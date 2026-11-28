<?php

namespace Modules\Product\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Product\Models\ProductVideo;
use Symfony\Component\Process\Process;

class ProcessProductVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public ProductVideo $video) {}

    public function handle(): void
    {
        if ($this->video->source_type !== 'upload' || !$this->video->video_path) {
            $this->video->update(['processing_status' => 'ready']);
            return;
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($this->video->video_path)) {
            $this->video->update(['processing_status' => 'failed']);
            return;
        }

        $this->video->update(['processing_status' => 'processing']);

        $fullPath = $disk->path($this->video->video_path);
        $updates = [
            'file_size'         => $this->video->file_size ?? $disk->size($this->video->video_path),
            'mime_type'         => $this->video->mime_type ?? $disk->mimeType($this->video->video_path),
            'processing_status' => 'ready',
        ];

        try {
            $probe = new Process([
                'ffprobe', '-v', 'error', '-select_streams', 'v:0',
                '-show_entries', 'stream=width,height:format=duration',
                '-of', 'json', $fullPath,
            ]);
            $probe->run();

            if ($probe->isSuccessful()) {
                $json = json_decode($probe->getOutput(), true);
                $stream = $json['streams'][0] ?? [];
                $duration = $json['format']['duration'] ?? null;

                if (!empty($stream['width'])) {
                    $updates['width'] = (int) $stream['width'];
                }
                if (!empty($stream['height'])) {
                    $updates['height'] = (int) $stream['height'];
                }
                if ($duration !== null) {
                    $updates['duration_seconds'] = (int) round((float) $duration);
                }
            }

            if (!$this->video->poster_path) {
                $posterRel = 'products/video-posters/' . pathinfo($this->video->video_path, PATHINFO_FILENAME) . '.jpg';
                $disk->makeDirectory('products/video-posters');
                $posterAbs = $disk->path($posterRel);

                $thumb = new Process([
                    'ffmpeg', '-y', '-ss', '00:00:01', '-i', $fullPath,
                    '-vframes', '1', '-q:v', '4', $posterAbs,
                ]);
                $thumb->run();

                if ($thumb->isSuccessful() && $disk->exists($posterRel)) {
                    $updates['poster_path'] = $posterRel;
                }
            }
        } catch (\Throwable $e) {
            // Host without ffmpeg/ffprobe still marks valid uploaded video ready for HTML5 playback
            Log::info('FFmpeg metadata extraction skipped', ['video_id' => $this->video->id, 'reason' => $e->getMessage()]);
        }

        $this->video->update($updates);
    }
}

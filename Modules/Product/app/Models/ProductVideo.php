<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Traits\HasUuid;

class ProductVideo extends Model
{
    use HasUuid;

    protected $table = 'product_videos';

    protected $fillable = [
        'product_id',
        'variant_id',
        'source_type',
        'title',
        'video_path',
        'external_url',
        'embed_id',
        'hls_url',
        'poster_path',
        'mime_type',
        'file_size',
        'duration_seconds',
        'width',
        'height',
        'processing_status',
        'is_primary',
        'sort_order',
    ];

    protected $casts = [
        'file_size'        => 'integer',
        'duration_seconds' => 'integer',
        'width'            => 'integer',
        'height'           => 'integer',
        'is_primary'       => 'boolean',
        'sort_order'       => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function getVideoUrlAttribute(): ?string
    {
        if ($this->hls_url) {
            return $this->hls_url;
        }
        if ($this->external_url) {
            return $this->external_url;
        }
        if (!$this->video_path) {
            return null;
        }
        return str_starts_with($this->video_path, 'http')
            ? $this->video_path
            : asset('storage/' . $this->video_path);
    }

    public function getPosterUrlAttribute(): ?string
    {
        if ($this->poster_path) {
            return str_starts_with($this->poster_path, 'http')
                ? $this->poster_path
                : asset('storage/' . $this->poster_path);
        }

        if ($this->source_type === 'youtube' && $this->embed_id) {
            return "https://img.youtube.com/vi/{$this->embed_id}/hqdefault.jpg";
        }

        return null;
    }

    public function getEmbedUrlAttribute(): ?string
    {
        if (!$this->embed_id) {
            return null;
        }

        return match ($this->source_type) {
            'youtube' => "https://www.youtube.com/embed/{$this->embed_id}",
            'vimeo'   => "https://player.vimeo.com/video/{$this->embed_id}",
            default   => null,
        };
    }

    /**
     * Detect provider & embed ID from external video URLs.
     *
     * @return array{source_type: string, embed_id: ?string}
     */
    public static function extractVideoMetadata(string $url): array
    {
        if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{6,})/', $url, $m)) {
            return ['source_type' => 'youtube', 'embed_id' => $m[1]];
        }

        if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $url, $m)) {
            return ['source_type' => 'vimeo', 'embed_id' => $m[1]];
        }

        return ['source_type' => 'external', 'embed_id' => null];
    }
}

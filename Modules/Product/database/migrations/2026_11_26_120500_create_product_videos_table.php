<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'has_video')) {
                $table->boolean('has_video')->default(false)->after('thumbnail');
            }
        });

        if (!Schema::hasTable('product_videos')) {
            Schema::create('product_videos', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
                $table->string('source_type', 20)->default('upload'); // upload, youtube, vimeo, external
                $table->string('title')->nullable();
                $table->string('video_path')->nullable();
                $table->string('external_url', 1000)->nullable();
                $table->string('embed_id', 100)->nullable();
                $table->string('hls_url', 1000)->nullable();
                $table->string('poster_path')->nullable();
                $table->string('mime_type', 100)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->unsignedInteger('width')->nullable();
                $table->unsignedInteger('height')->nullable();
                $table->string('processing_status', 20)->default('ready'); // pending, processing, ready, failed
                $table->boolean('is_primary')->default(false);
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->index(['product_id', 'sort_order']);
                $table->index(['product_id', 'is_primary']);
                $table->index('processing_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_videos');

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'has_video')) {
                $table->dropColumn('has_video');
            }
        });
    }
};

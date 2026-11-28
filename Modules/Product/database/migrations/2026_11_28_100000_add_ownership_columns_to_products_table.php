<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'vendor_id')) {
                $table->foreignId('vendor_id')
                    ->nullable()
                    ->after('brand_id')
                    ->constrained('vendors')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('products', 'created_by')) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('published_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('products', 'updated_by')) {
                $table->foreignId('updated_by')
                    ->nullable()
                    ->after('created_by')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            $table->index(['vendor_id', 'status', 'deleted_at'], 'products_vendor_status_del_idx');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_vendor_status_del_idx');

            foreach (['updated_by', 'created_by', 'vendor_id'] as $col) {
                if (Schema::hasColumn('products', $col)) {
                    $table->dropConstrainedForeignId($col);
                }
            }
        });
    }
};

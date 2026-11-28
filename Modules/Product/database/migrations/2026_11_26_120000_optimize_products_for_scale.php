<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        // Allow flexible workflow statuses ('draft', 'pending', 'active', 'inactive', 'rejected')
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE products MODIFY status VARCHAR(20) NOT NULL DEFAULT 'draft'");
        }

        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'effective_price')) {
                $table->decimal('effective_price', 12, 2)->default(0)->after('cost_price');
            }
            if (!Schema::hasColumn('products', 'min_price')) {
                $table->decimal('min_price', 12, 2)->nullable()->after('effective_price');
            }
            if (!Schema::hasColumn('products', 'max_price')) {
                $table->decimal('max_price', 12, 2)->nullable()->after('min_price');
            }
            if (!Schema::hasColumn('products', 'total_stock')) {
                $table->integer('total_stock')->default(0)->after('stock_quantity');
            }
        });

        // Backfill denormalized price/stock metrics for existing rows
        DB::table('products')->update([
            'effective_price' => DB::raw('COALESCE(sale_price, price, 0)'),
            'min_price'       => DB::raw('COALESCE(sale_price, price, 0)'),
            'max_price'       => DB::raw('COALESCE(sale_price, price, 0)'),
            'total_stock'     => DB::raw('COALESCE(stock_quantity, 0)'),
        ]);

        // Drop redundant single-column index on slug (already covered by unique constraint)
        try {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('products_slug_index');
            });
        } catch (\Throwable) {
            // Index may already be absent on some engines
        }

        Schema::table('products', function (Blueprint $table) use ($driver) {
            $table->index(['status', 'deleted_at', 'created_at'], 'products_status_del_created_idx');
            $table->index(['status', 'deleted_at', 'category_id', 'created_at'], 'products_status_del_cat_created_idx');
            $table->index(['status', 'deleted_at', 'category_id', 'effective_price'], 'products_status_del_cat_price_idx');
            $table->index(['status', 'deleted_at', 'brand_id', 'created_at'], 'products_status_del_brand_created_idx');
            $table->index(['status', 'deleted_at', 'is_featured', 'created_at'], 'products_status_del_featured_idx');
            $table->index(['status', 'deleted_at', 'total_sold'], 'products_status_del_sold_idx');

            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $table->fullText(['name', 'short_description'], 'products_fulltext_name_idx');
            }
        });

        Schema::table('vendor_product_prices', function (Blueprint $table) {
            $table->index(['product_id', 'is_active', 'price'], 'vpp_product_active_price_idx');
        });
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        Schema::table('vendor_product_prices', function (Blueprint $table) {
            $table->dropIndex('vpp_product_active_price_idx');
        });

        Schema::table('products', function (Blueprint $table) use ($driver) {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $table->dropFullText('products_fulltext_name_idx');
            }
            $table->dropIndex('products_status_del_created_idx');
            $table->dropIndex('products_status_del_cat_created_idx');
            $table->dropIndex('products_status_del_cat_price_idx');
            $table->dropIndex('products_status_del_brand_created_idx');
            $table->dropIndex('products_status_del_featured_idx');
            $table->dropIndex('products_status_del_sold_idx');

            $table->dropColumn(['effective_price', 'min_price', 'max_price', 'total_stock']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            $table->text('return_policy')->nullable()->change();
            $table->text('shipping_policy')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            $table->string('return_policy', 50)->nullable()->change();
            $table->string('shipping_policy', 50)->nullable()->change();
        });
    }
};

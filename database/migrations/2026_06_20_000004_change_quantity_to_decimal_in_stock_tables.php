<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->decimal('quantity', 10, 2)->default(0)->change();
            $table->decimal('reserved_qty', 10, 2)->default(0)->change();
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->decimal('quantity_before', 10, 2)->default(0)->change();
            $table->decimal('quantity_change', 10, 2)->change();
            $table->decimal('quantity_after', 10, 2)->default(0)->change();
        });

        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->decimal('quantity_returned', 10, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->integer('quantity')->default(0)->change();
            $table->integer('reserved_qty')->default(0)->change();
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->integer('quantity_before')->default(0)->change();
            $table->integer('quantity_change')->change();
            $table->integer('quantity_after')->default(0)->change();
        });

        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->integer('quantity_returned')->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('quantity_ordered', 10, 2)->change();
            $table->decimal('quantity_received', 10, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->integer('quantity_ordered')->change();
            $table->integer('quantity_received')->default(0)->change();
        });
    }
};

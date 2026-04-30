<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sale_id');
            $table->uuid('product_id');
            $table->uuid('variant_id')->nullable();
            $table->string('product_name'); // snapshot at time of sale
            $table->string('variant_label')->nullable();
            $table->integer('quantity');
            $table->bigInteger('unit_price')->default(0);  // Paisa - selling price at time of sale
            $table->bigInteger('cost_price')->default(0);  // Paisa - for margin reporting
            $table->bigInteger('discount')->default(0);    // Paisa - item-level discount
            $table->bigInteger('line_total')->default(0);  // Paisa
            $table->timestamps();

            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('restrict');
            $table->index('sale_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};

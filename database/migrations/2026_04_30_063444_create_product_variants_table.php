<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->string('size')->nullable();   // S, M, L, XL, 36, 38, 40...
            $table->string('color')->nullable();  // Red, Blue, Black...
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->bigInteger('cost_price')->default(0);    // Paisa
            $table->bigInteger('selling_price')->default(0); // Paisa
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};

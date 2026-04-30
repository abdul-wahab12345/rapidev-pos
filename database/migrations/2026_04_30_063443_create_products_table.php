<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('category_id')->nullable();
            $table->string('name');
            $table->string('name_ur')->nullable(); // Urdu name
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->text('description')->nullable();
            $table->string('unit')->default('piece'); // piece, kg, dozen, meter, liter, gram, box
            // Prices stored in Paisa (1 PKR = 100 Paisa) to avoid floating point errors
            $table->bigInteger('cost_price')->default(0);
            $table->bigInteger('selling_price')->default(0);
            $table->boolean('has_variants')->default(false);
            $table->integer('reorder_level')->default(5);
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            $table->index('tenant_id');
            $table->index(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'barcode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

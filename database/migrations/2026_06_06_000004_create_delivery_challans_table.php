<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_challans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('sale_id')->nullable();
            $table->uuid('quotation_id')->nullable();
            $table->uuid('customer_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('challan_number')->unique();                // DC-000001
            $table->string('status')->default('pending');              // pending|dispatched|delivered
            $table->date('delivery_date')->nullable();
            $table->string('vehicle_number')->nullable();
            $table->string('driver_name')->nullable();
            $table->text('site_address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::create('delivery_challan_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('challan_id');
            $table->uuid('product_id')->nullable();
            $table->uuid('variant_id')->nullable();
            $table->string('lot_number')->nullable();
            $table->string('product_name');                            // snapshot
            $table->string('product_unit')->default('piece');          // snapshot
            $table->decimal('quantity', 12, 3)->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('challan_id')->references('id')->on('delivery_challans')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_challan_items');
        Schema::dropIfExists('delivery_challans');
    }
};

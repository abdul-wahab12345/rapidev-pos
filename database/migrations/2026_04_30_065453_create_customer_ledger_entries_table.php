<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('customer_id');
            $table->uuid('sale_id')->nullable();
            $table->string('type'); // sale, payment, adjustment
            $table->bigInteger('amount')->default(0);            // Paisa — positive = debit (owes us), negative = credit (paid)
            $table->bigInteger('running_balance')->default(0);   // Paisa — balance after this entry
            $table->string('description')->nullable();
            $table->string('payment_method')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('set null');
            $table->index(['tenant_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_ledger_entries');
    }
};

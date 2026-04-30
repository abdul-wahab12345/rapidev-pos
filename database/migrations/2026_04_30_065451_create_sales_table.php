<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('branch_id');
            $table->unsignedBigInteger('user_id'); // cashier (matches users.id bigint)
            $table->uuid('customer_id')->nullable();
            $table->string('invoice_number')->unique();
            $table->string('status')->default('completed'); // completed, returned, void
            // All amounts in Paisa
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('discount')->default(0);
            $table->bigInteger('tax')->default(0);
            $table->bigInteger('total')->default(0);
            $table->bigInteger('paid')->default(0);
            $table->bigInteger('change_amount')->default(0);
            // Payment breakdown
            $table->bigInteger('cash_amount')->default(0);
            $table->bigInteger('jazzcash_amount')->default(0);
            $table->bigInteger('easypaisa_amount')->default(0);
            $table->bigInteger('udhaar_amount')->default(0);
            $table->string('payment_method')->default('cash'); // cash, jazzcash, easypaisa, udhaar, mixed
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            // user_id references users.id (bigint) cast to match
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->index('tenant_id');
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};

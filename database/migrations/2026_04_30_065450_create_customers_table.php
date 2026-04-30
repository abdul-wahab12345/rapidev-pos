<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('phone');
            $table->string('cnic', 15)->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            // Credit balance in Paisa (positive = customer owes us, negative = we owe customer)
            $table->bigInteger('current_balance')->default(0);
            $table->bigInteger('credit_limit')->default(0); // 0 = no limit
            $table->bigInteger('total_spend')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index('tenant_id');
            $table->index(['tenant_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};

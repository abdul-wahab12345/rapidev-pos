<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUuid('account_id')->constrained('accounts')->restrictOnDelete();
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();

            $table->string('expense_number', 20);   // EXP-00001
            $table->date('expense_date');
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 20)->default('cash'); // cash | bank
            $table->string('description')->nullable();
            $table->text('notes')->nullable();
            $table->string('reference', 100)->nullable(); // receipt / invoice number

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'expense_number']);
            $table->index(['tenant_id', 'expense_date']);
            $table->index(['tenant_id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};

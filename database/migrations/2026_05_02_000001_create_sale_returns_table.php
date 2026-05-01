<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUuid('sale_id')->constrained('sales')->restrictOnDelete();
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();

            $table->string('return_number', 20);        // RET-00001
            $table->date('return_date');
            $table->string('reason')->nullable();
            $table->string('refund_method', 20);        // cash | bank | store_credit
            $table->unsignedBigInteger('total_refund');  // integer rupees, same as SaleItem convention
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('completed');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'return_number']);
            $table->index(['tenant_id', 'return_date']);
            $table->index(['sale_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_returns');
    }
};

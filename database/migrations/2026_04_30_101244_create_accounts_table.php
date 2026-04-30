<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('code', 20);
            $table->string('name');
            // asset | liability | equity | income | expense
            $table->string('type', 20);
            // current_asset | fixed_asset | bank | receivable |
            // current_liability | long_term_liability | payable |
            // equity | revenue | cogs | operating_expense | other_expense
            $table->string('sub_type', 40)->nullable();
            $table->uuid('parent_id')->nullable();
            $table->boolean('is_system')->default(false);   // cannot be deleted
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};

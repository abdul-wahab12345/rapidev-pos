<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('name');
            $table->string('company')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('address', 300)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('ntn', 20)->nullable();
            $table->string('cnic', 20)->nullable();

            $table->boolean('is_customer')->default(false);
            $table->boolean('is_supplier')->default(false);

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_customer']);
            $table->index(['tenant_id', 'is_supplier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};

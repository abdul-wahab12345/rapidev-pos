<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('branch_id')->nullable();
            $table->string('name');          // e.g. "Table 1", "VIP 2"
            $table->integer('capacity')->default(4);
            $table->string('section')->nullable(); // e.g. "Ground Floor", "Rooftop"
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // Add table_id + order_type to sales
        Schema::table('sales', function (Blueprint $table) {
            $table->uuid('dining_table_id')->nullable()->after('customer_id');
            $table->string('order_type')->default('takeaway')->after('dining_table_id'); // dine_in | takeaway
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['dining_table_id', 'order_type']);
        });
        Schema::dropIfExists('dining_tables');
    }
};

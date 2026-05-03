<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->uuid('rate_list_id')->nullable()->after('customer_id');
            $table->foreign('rate_list_id')->references('id')->on('rate_lists')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['rate_list_id']);
            $table->dropColumn('rate_list_id');
        });
    }
};

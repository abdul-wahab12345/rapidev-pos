<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->unsignedInteger('boxes_count')->nullable()->after('restock');
            $table->unsignedInteger('loose_tiles_count')->nullable()->after('boxes_count');
        });
    }

    public function down(): void
    {
        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->dropColumn(['boxes_count', 'loose_tiles_count']);
        });
    }
};

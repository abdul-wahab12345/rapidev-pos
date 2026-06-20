<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->unsignedInteger('boxes_count')->nullable()->after('quantity');
            $table->unsignedInteger('loose_tiles_count')->nullable()->after('boxes_count');
            $table->timestamp('box_count_at')->nullable()->after('loose_tiles_count');
        });
    }

    public function down(): void
    {
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->dropColumn(['boxes_count', 'loose_tiles_count', 'box_count_at']);
        });
    }
};

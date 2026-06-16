<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->string('lot_number')->nullable()->after('variant_id');
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->string('lot_number')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->dropColumn('lot_number');
        });
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropColumn('lot_number');
        });
    }
};

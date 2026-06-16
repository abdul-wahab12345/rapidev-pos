<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('tile_width_cm', 'tile_width_in');
            $table->renameColumn('tile_height_cm', 'tile_height_in');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('tile_width_in', 'tile_width_cm');
            $table->renameColumn('tile_height_in', 'tile_height_cm');
        });
    }
};

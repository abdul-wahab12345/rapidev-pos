<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Material classification
            $table->string('material_type')->nullable()->after('description'); // marble|tile|ceramic|granite|mosaic|border
            $table->string('finish')->nullable()->after('material_type');       // polished|matte|satin|anti_slip|rough|rustic|glazed
            $table->string('origin')->nullable()->after('finish');              // Italy|Spain|Pakistan|China|Turkey|India|Iran|Other

            // Physical attributes
            $table->decimal('thickness_mm', 6, 2)->nullable()->after('origin');

            // Tile/Ceramic box dimensions (NULL for marble/slab types)
            $table->decimal('tile_width_cm', 8, 2)->nullable()->after('thickness_mm');
            $table->decimal('tile_height_cm', 8, 2)->nullable()->after('tile_width_cm');
            $table->unsignedInteger('tiles_per_box')->nullable()->after('tile_height_cm');
            // sq_m coverage per box = (tile_width_cm/100) * (tile_height_cm/100) * tiles_per_box
            $table->decimal('sq_m_per_box', 8, 4)->nullable()->after('tiles_per_box');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'material_type', 'finish', 'origin',
                'thickness_mm', 'tile_width_cm', 'tile_height_cm',
                'tiles_per_box', 'sq_m_per_box',
            ]);
        });
    }
};

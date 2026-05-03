<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->string('name_ur', 191)->nullable()->after('name');
        });

        $path = database_path('data/pakistan_cities_ur.json');
        if (! is_readable($path)) {
            return;
        }

        $map = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        foreach ($map as $province => $cities) {
            foreach ($cities as $english => $urdu) {
                DB::table('cities')->where('province', $province)->where('name', $english)->update(['name_ur' => $urdu]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn('name_ur');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('address')->constrained('cities')->nullOnDelete();
            $table->foreignId('area_id')->nullable()->after('city_id')->constrained('areas')->nullOnDelete();
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('address')->constrained('cities')->nullOnDelete();
            $table->foreignId('area_id')->nullable()->after('city_id')->constrained('areas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_id');
            $table->dropConstrainedForeignId('city_id');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_id');
            $table->dropConstrainedForeignId('city_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->decimal('quantity_returned', 10, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->integer('quantity_returned')->change();
        });
    }
};

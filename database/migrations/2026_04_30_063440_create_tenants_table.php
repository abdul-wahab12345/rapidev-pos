<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('subdomain')->unique();
            $table->string('plan')->default('trial'); // trial, basic, standard, pro
            $table->string('status')->default('active'); // active, suspended, cancelled
            $table->timestamp('trial_ends_at')->nullable();
            $table->json('settings')->nullable(); // business_name, logo, currency, language, etc.
            $table->timestamps();
            $table->softDeletes();
        });

        // Add tenant_id to users table
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->after('id');
            $table->string('role')->default('cashier')->after('email'); // owner, manager, cashier
            $table->string('pin', 6)->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('pin');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn(['tenant_id', 'role', 'pin', 'is_active']);
        });
        Schema::dropIfExists('tenants');
    }
};

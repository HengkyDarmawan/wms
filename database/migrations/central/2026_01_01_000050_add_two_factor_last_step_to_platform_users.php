<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A-205: sama dengan `users` tenant — kode TOTP Super Admin tidak bisa
 * dipakai ulang dalam jendelanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_users', function (Blueprint $table) {
            $table->unsignedBigInteger('two_factor_last_step')->nullable()->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('platform_users', function (Blueprint $table) {
            $table->dropColumn('two_factor_last_step');
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A-180 (diperketat): tautan masuk akses dukungan sekali pakai. Hanya hash
 * nonce tautan terakhir yang disimpan; tautan lama gugur saat tautan baru
 * dibuat atau begitu dipakai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_accesses', function (Blueprint $table) {
            $table->string('link_nonce_hash', 64)->nullable()->after('reason');
            $table->dateTime('link_used_at')->nullable()->after('link_nonce_hash');
        });
    }

    public function down(): void
    {
        Schema::table('support_accesses', function (Blueprint $table) {
            $table->dropColumn(['link_nonce_hash', 'link_used_at']);
        });
    }
};

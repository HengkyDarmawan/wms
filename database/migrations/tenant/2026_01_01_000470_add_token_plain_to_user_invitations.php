<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tautan undangan yang bisa disalin (A-333).
 *
 * `token` tetap menyimpan hash SHA-256 dan tetap satu-satunya jalan mencocokkan
 * token saat undangan dibuka. Kolom baru ini menyimpan token mentah dalam
 * keadaan **terenkripsi** (cast `encrypted`, kunci APP_KEY) semata-mata supaya
 * Admin bisa menampilkan dan menyalin tautannya lagi selama undangan belum
 * dipakai — penting karena mailer bisa saja mati.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_invitations', function (Blueprint $table) {
            $table->text('token_plain')->nullable()->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('user_invitations', function (Blueprint $table) {
            $table->dropColumn('token_plain');
        });
    }
};

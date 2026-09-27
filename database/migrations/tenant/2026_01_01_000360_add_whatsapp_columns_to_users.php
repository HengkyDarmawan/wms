<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2a WhatsApp (31-whatsapp §3, A-275, A-280): nomor WhatsApp user harus
 * diverifikasi dengan kode sebelum dipakai (BR-WA-01); kode disimpan sebagai
 * hash dengan masa berlaku & hitungan salah; `wa_digest_sent_at` = ringkasan
 * harian terakhir. Kolom `notification_preferences.whatsapp` sebelumnya
 * selalu ditulis `false` karena layar belum menawarkan WhatsApp; nilai itu
 * bukan pilihan user, jadi dinyalakan (= ikut izin company, A-276).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dateTime('phone_verified_at')->nullable()->after('phone');
            $table->string('wa_code_hash', 255)->nullable()->after('phone_verified_at');
            $table->dateTime('wa_code_expires_at')->nullable()->after('wa_code_hash');
            $table->unsignedTinyInteger('wa_code_attempts')->default(0)->after('wa_code_expires_at');
            $table->dateTime('wa_digest_sent_at')->nullable()->after('wa_code_attempts');
        });

        DB::table('notification_preferences')->update(['whatsapp' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone_verified_at', 'wa_code_hash', 'wa_code_expires_at', 'wa_code_attempts', 'wa_digest_sent_at']);
        });
    }
};

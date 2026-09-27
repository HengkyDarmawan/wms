<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A-273: OTP bukti terima dikirim otomatis ke HP penerima (O-15). Kapan OTP
 * terakhir terkirim dan berapa kali (termasuk kirim ulang dari halaman
 * penerima) dicatat per tautan; kosong = OTP disampaikan driver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_tokens', function (Blueprint $table) {
            $table->dateTime('otp_sent_at')->nullable()->after('attempts');
            $table->unsignedTinyInteger('otp_send_count')->default(0)->after('otp_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_tokens', function (Blueprint $table) {
            $table->dropColumn(['otp_sent_at', 'otp_send_count']);
        });
    }
};

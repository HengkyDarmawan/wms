<?php

declare(strict_types=1);

namespace App\Domain\WhatsApp\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Shared\Messaging\PhoneNumber;
use App\Domain\WhatsApp\Support\WhatsAppChannel;
use App\Domain\WhatsApp\Transport\WhatsAppNotSent;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Permission: `profile.update` — verifikasi nomor WhatsApp milik sendiri
 * (BR-WA-01, A-275). Kode 6 digit lewat template autentikasi `wms_kode`,
 * berlaku 10 menit, jeda kirim ulang 60 detik, 5 kali salah = harus minta
 * kode baru. Mengganti nomor menghapus status terverifikasi.
 */
class VerifyWhatsAppNumber
{
    public const BERLAKU_MENIT = 10;

    public const JEDA_DETIK = 60;

    public const MAKS_SALAH = 5;

    public function __construct(private readonly WhatsAppChannel $channel) {}

    public function sendCode(User $user, string $phone): void
    {
        $nomor = PhoneNumber::normalize($phone);

        if ($nomor === null) {
            throw ValidationException::withMessages(['phone' => __('Nomor HP tidak sah. Contoh: 0812 3456 7890.')]);
        }

        if (! $this->channel->enabled()) {
            throw ValidationException::withMessages(['phone' => __('WhatsApp belum aktif untuk company ini.')]);
        }

        $dikirim = $user->wa_code_expires_at?->copy()->subMinutes(self::BERLAKU_MENIT);

        if ($dikirim !== null && $dikirim->diffInSeconds(now()) < self::JEDA_DETIK && PhoneNumber::normalize($user->phone) === $nomor) {
            throw ValidationException::withMessages(['phone' => __('Tunggu satu menit sebelum meminta kode baru.')]);
        }

        $kode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        try {
            $this->channel->authCode($nomor, $kode, ['event' => 'verifikasi_nomor', 'user_id' => $user->id]);
        } catch (WhatsAppNotSent $e) {
            throw ValidationException::withMessages(['phone' => __('Kode gagal dikirim: :e', ['e' => $e->getMessage()])]);
        }

        $ganti = PhoneNumber::normalize($user->phone) !== $nomor;

        $user->forceFill([
            'phone' => $nomor,
            'phone_verified_at' => $ganti ? null : $user->phone_verified_at,
            'wa_code_hash' => Hash::make($kode),
            'wa_code_expires_at' => now()->addMinutes(self::BERLAKU_MENIT),
            'wa_code_attempts' => 0,
        ])->save();
    }

    public function confirm(User $user, string $code): void
    {
        if ($user->wa_code_hash === null || $user->wa_code_expires_at === null || $user->wa_code_expires_at->isPast()) {
            throw ValidationException::withMessages(['code' => __('Kode tidak berlaku. Minta kode baru.')]);
        }

        if ($user->wa_code_attempts >= self::MAKS_SALAH) {
            throw ValidationException::withMessages(['code' => __('Terlalu banyak kode salah. Minta kode baru.')]);
        }

        if (! Hash::check(trim($code), (string) $user->wa_code_hash)) {
            $user->increment('wa_code_attempts');

            throw ValidationException::withMessages(['code' => __('Kode salah.')]);
        }

        $user->forceFill([
            'phone_verified_at' => now(),
            'wa_code_hash' => null,
            'wa_code_expires_at' => null,
            'wa_code_attempts' => 0,
        ])->save();
    }

    /** Hapus nomor WhatsApp: pesan WhatsApp berhenti untuk user ini. */
    public function remove(User $user): void
    {
        $user->forceFill(['phone' => null, 'phone_verified_at' => null, 'wa_code_hash' => null, 'wa_code_expires_at' => null, 'wa_code_attempts' => 0])->save();
    }
}

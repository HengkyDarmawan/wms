<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Shipment\Models\DeliveryToken;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Support\DeliveryOtpSender;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Permission: `shipment.ship` — menerbitkan tautan bukti terima bertoken
 * (A-41, BR-SJ-05).
 *
 * Penerima di lapangan sering tidak punya akun. Membuatkan akun untuk orang
 * yang mungkin hanya ditemui sekali akan menumpuk akun mati; tautan sekali
 * pakai berumur 24 jam dengan OTP adalah jalan tengahnya.
 *
 * OTP tidak pernah disimpan sebagai teks. Bila company menyalakan OTP
 * otomatis dan platform punya kanal WhatsApp/SMS (A-273, O-15), OTP dikirim
 * ke HP penerima dan **tidak** dikembalikan ke layar; kalau tidak, atau gagal
 * terkirim, OTP dikembalikan sekali untuk disampaikan staf/driver.
 */
class IssueDeliveryToken
{
    private const MASA_BERLAKU_JAM = 24;

    private const MAKS_PERCOBAAN = 5;

    public function __construct(private readonly DeliveryOtpSender $sender) {}

    /**
     * @return array{token: DeliveryToken, otp: ?string, sent: bool, phone: ?string, error: ?string}
     *                                                                                               `otp` null bila sudah terkirim ke penerima
     */
    public function handle(Shipment $shipment, ?string $phone = null, ?User $actor = null): array
    {
        if ($shipment->status !== ShipmentStatus::Shipped) {
            throw ShipmentRuleException::rule(
                'BR-SJ-05',
                'Tautan bukti terima hanya diterbitkan untuk surat jalan yang sedang dikirim.',
            );
        }

        if ($shipment->proof()->exists()) {
            throw ShipmentRuleException::rule('BR-SJ-05', 'Surat jalan ini sudah punya bukti terima.');
        }

        // Tautan lama dimatikan: satu SJ hanya boleh punya satu tautan hidup,
        // supaya tidak ada dua orang yang merasa berhak menandatangani.
        $shipment->tokens()->usable()->update(['used_at' => now()]);

        $otp = $this->otpBaru();

        $token = DeliveryToken::create([
            'shipment_id' => $shipment->id,
            'token' => Str::random(64),
            'otp_hash' => Hash::make($otp),
            'phone' => $phone,
            'expires_at' => now()->addHours(self::MASA_BERLAKU_JAM),
        ]);

        activity('shipment')
            ->performedOn($shipment)
            ->causedBy($actor)
            ->withProperties(['telepon' => $phone, 'berlaku_sampai' => $token->expires_at?->toDateTimeString()])
            ->log('Tautan bukti terima diterbitkan');

        $kirim = $this->sender->send($token, $otp, $shipment);

        return ['token' => $token->refresh(), 'otp' => $kirim['sent'] ? null : $otp] + $kirim;
    }

    /**
     * Kirim ulang OTP dari halaman penerima (A-273): OTP lama tidak berlaku,
     * kode baru dikirim ke nomor yang sama. Dibatasi {@see DeliveryOtpSender::MAKS_KIRIM}
     * kiriman per tautan dan jeda {@see DeliveryOtpSender::JEDA_DETIK} detik;
     * hitungan percobaan salah tidak di-nol-kan (NFR-04).
     */
    public function resend(string $token): DeliveryToken
    {
        $baris = DeliveryToken::query()->where('token', $token)->first();

        if ($baris === null || ! $baris->isUsable()) {
            throw ShipmentRuleException::rule('BR-SJ-05', 'Tautan bukti terima tidak berlaku atau sudah kedaluwarsa.');
        }

        if ($baris->isLockedOut(self::MAKS_PERCOBAAN)) {
            throw ShipmentRuleException::rule('NFR-04', 'Tautan ini terkunci karena terlalu banyak percobaan.');
        }

        if (! $this->sender->enabled() || $baris->otp_sent_at === null) {
            throw ShipmentRuleException::rule('BR-SJ-05', 'Kode OTP tautan ini disampaikan driver; minta kodenya kepada driver.');
        }

        if ($baris->otp_send_count >= DeliveryOtpSender::MAKS_KIRIM) {
            throw ShipmentRuleException::rule('NFR-04', 'Kode sudah dikirim ulang terlalu sering. Minta driver menerbitkan tautan baru.');
        }

        if ($baris->otp_sent_at->diffInSeconds(now()) < DeliveryOtpSender::JEDA_DETIK) {
            throw ShipmentRuleException::rule('NFR-04', 'Tunggu satu menit sebelum meminta kode baru.');
        }

        $otp = $this->otpBaru();
        $baris->forceFill(['otp_hash' => Hash::make($otp)])->save();

        $hasil = $this->sender->send($baris, $otp, $baris->shipment);

        if (! $hasil['sent']) {
            throw ShipmentRuleException::rule('BR-SJ-05', 'Kode baru gagal dikirim. Minta kode kepada driver.');
        }

        return $baris->refresh();
    }

    private function otpBaru(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Memverifikasi tautan dan OTP-nya.
     *
     * NFR-04: percobaan dihitung dan dibatasi. Token yang habis percobaannya
     * mati, bukan sekadar menolak sekali — enam digit terlalu mudah ditebak
     * bila boleh dicoba tanpa batas.
     */
    public function verify(string $token, string $otp): DeliveryToken
    {
        $baris = DeliveryToken::query()->where('token', $token)->first();

        if ($baris === null || ! $baris->isUsable()) {
            throw ShipmentRuleException::rule('BR-SJ-05', 'Tautan bukti terima tidak berlaku atau sudah kedaluwarsa.');
        }

        if ($baris->isLockedOut(self::MAKS_PERCOBAAN)) {
            throw ShipmentRuleException::rule('NFR-04', 'Tautan ini terkunci karena terlalu banyak percobaan.');
        }

        if (! Hash::check($otp, (string) $baris->otp_hash)) {
            $baris->increment('attempts');

            throw ShipmentRuleException::field('BR-SJ-05', 'otp', 'Kode OTP tidak cocok.');
        }

        return $baris;
    }

    /** Dipanggil setelah bukti terima tersimpan, supaya tautan tidak dipakai dua kali. */
    public function consume(DeliveryToken $token): DeliveryToken
    {
        $token->forceFill(['used_at' => now()])->save();

        return $token->refresh();
    }
}

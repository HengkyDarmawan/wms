<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Shipment\Models\DeliveryToken;
use App\Domain\Shipment\Models\Shipment;
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
 * OTP hanya dikembalikan **sekali**, saat diterbitkan, dan tidak pernah
 * disimpan sebagai teks. Pengirimannya lewat WhatsApp atau SMS menunggu
 * [O-06](docs/wms/04-keputusan-dan-asumsi.md#o-06); sampai itu ada, OTP
 * disampaikan lisan oleh staf yang menerbitkannya.
 */
class IssueDeliveryToken
{
    private const MASA_BERLAKU_JAM = 24;

    private const MAKS_PERCOBAAN = 5;

    /**
     * @return array{token: DeliveryToken, otp: string}  OTP hanya ada di sini
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

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

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

        return ['token' => $token, 'otp' => $otp];
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

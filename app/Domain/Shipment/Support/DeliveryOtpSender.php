<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Support;

use App\Domain\Master\Models\FeatureSetting;
use App\Domain\Shared\Messaging\MessageGateway;
use App\Domain\Shared\Messaging\MessageNotSent;
use App\Domain\Shared\Messaging\PhoneNumber;
use App\Domain\Shipment\Models\DeliveryToken;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\WhatsApp\Support\WhatsAppChannel;
use App\Domain\WhatsApp\Transport\WhatsAppNotSent;

/**
 * OTP bukti terima otomatis (A-273, O-15, A-41): OTP dikirim ke HP penerima
 * lewat kanal WhatsApp/SMS platform sehingga hanya penerima yang tahu kodenya.
 *
 * Aktif bila company menyalakan saklar `otp_auto` **dan** platform punya kanal
 * ({@see MessageGateway::available()}). Gagal kirim tidak menggagalkan
 * penerbitan tautan: pemanggil kembali ke jalur manual (OTP tampil sekali
 * untuk disampaikan staf/pengantar), sama seperti sebelum A-273.
 *
 * Bila WhatsApp company aktif (Fase 2a, A-279) OTP dikirim lewat template
 * autentikasi `wms_kode` — Meta tidak mengizinkan tautan di template itu, jadi
 * tautannya tetap dibagikan staf (tanpa kode); selain itu lewat kanal umum.
 */
class DeliveryOtpSender
{
    public const FEATURE = 'otp_auto';

    /** Kirim ulang dari halaman penerima: total kiriman per tautan dan jeda. */
    public const MAKS_KIRIM = 4;

    public const JEDA_DETIK = 60;

    public function __construct(
        private readonly MessageGateway $gateway,
        private readonly WhatsAppChannel $whatsapp,
    ) {}

    public function enabled(): bool
    {
        return FeatureSetting::enabled(self::FEATURE) && ($this->gateway->available() || $this->whatsapp->enabled());
    }

    /** Kanal yang dipakai: `whatsapp` (A-279) atau `gateway` (A-273). */
    public function via(): string
    {
        return $this->whatsapp->enabled() ? 'whatsapp' : 'gateway';
    }

    /**
     * @return array{sent: bool, phone: ?string, error: ?string, via: ?string} `phone` sudah disamarkan
     */
    public function send(DeliveryToken $token, string $otp, Shipment $shipment): array
    {
        if (! $this->enabled()) {
            return ['sent' => false, 'phone' => null, 'error' => null, 'via' => null];
        }

        $nomor = PhoneNumber::normalize($token->phone);

        if ($nomor === null) {
            return ['sent' => false, 'phone' => null, 'error' => __('Nomor HP penerima kosong atau tidak sah, jadi OTP tidak dikirim otomatis.'), 'via' => null];
        }

        $via = $this->via();

        try {
            if ($via === 'whatsapp') {
                $this->whatsapp->authCode($nomor, $otp, ['event' => 'otp_bukti_terima', 'shipment_id' => $shipment->id]);
            } else {
                $this->gateway->send($nomor, $this->pesan($shipment, $token, $otp));
            }
        } catch (MessageNotSent|WhatsAppNotSent $e) {
            activity('shipment')->performedOn($shipment)
                ->withProperties(['telepon' => PhoneNumber::mask($nomor), 'galat' => $e->getMessage()])
                ->log('OTP bukti terima gagal dikirim otomatis');

            return ['sent' => false, 'phone' => PhoneNumber::mask($nomor), 'error' => $e->getMessage(), 'via' => null];
        }

        $token->forceFill(['otp_sent_at' => now(), 'otp_send_count' => $token->otp_send_count + 1])->save();

        activity('shipment')->performedOn($shipment)
            ->withProperties(['telepon' => PhoneNumber::mask($nomor), 'kiriman_ke' => $token->otp_send_count, 'kanal' => $via])
            ->log('OTP bukti terima dikirim otomatis');

        return ['sent' => true, 'phone' => PhoneNumber::mask($nomor), 'error' => null, 'via' => $via];
    }

    /** Pesan untuk penerima: nomor SJ, asal, tautan, dan OTP (tanpa harga, D-07). */
    public function pesan(Shipment $shipment, DeliveryToken $token, string $otp): string
    {
        $shipment->loadMissing('warehouse:id,name');

        return __("Konfirmasi penerimaan barang :sj dari :gudang.\nBuka: :url\nKode OTP: :otp (jangan berikan ke siapa pun, termasuk pengantar). Berlaku sampai :sampai.", [
            'sj' => $shipment->number,
            'gudang' => $shipment->warehouse?->name ?? '-',
            'url' => route('terima.show', $token->token),
            'otp' => $otp,
            'sampai' => $token->expires_at?->lokal()->format('d/m/Y H:i'),
        ]);
    }
}

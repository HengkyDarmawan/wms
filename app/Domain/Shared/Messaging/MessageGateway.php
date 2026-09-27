<?php

declare(strict_types=1);

namespace App\Domain\Shared\Messaging;

/**
 * Kanal pesan singkat keluar (WhatsApp/SMS) milik platform (A-273, O-15).
 *
 * Satu antarmuka untuk semua penyedia: OTP bukti terima di Fase 1, dan
 * notifikasi/approval WhatsApp Fase 2a memakai kanal yang sama. Penyedia
 * dipilih lewat `config('wms.messaging.driver')`; kodenya tidak tahu merek.
 */
interface MessageGateway
{
    /** Apakah kanal ini benar-benar bisa mengirim (bukan `none`). */
    public function available(): bool;

    /**
     * Mengirim satu pesan teks ke nomor yang sudah dibakukan {@see PhoneNumber}.
     *
     * @throws MessageNotSent bila penyedia menolak atau tidak terjangkau
     */
    public function send(string $phone, string $text): void;
}

<?php

declare(strict_types=1);

namespace App\Domain\Shared\Messaging;

use Illuminate\Support\Facades\Log;

/**
 * Driver `log` untuk lokal & demo: pesan ditulis ke log aplikasi alih-alih
 * dikirim, jadi alur OTP otomatis bisa dicoba tanpa akun penyedia.
 */
class LogGateway implements MessageGateway
{
    public function available(): bool
    {
        return true;
    }

    public function send(string $phone, string $text): void
    {
        Log::info('[wms.messaging] pesan ke '.$phone, ['text' => $text]);
    }
}

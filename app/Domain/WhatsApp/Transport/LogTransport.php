<?php

declare(strict_types=1);

namespace App\Domain\WhatsApp\Transport;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Driver `log` (lokal & demo, A-274): pesan ditulis ke log dengan id palsu
 * `wamid.log.*`, sehingga token approval, log pemakaian, dan kuota tetap bisa
 * dicoba tanpa akun Meta. Tombol ditekan lewat `php artisan whatsapp:simulate`.
 */
class LogTransport implements WhatsAppTransport
{
    public function available(): bool
    {
        return true;
    }

    public function send(array $message): string
    {
        $id = 'wamid.log.'.Str::uuid()->toString();
        Log::info('[wms.whatsapp] '.$id, $message);

        return $id;
    }
}

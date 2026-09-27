<?php

declare(strict_types=1);

namespace App\Domain\WhatsApp\Transport;

/**
 * Cara pesan WhatsApp keluar dikirim (A-274): `cloud` (Graph API Meta),
 * `log` (lokal/demo), atau `none`. Pesan sudah berbentuk body Cloud API
 * (`to`, `type`, `template`/`text`); transport menambah `messaging_product`.
 */
interface WhatsAppTransport
{
    public function available(): bool;

    /**
     * @param  array<string, mixed>  $message
     * @return string `wa_message_id` dari Meta
     *
     * @throws WhatsAppNotSent
     */
    public function send(array $message): string;
}

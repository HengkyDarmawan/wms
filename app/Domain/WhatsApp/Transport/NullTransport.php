<?php

declare(strict_types=1);

namespace App\Domain\WhatsApp\Transport;

/** Driver `none`: WhatsApp belum diatur platform. */
class NullTransport implements WhatsAppTransport
{
    public function available(): bool
    {
        return false;
    }

    public function send(array $message): string
    {
        throw new WhatsAppNotSent('WhatsApp belum diatur oleh platform.');
    }
}

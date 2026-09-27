<?php

declare(strict_types=1);

namespace App\Domain\Shared\Messaging;

/** Driver `none`: kanal belum diatur platform; semua pengiriman jatuh ke jalur manual. */
class NullGateway implements MessageGateway
{
    public function available(): bool
    {
        return false;
    }

    public function send(string $phone, string $text): void
    {
        throw new MessageNotSent('Kanal WhatsApp/SMS belum diatur oleh platform.');
    }
}

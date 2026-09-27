<?php

declare(strict_types=1);

namespace App\Domain\WhatsApp\Transport;

use RuntimeException;

/** Pesan WhatsApp tidak terkirim (ditolak Meta, tidak terjangkau, kuota habis, atau kanal mati). */
class WhatsAppNotSent extends RuntimeException
{
    public static function quota(): self
    {
        return new self('Kuota WhatsApp company bulan ini habis.');
    }
}

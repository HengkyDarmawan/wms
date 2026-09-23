<?php

declare(strict_types=1);

namespace App\Domain\Request\Enums;

/** A-54: tambahan klien setelah REQ disetujui lahir sebagai REQ tersendiri. */
enum RequestOrigin: string
{
    case Regular = 'regular';
    case Supplement = 'supplement';

    public function label(): string
    {
        return $this === self::Regular ? 'Biasa' : 'REQ Tambahan';
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Regular->value => self::Regular->label(),
            self::Supplement->value => self::Supplement->label(),
        ];
    }
}

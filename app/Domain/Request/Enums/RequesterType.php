<?php

declare(strict_types=1);

namespace App\Domain\Request\Enums;

/** A-07: asal pemohon menentukan apakah REQ singgah di `under_review`. */
enum RequesterType: string
{
    case Internal = 'internal';
    case Client = 'client';

    public function label(): string
    {
        return $this === self::Internal ? 'Internal' : 'Klien';
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Internal->value => self::Internal->label(),
            self::Client->value => self::Client->label(),
        ];
    }

    public function needsReview(): bool
    {
        return $this === self::Client;
    }
}

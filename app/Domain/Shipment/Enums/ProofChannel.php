<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Enums;

/** A-41 — siapa yang mengisi bukti terima. */
enum ProofChannel: string
{
    case DriverPwa = 'driver_pwa';
    case TokenLink = 'token_link';

    public function label(): string
    {
        return $this === self::DriverPwa ? 'Aplikasi driver' : 'Tautan bertoken';
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::DriverPwa->value => self::DriverPwa->label(),
            self::TokenLink->value => self::TokenLink->label(),
        ];
    }
}

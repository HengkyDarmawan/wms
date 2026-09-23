<?php

declare(strict_types=1);

namespace App\Domain\Stock\Enums;

/** 13-stock §4 — status reservasi. */
enum ReservationStatus: string
{
    case Active = 'active';
    case Consumed = 'consumed';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Consumed => 'Terpenuhi',
            self::Released => 'Dilepas',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $hasil = [];

        foreach (self::cases() as $case) {
            $hasil[$case->value] = $case->label();
        }

        return $hasil;
    }

    public function badge(): string
    {
        return match ($this) {
            self::Active => 'primary',
            self::Consumed => 'success',
            self::Released => 'secondary',
        };
    }

    /** Hanya reservasi aktif yang mengurangi stok tersedia (BR-STK-03). */
    public function reducesAvailable(): bool
    {
        return $this === self::Active;
    }
}

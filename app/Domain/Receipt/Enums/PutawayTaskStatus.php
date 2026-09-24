<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Enums;

/**
 * Status PUT — Katalog Status §2.6. **Tidak boleh ditambah.**
 */
enum PutawayTaskStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
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
            self::Pending => 'text-bg-warning',
            self::Completed => 'text-bg-success',
            self::Cancelled => 'text-bg-danger',
        };
    }
}

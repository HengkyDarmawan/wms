<?php

declare(strict_types=1);

namespace App\Domain\Request\Enums;

/**
 * Status baris REQ. Baris tidak pernah dihapus (P-03): yang dibatalkan tetap
 * tampil supaya riwayat permintaan terbaca utuh.
 */
enum RequestLineStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Terbuka',
            self::Closed => 'Selesai',
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
}

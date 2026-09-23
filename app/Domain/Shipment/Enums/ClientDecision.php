<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Enums;

/** BR-SJ-10 — apakah klien masih membutuhkan barang yang kurang atau rusak. */
enum ClientDecision: string
{
    case StillNeeded = 'still_needed';
    case NotNeeded = 'not_needed';

    public function label(): string
    {
        return $this === self::StillNeeded ? 'Masih dibutuhkan' : 'Tidak dibutuhkan lagi';
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::StillNeeded->value => self::StillNeeded->label(),
            self::NotNeeded->value => self::NotNeeded->label(),
        ];
    }

    /** `not_needed` menutup sisa baris REQ alih-alih mengembalikannya ke backorder. */
    public function closesRequestLine(): bool
    {
        return $this === self::NotNeeded;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Enums;

/**
 * Status GRN — Katalog Status §2.5. **Tidak boleh ditambah.**
 *
 * QC bukan status: ia langkah per baris di dalam `received` (A-34). GRN yang
 * sudah `received` tidak bisa dibatalkan; koreksinya lewat ADJ atau RTV
 * (BR-GEN-04).
 */
enum GoodsReceiptStatus: string
{
    case Draft = 'draft';
    case Received = 'received';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Received => 'Diterima',
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

    public function isFinal(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'text-bg-secondary',
            self::Received => 'text-bg-primary',
            self::Completed => 'text-bg-success',
            self::Cancelled => 'text-bg-danger',
        };
    }
}

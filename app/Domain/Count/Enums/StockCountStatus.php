<?php

declare(strict_types=1);

namespace App\Domain\Count\Enums;

/**
 * Status sesi opname — Katalog Status §2.13. **Tidak boleh ditambah.**
 *
 * Tidak ada `rejected`: penolakan approval mengembalikan sesi ke tangan
 * perekonsiliasi dalam status `reconciling` untuk diajukan ulang (A-97).
 */
enum StockCountStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Recount = 'recount';
    case Reconciling = 'reconciling';
    case Approved = 'approved';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Direncanakan',
            self::InProgress => 'Berjalan',
            self::Recount => 'Hitung Ulang',
            self::Reconciling => 'Rekonsiliasi',
            self::Approved => 'Disetujui',
            self::Closed => 'Ditutup',
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
            self::Planned => 'text-bg-secondary',
            self::InProgress, self::Recount => 'text-bg-info',
            self::Reconciling => 'text-bg-warning',
            self::Approved => 'text-bg-primary',
            self::Closed => 'text-bg-success',
            self::Cancelled => 'text-bg-danger',
        };
    }

    /** Hitungan masih boleh diinput. */
    public function isCounting(): bool
    {
        return $this === self::InProgress || $this === self::Recount;
    }

    /** Bin cakupan sedang dibeku (bila sesi membekukan). */
    public function holdsFreeze(): bool
    {
        return in_array($this, [self::InProgress, self::Recount, self::Reconciling], true);
    }
}

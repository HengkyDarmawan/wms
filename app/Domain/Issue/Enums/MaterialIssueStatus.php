<?php

declare(strict_types=1);

namespace App\Domain\Issue\Enums;

/**
 * Status ISU — Katalog Status §2.9. **Tidak boleh ditambah.**
 *
 * `draft → confirmed` mengeluarkan barang dari bin Gudang Site (kejadian
 * `material_consumed`); `draft → cancelled` tanpa efek. ISU pembalik tetap
 * `draft` selama menunggu approval dan menjadi `confirmed` saat disetujui
 * (A-150) — tidak ada status "menunggu approval" untuk ISU.
 */
enum MaterialIssueStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Confirmed => 'Dikonfirmasi',
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
            self::Draft => 'text-bg-secondary',
            self::Confirmed => 'text-bg-success',
            self::Cancelled => 'text-bg-danger',
        };
    }
}

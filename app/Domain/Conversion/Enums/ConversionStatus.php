<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Enums;

/**
 * Status CNV — Katalog Status §2.10. **Tidak boleh ditambah.**
 *
 * `draft → pending_approval` (`conversion.submit`, hanya bila ada aturan
 * approval yang cocok), `draft → completed` (`conversion.complete`, tanpa
 * aturan) atau `pending_approval → completed` (`conversion.approve`);
 * `draft`/`pending_approval → cancelled`. Ditolak approver = kembali `draft`
 * (A-153) — tidak ada status "ditolak" untuk CNV.
 */
enum ConversionStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::PendingApproval => 'Menunggu Approval',
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

    /** Katalog §2.10: `draft`/`pending_approval` → `cancelled`. */
    public function isCancellable(): bool
    {
        return $this === self::Draft || $this === self::PendingApproval;
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'text-bg-secondary',
            self::PendingApproval => 'text-bg-warning',
            self::Completed => 'text-bg-success',
            self::Cancelled => 'text-bg-danger',
        };
    }
}

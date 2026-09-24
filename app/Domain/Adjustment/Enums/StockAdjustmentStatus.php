<?php

declare(strict_types=1);

namespace App\Domain\Adjustment\Enums;

/**
 * Status ADJ — Katalog Status §2.12. **Tidak boleh ditambah.**
 *
 * `approved → posted` otomatis: stok bergerak saat itu juga lewat
 * `StockLedger` (P-01). Setelah `posted` koreksi hanya lewat ADJ pembalik
 * (BR-GEN-03, BR-LED-05).
 */
enum StockAdjustmentStatus: string
{
    case Submitted = 'submitted';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Posted = 'posted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Diajukan',
            self::PendingApproval => 'Menunggu Approval',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Posted => 'Diposting',
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
            self::Submitted, self::PendingApproval => 'text-bg-info',
            self::Approved => 'text-bg-primary',
            self::Posted => 'text-bg-success',
            self::Rejected, self::Cancelled => 'text-bg-danger',
        };
    }

    /** Katalog §2.12: dibatalkan hanya sebelum disetujui. */
    public function isCancellable(): bool
    {
        return $this === self::Submitted || $this === self::PendingApproval;
    }
}

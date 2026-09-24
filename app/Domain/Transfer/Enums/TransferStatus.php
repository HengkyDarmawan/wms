<?php

declare(strict_types=1);

namespace App\Domain\Transfer\Enums;

/**
 * Status TRF — Katalog Status §2.7. **Tidak boleh ditambah.**
 *
 * TRF adalah dokumen niat (A-33): `in_progress` begitu PCK di gudang asal
 * dibuat, `completed` saat GRN gudang tujuan selesai. Pembatalan hanya sebelum
 * ada PCK/SJ (guard "belum ada SJ shipped").
 */
enum TransferStatus: string
{
    case Submitted = 'submitted';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Diajukan',
            self::PendingApproval => 'Menunggu Approval',
            self::Approved => 'Disetujui',
            self::InProgress => 'Diproses',
            self::Completed => 'Selesai',
            self::Rejected => 'Ditolak',
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

    /** Katalog §2.7: `submitted`/`pending_approval`/`approved` → `cancelled`. */
    public function isCancellable(): bool
    {
        return in_array($this, [self::Submitted, self::PendingApproval, self::Approved], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Rejected, self::Cancelled], true);
    }

    /** Masih mengikat stok gudang asal atau sedang berjalan. */
    public function isOpen(): bool
    {
        return ! $this->isFinal();
    }

    public function badge(): string
    {
        return match ($this) {
            self::Submitted => 'text-bg-info',
            self::PendingApproval => 'text-bg-warning',
            self::Approved, self::InProgress => 'text-bg-primary',
            self::Completed => 'text-bg-success',
            self::Rejected, self::Cancelled => 'text-bg-danger',
        };
    }
}

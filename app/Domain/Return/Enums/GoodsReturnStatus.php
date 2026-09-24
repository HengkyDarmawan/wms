<?php

declare(strict_types=1);

namespace App\Domain\Return\Enums;

/**
 * Status RET — Katalog Status §2.8. **Tidak boleh ditambah.**
 *
 * `in_progress` saat SJ balik disusun (atau langsung setelah disetujui bila
 * tanpa SJ), `received` saat GRN retur diterima, `sorted` setelah setiap baris
 * dipilah. Pembatalan hanya sebelum ada SJ balik yang berangkat.
 */
enum GoodsReturnStatus: string
{
    case Submitted = 'submitted';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case InProgress = 'in_progress';
    case Received = 'received';
    case Sorted = 'sorted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Diajukan',
            self::PendingApproval => 'Menunggu Approval',
            self::Approved => 'Disetujui',
            self::InProgress => 'Diproses',
            self::Received => 'Diterima',
            self::Sorted => 'Dipilah',
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

    /** Katalog §2.8: `submitted`/`pending_approval`/`approved` → `cancelled`. */
    public function isCancellable(): bool
    {
        return in_array($this, [self::Submitted, self::PendingApproval, self::Approved], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Sorted, self::Rejected, self::Cancelled], true);
    }

    /** Masih memegang jumlah barang yang dikembalikan (menghalangi retur ganda). */
    public function holdsQuantity(): bool
    {
        return ! in_array($this, [self::Rejected, self::Cancelled], true);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Submitted => 'text-bg-info',
            self::PendingApproval => 'text-bg-warning',
            self::Approved, self::InProgress => 'text-bg-primary',
            self::Received => 'text-bg-warning',
            self::Sorted => 'text-bg-success',
            self::Rejected, self::Cancelled => 'text-bg-danger',
        };
    }
}

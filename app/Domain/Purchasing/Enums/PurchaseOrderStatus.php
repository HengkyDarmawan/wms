<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Enums;

/**
 * Status PO — Katalog Status §2.17. Memakai nilai status umum Katalog §1
 * (A-209); **tidak boleh ditambah.**
 *
 * `draft` → `pending_approval` / `approved` (otomatis sesuai aturan) →
 * `partially_fulfilled` / `completed` (otomatis dari GRN); `closed_short`
 * dari sebagian; `rejected`; `cancelled` selama belum ada barang diterima.
 */
enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case PartiallyFulfilled = 'partially_fulfilled';
    case Completed = 'completed';
    case ClosedShort = 'closed_short';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::PendingApproval => 'Menunggu Approval',
            self::Approved => 'Disetujui',
            self::PartiallyFulfilled => 'Sebagian Terpenuhi',
            self::Completed => 'Selesai',
            self::ClosedShort => 'Ditutup dengan Sisa',
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

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'text-bg-secondary',
            self::PendingApproval, self::PartiallyFulfilled => 'text-bg-warning',
            self::Approved => 'text-bg-primary',
            self::Completed => 'text-bg-success',
            self::ClosedShort => 'text-bg-dark',
            self::Rejected, self::Cancelled => 'text-bg-danger',
        };
    }

    /** PO yang memegang jumlah baris PRQ tetapi belum menjadi catatan pemesanan (A-210). */
    public function holdsPrqQty(): bool
    {
        return $this === self::Draft || $this === self::PendingApproval;
    }

    /** Katalog §2.17: batal dari draf, menunggu approval, atau disetujui (belum ada barang). */
    public function isCancellable(): bool
    {
        return in_array($this, [self::Draft, self::PendingApproval, self::Approved], true);
    }

    /** Barang PO ini masih ditunggu (ETA boleh diubah). */
    public function isOpen(): bool
    {
        return $this === self::Approved || $this === self::PartiallyFulfilled;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Enums;

/**
 * Status PRQ — Katalog Status §2.15. **Tidak boleh ditambah.**
 *
 * `draft` (hanya titik pesan ulang) → `submitted` → `pending_approval` /
 * `approved` (otomatis sesuai aturan) → `forwarded` (`pr.order`) →
 * `partially_fulfilled` / `fulfilled` (otomatis dari GRN); `rejected`;
 * `cancelled` dari draf s.d. diteruskan selama belum ada GRN.
 */
enum PurchaseRequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Forwarded = 'forwarded';
    case PartiallyFulfilled = 'partially_fulfilled';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Submitted => 'Diajukan',
            self::PendingApproval => 'Menunggu Approval',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Forwarded => 'Diteruskan',
            self::PartiallyFulfilled => 'Sebagian Terpenuhi',
            self::Fulfilled => 'Dipenuhi',
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
            self::Submitted => 'text-bg-info',
            self::PendingApproval, self::PartiallyFulfilled => 'text-bg-warning',
            self::Approved, self::Forwarded => 'text-bg-primary',
            self::Fulfilled => 'text-bg-success',
            self::Rejected, self::Cancelled => 'text-bg-danger',
        };
    }

    /** Katalog §2.15: `draft`, `submitted`, `pending_approval`, `approved`, `forwarded` → `cancelled`. */
    public function isCancellable(): bool
    {
        return in_array($this, [self::Draft, self::Submitted, self::PendingApproval, self::Approved, self::Forwarded], true);
    }

    /** PRQ yang masih "terbuka" untuk item & gudangnya (BR-REQ-11: jangan dibuat ulang). */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Rejected, self::Fulfilled, self::Cancelled], true);
    }

    /** @return array<int, string> */
    public static function openValues(): array
    {
        return array_values(array_map(fn (self $s) => $s->value, array_filter(self::cases(), fn (self $s) => $s->isOpen())));
    }

    /** Catatan pemesanan boleh ditambah (A-51): disetujui atau sudah berjalan. */
    public function acceptsOrders(): bool
    {
        return in_array($this, [self::Approved, self::Forwarded, self::PartiallyFulfilled], true);
    }

    /** Baris GRN boleh merujuk catatan pemesanan PRQ ini. */
    public function acceptsReceipts(): bool
    {
        return $this === self::Forwarded || $this === self::PartiallyFulfilled;
    }
}

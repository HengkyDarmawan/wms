<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Enums;

/**
 * Status RTV — Katalog Status §2.16. **Tidak boleh ditambah.**
 *
 * Setelah `shipped` barang sudah keluar dari buku besar; RTV tidak bisa
 * dibatalkan lagi (BR-GEN-03).
 */
enum VendorReturnStatus: string
{
    case Submitted = 'submitted';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Diajukan',
            self::PendingApproval => 'Menunggu Approval',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Shipped => 'Dikirim',
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

    /** Katalog §2.16: dibatalkan hanya sebelum barang dikirim. */
    public function isCancellable(): bool
    {
        return in_array($this, [self::Submitted, self::PendingApproval, self::Approved], true);
    }

    /** Masih memegang jumlah baris GRN (menghalangi RTV ganda atas barang yang sama). */
    public function holdsQuantity(): bool
    {
        return ! in_array($this, [self::Rejected, self::Cancelled], true);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Submitted, self::PendingApproval => 'text-bg-info',
            self::Approved => 'text-bg-primary',
            self::Shipped => 'text-bg-warning',
            self::Completed => 'text-bg-success',
            self::Rejected, self::Cancelled => 'text-bg-danger',
        };
    }
}

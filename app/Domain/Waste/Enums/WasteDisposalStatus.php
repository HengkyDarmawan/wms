<?php

declare(strict_types=1);

namespace App\Domain\Waste\Enums;

/**
 * Status WST — Katalog Status §2.14. **Tidak boleh ditambah.**
 *
 * `submitted → pending_approval / approved` (otomatis, sesuai aturan),
 * `pending_approval → approved / rejected` (`waste.approve`),
 * `approved → closed` (`waste.close`, dengan bukti),
 * `submitted / pending_approval → cancelled` (`waste.cancel`).
 */
enum WasteDisposalStatus: string
{
    case Submitted = 'submitted';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Diajukan',
            self::PendingApproval => 'Menunggu Approval',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
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
            self::Submitted => 'text-bg-info',
            self::PendingApproval => 'text-bg-warning',
            self::Approved => 'text-bg-primary',
            self::Rejected, self::Cancelled => 'text-bg-danger',
            self::Closed => 'text-bg-success',
        };
    }

    /** Katalog §2.14: `submitted`/`pending_approval` → `cancelled`. */
    public function isCancellable(): bool
    {
        return $this === self::Submitted || $this === self::PendingApproval;
    }

    /** WST yang masih memegang isi bin Waste (A-159). */
    public function holdsWaste(): bool
    {
        return in_array($this, [self::Submitted, self::PendingApproval, self::Approved], true);
    }

    /** @return array<int, string> */
    public static function holdingValues(): array
    {
        return [self::Submitted->value, self::PendingApproval->value, self::Approved->value];
    }
}

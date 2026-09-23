<?php

declare(strict_types=1);

namespace App\Domain\Request\Enums;

/**
 * Status REQ — Katalog Status §2.1. **Tidak boleh ditambah.**
 *
 * `under_review` hanya dilewati REQ klien: permintaan klien selalu ditinjau
 * staf lebih dulu (BR-REQ-02), sedangkan REQ internal langsung menunggu
 * approval bila gudang sumbernya sudah terisi.
 */
enum MaterialRequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case InProgress = 'in_progress';
    case PartiallyFulfilled = 'partially_fulfilled';
    case Completed = 'completed';
    case ClosedShort = 'closed_short';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Submitted => 'Diajukan',
            self::UnderReview => 'Ditinjau',
            self::PendingApproval => 'Menunggu Persetujuan',
            self::Approved => 'Disetujui',
            self::InProgress => 'Diproses',
            self::PartiallyFulfilled => 'Terpenuhi Sebagian',
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

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::ClosedShort, self::Rejected, self::Cancelled], true);
    }

    /** Baris masih boleh diubah pemohon atau staf. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Submitted, self::UnderReview], true);
    }

    /** Sudah disetujui, jadi reservasi lunaknya sudah ada. */
    public function hasReservations(): bool
    {
        return in_array($this, [self::Approved, self::InProgress, self::PartiallyFulfilled], true);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'text-bg-secondary',
            self::Submitted, self::UnderReview, self::PendingApproval => 'text-bg-warning',
            self::Approved, self::InProgress => 'text-bg-primary',
            self::PartiallyFulfilled => 'text-bg-info',
            self::Completed => 'text-bg-success',
            self::ClosedShort => 'text-bg-dark',
            self::Rejected, self::Cancelled => 'text-bg-danger',
        };
    }
}

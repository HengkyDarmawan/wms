<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Enums;

/** Status PCK — Katalog Status §2.2. **Tidak boleh ditambah.** */
enum PickTaskStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::InProgress => 'Dikerjakan',
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

    /** PCK yang sudah selesai atau batal tidak bisa disentuh lagi. */
    public function isFinal(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'text-bg-secondary',
            self::InProgress => 'text-bg-warning',
            self::Completed => 'text-bg-success',
            self::Cancelled => 'text-bg-danger',
        };
    }
}

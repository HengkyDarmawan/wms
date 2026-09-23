<?php

declare(strict_types=1);

namespace App\Domain\Platform\Enums;

/**
 * Katalog Status & Enum §3 `subscription_status` — A-12, BR-SUB-01.
 */
enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Trial',
            self::Active => 'Aktif',
            self::PastDue => 'Jatuh Tempo',
            self::Suspended => 'Ditangguhkan',
            self::Terminated => 'Diakhiri',
        };
    }

    /** BR-SUB-02: saat ditangguhkan semua aksi tulis ditolak. */
    public function allowsWrite(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::PastDue], true);
    }

    /** BR-SUB-02/03: masih boleh membaca (dengan spanduk) selama belum diakhiri. */
    public function allowsRead(): bool
    {
        return $this !== self::Terminated;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Access\Enums;

/**
 * Status anggota Tim site adalah nilai TURUNAN (A-337), bukan kolom enum:
 * dihitung dari `starts_on`, `ends_on`, dan `ended_at`. Bukan status dokumen,
 * jadi tidak menambah apa pun ke mesin status Katalog §1.
 */
enum SiteTeamStatus: string
{
    case Upcoming = 'upcoming';
    case Active = 'active';
    case EndingSoon = 'ending_soon';
    case Expired = 'expired';
    case Ended = 'ended';

    /** Ambang "akan berakhir" sekaligus tenggat pengingat H-7. */
    public const AMBANG_HARI = 7;

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'Belum mulai',
            self::Active => 'Aktif',
            self::EndingSoon => 'Akan berakhir',
            self::Expired => 'Berakhir',
            self::Ended => 'Diakhiri',
        };
    }

    /** Warna badge NexaDash. */
    public function badge(): string
    {
        return match ($this) {
            self::Upcoming => 'info',
            self::Active => 'success',
            self::EndingSoon => 'warning',
            self::Expired, self::Ended => 'secondary',
        };
    }

    /** Masih memberi akses hari ini. */
    public function isRunning(): bool
    {
        return $this === self::Active || $this === self::EndingSoon;
    }
}

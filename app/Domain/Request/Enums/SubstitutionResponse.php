<?php

declare(strict_types=1);

namespace App\Domain\Request\Enums;

/**
 * A-55 dan BR-REQ-13 — tanggapan klien atas penggantian item.
 *
 * `expired` bukan kegagalan: diam sampai tenggat dianggap setuju, supaya REQ
 * tidak tertahan menunggu klien yang tidak membuka portal.
 */
enum SubstitutionResponse: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Disetujui klien',
            self::Rejected => 'Ditolak klien',
            self::Expired => 'Lewat tenggat (dianggap setuju)',
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

    public function isAgreement(): bool
    {
        return $this !== self::Rejected;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Platform\Enums;

/**
 * Status company di database pusat (ERD 08a). Bukan status langganan.
 */
enum CompanyStatus: string
{
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Suspended = 'suspended';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::Provisioning => 'Disiapkan',
            self::Active => 'Aktif',
            self::Suspended => 'Ditangguhkan',
            self::Terminated => 'Diakhiri',
        };
    }
}

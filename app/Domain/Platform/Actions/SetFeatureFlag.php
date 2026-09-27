<?php

declare(strict_types=1);

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Exceptions\PlatformRuleException;
use App\Domain\Platform\Models\Company;
use App\Domain\Platform\Models\FeatureFlag;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\Platform\Support\PlatformAudit;

/**
 * Lapis 1 P-08: Super Admin menyalakan fitur per company (A-183). WhatsApp
 * dipakai sejak Fase 2a (31-whatsapp, BR-WA-02); fitur lain masih disimpan saja.
 */
class SetFeatureFlag
{
    /** @var array<string, string> kunci => label */
    public const KEYS = [
        'whatsapp' => 'Notifikasi & approval WhatsApp (Fase 2a)',
        'offline_sync' => 'PWA offline [F2]',
        'rfid' => 'RFID [F3]',
    ];

    public function handle(Company $company, string $key, bool $enabled, PlatformUser $actor): FeatureFlag
    {
        if (! array_key_exists($key, self::KEYS)) {
            throw PlatformRuleException::rule('BR-GEN-10', 'Fitur tidak dikenal.');
        }

        $flag = FeatureFlag::query()->updateOrCreate(
            ['company_id' => $company->getTenantKey(), 'key' => $key],
            ['enabled' => $enabled],
        );

        PlatformAudit::record($enabled ? 'Fitur dinyalakan' : 'Fitur dimatikan', $company, $actor, ['key' => $key]);

        return $flag;
    }
}

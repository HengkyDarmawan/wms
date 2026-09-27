<?php

declare(strict_types=1);

namespace App\Domain\WhatsApp\Support;

use App\Domain\Master\Models\CompanySetting;
use App\Domain\Notification\Support\NotificationEvents;

/**
 * Lapis 2 (Admin Company, BR-WA-02, A-276, A-280): kejadian mana yang boleh
 * memakai WhatsApp dan caranya — `instant` (langsung) atau `digest`
 * (ringkasan harian) — serta balasan konfirmasi setelah tombol ditekan.
 * Disimpan di `company_settings` (`wa_events`, `wa_confirm_reply`).
 */
final class WhatsAppSettings
{
    public const INSTANT = 'instant';

    public const DIGEST = 'digest';

    /** Kejadian yang tidak pernah lewat WhatsApp (mencegah putaran kuota, A-278). */
    public const TIDAK_BOLEH = [WhatsAppChannel::EVENT_QUOTA];

    /** @return array<string, string> kejadian ⇒ instant|digest */
    public static function events(): array
    {
        $isi = CompanySetting::get('wa_events', []);

        return collect(is_array($isi) ? $isi : [])
            ->filter(fn ($mode, $event) => isset(NotificationEvents::ALL[$event])
                && ! in_array($event, self::TIDAK_BOLEH, true)
                && in_array($mode, [self::INSTANT, self::DIGEST], true))
            ->all();
    }

    public static function mode(string $event): ?string
    {
        return self::events()[$event] ?? null;
    }

    public static function confirmReply(): bool
    {
        return (bool) CompanySetting::get('wa_confirm_reply', true);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\WhatsApp\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\CompanySetting;
use App\Domain\Notification\Support\NotificationEvents;
use App\Domain\WhatsApp\Support\WhatsAppSettings;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `company_setting.manage` — lapis 2 WhatsApp (BR-WA-02, A-276,
 * A-280): kejadian yang boleh lewat WhatsApp beserta modenya, dan balasan
 * konfirmasi setelah tombol. Hanya kunci yang berubah ditulis (pola A-230).
 */
class SaveWhatsAppSettings
{
    /** @param  array<string, string>  $events  kejadian ⇒ off|instant|digest */
    public function handle(array $events, bool $confirmReply, ?User $actor = null): void
    {
        $bersih = [];

        foreach ($events as $kejadian => $mode) {
            if (isset(NotificationEvents::ALL[$kejadian]) && ! in_array($kejadian, WhatsAppSettings::TIDAK_BOLEH, true)
                && in_array($mode, [WhatsAppSettings::INSTANT, WhatsAppSettings::DIGEST], true)) {
                $bersih[$kejadian] = $mode;
            }
        }

        ksort($bersih);

        DB::connection('tenant')->transaction(function () use ($bersih, $confirmReply): void {
            if (WhatsAppSettings::events() !== $bersih) {
                CompanySetting::put('wa_events', $bersih);
            }

            if (WhatsAppSettings::confirmReply() !== $confirmReply) {
                CompanySetting::put('wa_confirm_reply', $confirmReply);
            }
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\WhatsApp\Support;

use App\Domain\Access\Models\User;
use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Models\NotificationPreference;
use App\Domain\WhatsApp\Transport\WhatsAppNotSent;
use Illuminate\Support\Facades\Log;

/**
 * Notifikasi WhatsApp (31-whatsapp §4, BR-WA-02, A-276, A-280).
 *
 * Tiga lapis harus terbuka: fitur `whatsapp` company (Super Admin) → kejadian
 * diizinkan Admin Company (mode langsung/ringkasan) → preferensi user
 * (bawaan ikut izin company; user bisa mematikan). Penerima harus punya nomor
 * terverifikasi (BR-WA-01). Galat kirim hanya dicatat — tidak pernah
 * membatalkan aksi bisnis, sama seperti email.
 */
class WhatsAppNotifier
{
    public function __construct(private readonly WhatsAppChannel $channel) {}

    /** Mode kejadian bila WhatsApp aktif untuk company; null = tidak lewat WhatsApp. */
    public function modeFor(string $event): ?string
    {
        if (in_array($event, WhatsAppSettings::TIDAK_BOLEH, true) || ! $this->channel->enabled()) {
            return null;
        }

        return WhatsAppSettings::mode($event);
    }

    public function wants(User $user, string $event): bool
    {
        $pref = NotificationPreference::query()->where('user_id', $user->id)->where('event_key', $event)->first();

        // Bawaan: ikut izin company (A-276); baris preferensi yang tersimpan menang.
        return ($pref?->whatsapp ?? true) && WhatsAppRecipients::number($user) !== null;
    }

    /** Kirim langsung `wms_notifikasi`; true bila terkirim. */
    public function instant(User $user, string $event, string $title, ?string $body, ?string $url, ?string $documentType = null, ?int $documentId = null): bool
    {
        if ($this->modeFor($event) !== WhatsAppSettings::INSTANT || ! $this->wants($user, $event)) {
            return false;
        }

        try {
            $this->channel->template((string) WhatsAppRecipients::number($user), 'notification',
                [$this->channel->companyName(), $title, $body ?? '-'],
                [['type' => 'url', 'value' => $this->channel->linkSuffix($url)]],
                ['event' => $event, 'user_id' => $user->id, 'document_type' => $documentType, 'document_id' => $documentId]);
        } catch (WhatsAppNotSent $e) {
            Log::warning('WhatsApp notifikasi gagal: '.$e->getMessage(), ['event' => $event, 'user' => $user->id]);

            return false;
        }

        Notification::create([
            'user_id' => $user->id, 'type' => $event, 'channel' => 'whatsapp',
            'title' => mb_substr($title, 0, 150), 'body' => $body === null ? null : mb_substr($body, 0, 500),
            'url' => $url === null ? null : mb_substr($url, 0, 255),
            'document_type' => $documentType, 'document_id' => $documentId, 'sent_at' => now(),
        ]);

        return true;
    }

    /**
     * Ringkasan harian (A-280): per user, notifikasi lonceng kejadian bermode
     * `digest` sejak ringkasan terakhir (maks 24 jam ke belakang) digabung
     * menjadi satu `wms_ringkasan`. Dipanggil `notifications:daily`.
     *
     * @return int jumlah pesan terkirim
     */
    public function digest(): int
    {
        if (! $this->channel->enabled()) {
            return 0;
        }

        $kejadian = array_keys(array_filter(WhatsAppSettings::events(), fn ($m) => $m === WhatsAppSettings::DIGEST));

        if ($kejadian === []) {
            return 0;
        }

        $terkirim = 0;

        foreach (User::query()->where('is_active', true)->whereNotNull('phone_verified_at')->get() as $user) {
            $mulai = max($user->wa_digest_sent_at?->getTimestamp() ?? 0, now()->subDay()->getTimestamp());
            $boleh = array_values(array_filter($kejadian, fn ($e) => $this->wants($user, $e)));

            if ($boleh === []) {
                continue;
            }

            $isi = Notification::query()->inApp()->where('user_id', $user->id)->whereIn('type', $boleh)
                ->where('created_at', '>', date('Y-m-d H:i:s', $mulai))->latest()->get(['title']);

            if ($isi->isEmpty()) {
                continue;
            }

            try {
                $this->channel->template((string) WhatsAppRecipients::number($user), 'digest',
                    [$this->channel->companyName(), (string) $isi->count(), $isi->take(3)->pluck('title')->implode('; ').($isi->count() > 3 ? '; …' : '')],
                    [['type' => 'url', 'value' => $this->channel->linkSuffix(route('notifications.index', absolute: false))]],
                    ['event' => 'digest', 'user_id' => $user->id, 'jumlah' => $isi->count()]);
            } catch (WhatsAppNotSent $e) {
                Log::warning('WhatsApp ringkasan gagal: '.$e->getMessage(), ['user' => $user->id]);

                continue;
            }

            $user->forceFill(['wa_digest_sent_at' => now()])->save();
            $terkirim++;
        }

        return $terkirim;
    }
}

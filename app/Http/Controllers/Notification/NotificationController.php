<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notification;

use App\Domain\Approval\Models\ApprovalStep;
use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Models\NotificationPreference;
use App\Domain\Notification\Support\NotificationEvents;
use App\Domain\WhatsApp\Support\WhatsAppChannel;
use App\Domain\WhatsApp\Support\WhatsAppRecipients;
use App\Domain\WhatsApp\Support\WhatsAppSettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lonceng notifikasi & preferensi kanal (Blueprint §10, 27-pendukung-f1 §2).
 * Setiap user hanya melihat notifikasinya sendiri; tanda baca lewat POST.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notification.index', [
            'notifications' => Notification::query()->inApp()->where('user_id', $request->user()->id)
                ->latest()->paginate(25),
        ]);
    }

    /** Tandai dibaca lalu buka tautannya. */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $n = Notification::query()->inApp()->where('user_id', $request->user()->id)->findOrFail($notification);

        if ($n->read_at === null) {
            $n->forceFill(['read_at' => now()])->save();
        }

        return $n->url !== null && str_starts_with($n->url, '/') ? redirect($n->url) : redirect()->route('notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        Notification::query()->inApp()->unread()->where('user_id', $request->user()->id)->update(['read_at' => now()]);

        return back()->with('status', __('Semua notifikasi ditandai dibaca.'));
    }

    public function preferences(Request $request): View
    {
        return view('notification.preferences', [
            'events' => NotificationEvents::forUser($request->user()),
            'prefs' => NotificationPreference::query()->where('user_id', $request->user()->id)->get()->keyBy('event_key'),
            'waEvents' => $this->waEvents(),
            'waNomor' => WhatsAppRecipients::number($request->user()) !== null,
        ]);
    }

    /**
     * Kejadian yang boleh lewat WhatsApp untuk company ini (BR-WA-02): pilihan
     * Admin Company, ditambah tugas approval bila ada lapis *Web & WhatsApp*.
     *
     * @return array<string, string> kejadian ⇒ instant|digest|buttons
     */
    private function waEvents(): array
    {
        if (! app(WhatsAppChannel::class)->enabled()) {
            return [];
        }

        $hasil = WhatsAppSettings::events();

        if (! isset($hasil['approval.task_assigned']) && ApprovalStep::query()->where('channel', 'both')->exists()) {
            $hasil['approval.task_assigned'] = 'buttons';
        }

        return $hasil;
    }

    public function savePreferences(Request $request): RedirectResponse
    {
        $isian = (array) $request->input('prefs', []);
        $wa = $this->waEvents();
        $lama = NotificationPreference::query()->where('user_id', $request->user()->id)->pluck('whatsapp', 'event_key');

        foreach (array_keys(NotificationEvents::forUser($request->user())) as $kunci) {
            NotificationPreference::query()->updateOrInsert(
                ['user_id' => $request->user()->id, 'event_key' => $kunci],
                [
                    'in_app' => ! empty($isian[$kunci]['in_app']),
                    'email' => ! empty($isian[$kunci]['email']),
                    // A-276: kolom WhatsApp hanya untuk kejadian yang diizinkan company;
                    // kejadian lain menyimpan pilihan lama (bawaan menyala).
                    'whatsapp' => isset($wa[$kunci]) ? ! empty($isian[$kunci]['whatsapp']) : (bool) ($lama[$kunci] ?? true),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        return back()->with('status', __('Preferensi notifikasi tersimpan.'));
    }
}

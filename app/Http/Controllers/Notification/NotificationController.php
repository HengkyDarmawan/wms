<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notification;

use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Models\NotificationPreference;
use App\Domain\Notification\Support\NotificationEvents;
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
        ]);
    }

    public function savePreferences(Request $request): RedirectResponse
    {
        $isian = (array) $request->input('prefs', []);

        foreach (array_keys(NotificationEvents::forUser($request->user())) as $kunci) {
            NotificationPreference::query()->updateOrInsert(
                ['user_id' => $request->user()->id, 'event_key' => $kunci],
                [
                    'in_app' => ! empty($isian[$kunci]['in_app']),
                    'email' => ! empty($isian[$kunci]['email']),
                    'whatsapp' => false,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        return back()->with('status', __('Preferensi notifikasi tersimpan.'));
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Notification\Support;

use App\Domain\Access\Models\User;
use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Models\NotificationPreference;
use App\Domain\Notification\Notifications\EventMail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pengirim notifikasi Fase 1 (Blueprint §10, A-189): baris lonceng `in_app`
 * dan, bila preferensi user mengizinkan, email. WhatsApp `[F2]`.
 *
 * - Penerima tidak aktif dan pelaku kejadian itu sendiri dilewati.
 * - Notifikasi in-app yang sama (kejadian + dokumen) yang belum dibaca tidak
 *   digandakan — penting untuk pengingat harian.
 * - URL disimpan relatif supaya benar di subdomain company mana pun.
 * - Email ditunda sampai commit (`DB::afterCommit`) dan galatnya hanya dicatat.
 */
class Notifier
{
    /**
     * @param  iterable<int, User>|User  $users
     * @param  array<string, mixed>  $data
     */
    public function send(iterable|User $users, string $event, string $title, ?string $body = null, ?string $url = null, ?string $documentType = null, ?int $documentId = null, array $data = [], ?User $actor = null): int
    {
        $penerima = collect($users instanceof User ? [$users] : $users)
            ->filter(fn ($u) => $u instanceof User && $u->is_active && $u->id !== $actor?->id)
            ->unique('id');

        if ($penerima->isEmpty()) {
            return 0;
        }

        $pref = NotificationPreference::query()->where('event_key', $event)
            ->whereIn('user_id', $penerima->pluck('id'))->get()->keyBy('user_id');

        $jumlah = 0;

        foreach ($penerima as $user) {
            /** @var NotificationPreference|null $p */
            $p = $pref->get($user->id);
            $inApp = $p?->in_app ?? true;
            $email = $p?->email ?? NotificationEvents::emailByDefault($event);

            if ($inApp && ! $this->sudahAda($user, $event, $documentType, $documentId)) {
                $this->simpan($user, 'in_app', $event, $title, $body, $url, $documentType, $documentId, $data);
                $jumlah++;
            }

            if ($email && $user->email) {
                // Email dikirim setelah transaksi pemanggil commit dan galat SMTP tidak
                // pernah membatalkan aksi bisnis (mis. keputusan approval).
                DB::afterCommit(function () use ($user, $event, $title, $body, $url, $documentType, $documentId, $data) {
                    try {
                        $user->notify(new EventMail($title, $body, $url));
                        $this->simpan($user, 'email', $event, $title, $body, $url, $documentType, $documentId, $data, now());
                    } catch (\Throwable $e) {
                        Log::warning('Email notifikasi gagal: '.$e->getMessage(), ['event' => $event, 'user' => $user->id]);
                    }
                });
            }
        }

        return $jumlah;
    }

    /**
     * User aktif yang memegang izin dan (bila diberikan) mencakup gudang/proyek.
     *
     * @return Collection<int, User>
     */
    public function recipients(string $permission, ?int $warehouseId = null, ?int $projectId = null): Collection
    {
        return User::query()->where('is_active', true)->get()
            ->filter(fn (User $u) => $u->hasPermission($permission)
                && ($warehouseId === null || $u->canAccessWarehouse($warehouseId))
                && ($projectId === null || $u->canAccessProject($projectId)))
            ->values();
    }

    private function sudahAda(User $user, string $event, ?string $documentType, ?int $documentId): bool
    {
        if ($documentType === null) {
            return false;
        }

        return Notification::query()->inApp()->unread()->where('user_id', $user->id)->where('type', $event)
            ->where('document_type', $documentType)->where('document_id', $documentId)->exists();
    }

    /** @param  array<string, mixed>  $data */
    private function simpan(User $user, string $channel, string $event, string $title, ?string $body, ?string $url, ?string $documentType, ?int $documentId, array $data, $sentAt = null): void
    {
        Notification::create([
            'user_id' => $user->id,
            'type' => $event,
            'channel' => $channel,
            'title' => mb_substr($title, 0, 150),
            'body' => $body === null ? null : mb_substr($body, 0, 500),
            'url' => $url === null ? null : mb_substr($url, 0, 255),
            'data' => $data ?: null,
            'document_type' => $documentType,
            'document_id' => $documentId,
            'sent_at' => $sentAt ?? now(),
        ]);
    }
}

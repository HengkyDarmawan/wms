<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Access\Models\UserInvitation;
use App\Domain\Access\Notifications\UserInvitationNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Permission: `user.invite`.
 *
 * Membuat undangan baru untuk user (Blueprint §13). Token mentah hanya dikirim
 * lewat email; database menyimpan hash-nya. Masa berlaku dari config/access.php.
 */
class InviteUser
{
    public function handle(User $user, ?User $actor = null): UserInvitation
    {
        // Undangan lama yang belum dipakai dibatalkan agar hanya satu token berlaku.
        $previous = UserInvitation::query()
            ->where('user_id', $user->id)
            ->pending()
            ->get();

        $sentCount = 1;

        foreach ($previous as $old) {
            $sentCount = max($sentCount, $old->sent_count + 1);
            $old->delete();
        }

        $plainToken = Str::random(40);

        $invitation = UserInvitation::create([
            'user_id' => $user->id,
            'token' => UserInvitation::hashToken($plainToken),
            // A-333: disimpan terenkripsi supaya tautannya bisa ditampilkan &
            // disalin lagi selama undangan belum dipakai.
            'token_plain' => $plainToken,
            'expires_at' => now()->addHours((int) config('access.invitation.valid_hours', 72)),
            'sent_count' => $sentCount,
        ]);

        // Email boleh gagal (mailer mati / salah setel) tanpa menggagalkan
        // pembuatan undangan: tautannya tetap bisa diserahkan lewat kartu
        // Undangan di detail pengguna.
        try {
            $user->notify(new UserInvitationNotification($plainToken, $invitation->expires_at));
        } catch (\Throwable $e) {
            Log::warning('Email undangan gagal dikirim: '.$e->getMessage(), ['user' => $user->id]);
        }

        activity('access')
            ->performedOn($user)
            ->causedBy($actor)
            ->withProperties(['expires_at' => $invitation->expires_at->toIso8601String()])
            ->log('Undangan dikirim');

        // Token mentah dikembalikan agar pemanggil (mis. uji) bisa memakainya.
        $invitation->plainToken = $plainToken;

        return $invitation;
    }
}

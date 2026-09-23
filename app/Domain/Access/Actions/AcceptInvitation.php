<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\User;
use App\Domain\Access\Models\UserInvitation;
use Illuminate\Support\Facades\DB;

/**
 * User menerima undangan dan mengatur password pertama (10-access §4).
 * Undangan kedaluwarsa (default 72 jam) ditolak dan bisa dikirim ulang Admin.
 */
class AcceptInvitation
{
    public function __construct(private readonly ChangePassword $changePassword) {}

    public static function findUsable(string $plainToken): ?UserInvitation
    {
        return UserInvitation::query()
            ->where('token', UserInvitation::hashToken($plainToken))
            ->pending()
            ->first();
    }

    public function handle(string $plainToken, string $password, ?string $name = null, ?string $phone = null): User
    {
        $invitation = self::findUsable($plainToken);

        if ($invitation === null) {
            throw new AccessRuleException('Undangan tidak ditemukan atau sudah dipakai.');
        }

        if ($invitation->isExpired()) {
            throw new AccessRuleException('Undangan sudah kedaluwarsa. Minta Admin mengirim ulang.');
        }

        $user = $invitation->user;

        if ($user === null || ! $user->is_active) {
            throw new AccessRuleException('Akun tidak aktif. Hubungi Admin Company.');
        }

        DB::transaction(function () use ($invitation, $user, $password, $name, $phone): void {
            if ($name !== null && $name !== '') {
                $user->name = $name;
            }

            if ($phone !== null && $phone !== '') {
                $user->phone = $phone;
            }

            $user->email_verified_at = now();
            $user->save();

            $this->changePassword->handle($user, $password, requireCurrent: false);

            $invitation->forceFill(['accepted_at' => now()])->save();
        });

        activity('access')->performedOn($user)->log('Undangan diterima');

        return $user->refresh();
    }
}

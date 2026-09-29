<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Access\Models\UserInvitation;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `user.create` / `user.reset_password`.
 *
 * Jalur "buatkan saja passwordnya" (A-334): sebagian orang tidak perlu alur
 * undangan — Admin menyerahkan password langsung. Akunnya langsung aktif dan
 * bisa masuk, undangan yang masih tertunda dibatalkan supaya tidak ada dua
 * jalan masuk yang berlaku bersamaan.
 *
 * Kebijakan password tetap BR-ACC-06 karena pemeriksaannya ada di
 * {@see ChangePassword}: panjang minimum dan tidak sama dengan yang terakhir.
 */
class SetInitialPassword
{
    public function __construct(private readonly ChangePassword $changePassword) {}

    public function handle(User $user, string $password, ?User $actor = null): User
    {
        $this->changePassword->handle($user, $password, requireCurrent: false, revokeOtherSessions: true);

        DB::transaction(function () use ($user): void {
            $user->forceFill([
                'is_active' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            UserInvitation::query()->where('user_id', $user->id)->pending()->delete();
        });

        activity('access')
            ->performedOn($user)
            ->causedBy($actor)
            ->log('Password awal dibuatkan Admin');

        return $user->refresh();
    }
}

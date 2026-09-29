<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\User;
use App\Domain\Access\Models\UserInvitation;
use App\Domain\Access\Support\CompanyAdminGuard;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `user.create` / `user.reset_password`.
 *
 * Jalur "buatkan saja passwordnya" (A-334): sebagian orang tidak perlu alur
 * undangan — Admin menyerahkan password langsung. Akunnya langsung bisa masuk,
 * undangan yang masih tertunda dibatalkan supaya tidak ada dua jalan masuk
 * yang berlaku bersamaan.
 *
 * Hanya untuk akun yang **belum pernah bisa masuk** (belum menerima undangan
 * atau dibuatkan password) dan masih aktif; akun yang sudah dipakai memakai
 * Reset password. Tanpa batas itu, pemegang `user.reset_password` bisa
 * mengetahui password Admin Company, atau menghidupkan akun yang sudah
 * dinonaktifkan tanpa lewat Aktifkan kembali (BR-ACC-01).
 *
 * Kebijakan password tetap BR-ACC-06 karena pemeriksaannya ada di
 * {@see ChangePassword}: panjang minimum dan tidak sama dengan yang terakhir.
 */
class SetInitialPassword
{
    public function __construct(private readonly ChangePassword $changePassword) {}

    /** @param  User|null  $actor  null = Super Admin saat serah terima company baru (A-335) */
    public function handle(User $user, string $password, ?User $actor = null): User
    {
        if ($user->password !== null) {
            throw AccessRuleException::rule('A-334', 'Pengguna ini sudah bisa masuk; gunakan Reset password.');
        }

        if (! $user->is_active) {
            throw AccessRuleException::rule('BR-ACC-01', 'Akun nonaktif. Aktifkan kembali dulu.');
        }

        if ($actor !== null && $user->hasRoleCode(CompanyAdminGuard::ROLE_CODE) && ! $actor->hasRoleCode(CompanyAdminGuard::ROLE_CODE)) {
            throw AccessRuleException::rule('BR-ACC-02', 'Password Admin Company hanya bisa dibuatkan oleh Admin Company.');
        }

        DB::transaction(function () use ($user, $password): void {
            $this->changePassword->handle($user, $password, requireCurrent: false, revokeOtherSessions: true);

            $user->forceFill(['email_verified_at' => now()])->save();

            UserInvitation::query()->where('user_id', $user->id)->pending()->delete();
        });

        activity('access')
            ->performedOn($user)
            ->causedBy($actor)
            ->log('Password awal dibuatkan Admin');

        return $user->refresh();
    }
}

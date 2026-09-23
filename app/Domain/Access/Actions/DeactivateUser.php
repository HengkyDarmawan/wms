<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\CompanyAdminGuard;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `user.deactivate`.
 *
 * P-03: user tidak pernah dihapus, hanya dinonaktifkan — data historis tetap utuh.
 * BR-GEN-11: alasan wajib, keterangan opsional.
 * BR-ACC-02: Admin Company aktif terakhir tidak boleh dinonaktifkan.
 */
class DeactivateUser
{
    public function __construct(private readonly CompanyAdminGuard $adminGuard) {}

    public function handle(User $user, string $reason, ?string $notes = null, ?User $actor = null): User
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw AccessRuleException::rule('BR-GEN-11', 'Alasan wajib diisi.');
        }

        $this->adminGuard->ensureNotLastAdmin($user, 'menonaktifkan user ini');

        DB::transaction(function () use ($user): void {
            $user->forceFill([
                'is_active' => false,
                'remember_token' => null,
            ])->save();

            // Cabut sesi & perangkat aktif (BR-ACC-06).
            $user->devices()->update(['is_active' => false]);
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });

        activity('access')
            ->performedOn($user)
            ->causedBy($actor)
            ->withProperties(['reason' => $reason, 'notes' => $notes])
            ->log('User dinonaktifkan');

        return $user->refresh();
    }
}

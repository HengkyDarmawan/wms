<?php

declare(strict_types=1);

namespace App\Domain\Access\Support;

use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\User;

/**
 * BR-ACC-02: company harus selalu punya minimal satu Admin Company yang aktif.
 */
class CompanyAdminGuard
{
    public const ROLE_CODE = 'company_admin';

    /** Jumlah user aktif yang memegang role Admin Company. */
    public function activeAdminCount(): int
    {
        $role = Role::findByCode(self::ROLE_CODE);

        if ($role === null) {
            return 0;
        }

        return RoleAssignment::query()
            ->valid()
            ->where('role_id', $role->id)
            ->whereHas('user', fn ($q) => $q->where('is_active', true))
            ->distinct('user_id')
            ->count('user_id');
    }

    public function isCompanyAdmin(User $user): bool
    {
        return $user->hasRoleCode(self::ROLE_CODE);
    }

    /**
     * Menolak aksi bila user adalah Admin Company aktif terakhir.
     */
    public function ensureNotLastAdmin(?User $user, string $action): void
    {
        if ($user === null || ! $this->isCompanyAdmin($user) || ! $user->is_active) {
            return;
        }

        if ($this->activeAdminCount() <= 1) {
            throw AccessRuleException::rule(
                'BR-ACC-02',
                'Tidak bisa '.$action.': company harus punya minimal satu Admin Company yang aktif.',
            );
        }
    }
}

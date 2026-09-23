<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\OrgUnit;
use App\Domain\Access\Models\User;

/**
 * Permission: `org.manage`.
 *
 * P-03: unit tidak pernah dihapus, hanya dinonaktifkan, dan hanya bila sudah
 * tidak dipakai user aktif maupun sub-unit aktif.
 */
class DeactivateOrgUnit
{
    public function handle(OrgUnit $unit, ?User $actor = null): OrgUnit
    {
        $userAktif = User::query()->where('org_unit_id', $unit->id)->where('is_active', true)->count();

        if ($userAktif > 0) {
            throw new AccessRuleException(
                'Unit masih dipakai '.$userAktif.' user aktif. Pindahkan mereka lebih dulu.',
            );
        }

        $anakAktif = OrgUnit::query()->where('parent_id', $unit->id)->where('is_active', true)->count();

        if ($anakAktif > 0) {
            throw new AccessRuleException(
                'Unit masih punya '.$anakAktif.' sub-unit aktif. Nonaktifkan sub-unit lebih dulu.',
            );
        }

        $unit->forceFill(['is_active' => false])->save();
        $unit->positions()->update(['is_active' => false]);

        activity('access')->performedOn($unit)->causedBy($actor)->log('Unit organisasi dinonaktifkan');

        return $unit->refresh();
    }

    public function reactivate(OrgUnit $unit, ?User $actor = null): OrgUnit
    {
        $unit->forceFill(['is_active' => true])->save();

        activity('access')->performedOn($unit)->causedBy($actor)->log('Unit organisasi diaktifkan kembali');

        return $unit->refresh();
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\ProjectTeamMember;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\SiteTeam;
use App\Domain\Master\Models\Project;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `role.assign`.
 *
 * Mengangkat **penugasan bertanggal lama** menjadi anggota Tim site (A-343).
 * Sebelum A-337 tanggal diketik langsung di form pengguna; baris seperti itu
 * tetap ditegakkan (P-03) tetapi tidak terlihat di tab Tim site. Aksi ini
 * memindahkannya ke tempat yang benar tanpa mengubah tanggal atau cakupannya —
 * hanya baris `role_assignments` itu sendiri yang diadopsi, bukan seluruh
 * Gudang Site proyek.
 */
class AdoptAssignmentIntoSiteTeam
{
    public function handle(RoleAssignment $assignment, ?User $actor = null): ProjectTeamMember
    {
        if ($assignment->project_team_member_id !== null) {
            throw AccessRuleException::rule('BR-GEN-09', 'Penugasan ini sudah bagian dari Tim site.');
        }

        if ($assignment->valid_until === null) {
            throw AccessRuleException::rule(
                'BR-GEN-11',
                'Penugasan ini tidak punya tanggal selesai, jadi tidak perlu dijadikan Tim site.',
            );
        }

        $role = $assignment->role
            ?? throw AccessRuleException::rule('BR-GEN-09', 'Peran penugasan ini tidak ditemukan.');

        if (SiteTeam::scopeFor($role) === null) {
            throw AccessRuleException::rule(
                'BR-GEN-09',
                'Peran "'.$role->name.'" tidak bisa ditempatkan di site.',
            );
        }

        $project = $this->project($assignment)
            ?? throw AccessRuleException::rule(
                'BR-GEN-09',
                'Cakupan penugasan ini bukan proyek atau Gudang Site, jadi tidak bisa dijadikan Tim site.',
            );

        $member = DB::transaction(function () use ($assignment, $project, $role, $actor): ProjectTeamMember {
            $member = ProjectTeamMember::create([
                'project_id' => $project->id,
                'user_id' => $assignment->user_id,
                'role_id' => $role->id,
                'starts_on' => ($assignment->valid_from ?? $assignment->created_at ?? now())->toDateString(),
                'ends_on' => $assignment->valid_until->toDateString(),
                'grants_access' => true,
                'created_by' => $actor?->id,
            ]);

            $assignment->forceFill(['project_team_member_id' => $member->id])->save();

            return $member;
        });

        activity('access')
            ->performedOn($assignment->user)
            ->causedBy($actor)
            ->withProperties(['project' => $project->code, 'role' => $role->code])
            ->log('Penugasan bertanggal dijadikan Tim site');

        return $member->refresh();
    }

    /** Proyek di balik cakupan: proyek langsung, atau proyek pemilik Gudang Site. */
    private function project(RoleAssignment $assignment): ?Project
    {
        $scopeId = $assignment->scope_id === null ? null : (int) $assignment->scope_id;

        if ($scopeId === null) {
            return null;
        }

        if ($assignment->scope_type === ScopeType::Project) {
            return Project::query()->find($scopeId);
        }

        if ($assignment->scope_type !== ScopeType::Warehouse) {
            return null;
        }

        $gudang = Warehouse::withoutGlobalScopes()->with('type')->find($scopeId);

        return $gudang !== null && $gudang->isSite() && $gudang->project_id !== null
            ? Project::query()->find((int) $gudang->project_id)
            : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\ProjectTeamMember;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\SiteTeam;
use App\Domain\Master\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `role.assign`.
 *
 * Menempatkan seseorang di site proyek untuk satu periode (A-337). Inilah satu-
 * satunya jalan membuat penugasan role bertanggal; form pengguna tidak punya
 * isian tanggal lagi.
 *
 * Penugasan tetap yang kebetulan berkunci sama (user × role × cakupan) **tidak
 * disentuh**: `role_assignments` unik pada kunci itu, jadi menimpanya berarti
 * mengubah akses permanen orang itu menjadi berbatas waktu — dan mengakhiri
 * keanggotaan akan memutusnya. Keanggotaan seperti itu disimpan dengan
 * `grants_access = false` sebagai catatan saja.
 */
class AddProjectTeamMember
{
    public function __construct(
        private readonly AssignRole $assignRole,
        private readonly SiteTeam $siteTeam,
    ) {}

    public function handle(
        Project $project,
        User $user,
        Role $role,
        string $startsOn,
        string $endsOn,
        ?User $actor = null,
    ): ProjectTeamMember {
        $scope = SiteTeam::scopeFor($role)
            ?? throw AccessRuleException::rule('BR-GEN-09', 'Peran "'.$role->name.'" tidak bisa ditempatkan di site.');

        if (! $user->is_active) {
            throw AccessRuleException::rule('BR-ACC-01', 'Pengguna nonaktif tidak bisa ditambahkan ke Tim site.');
        }

        $mulai = $this->tanggal($startsOn, 'Tanggal mulai');
        $selesai = $this->tanggal($endsOn, 'Tanggal selesai');

        if ($selesai->lessThan($mulai)) {
            throw AccessRuleException::rule('BR-GEN-11', 'Tanggal selesai tidak boleh sebelum tanggal mulai.');
        }

        $targets = $this->siteTeam->targets($project, $role);

        if ($targets === []) {
            throw AccessRuleException::rule(
                'BR-GEN-09',
                'Proyek ini belum punya Gudang Site, jadi peran gudang belum bisa ditempatkan di sini.',
            );
        }

        $kembar = ProjectTeamMember::query()->running()
            ->where('project_id', $project->id)->where('user_id', $user->id)->where('role_id', $role->id)
            ->exists();

        if ($kembar) {
            throw AccessRuleException::rule(
                'BR-GEN-09',
                $user->name.' sudah menjadi anggota Tim site proyek ini dengan peran itu. Perpanjang saja periodenya.',
            );
        }

        $member = DB::transaction(function () use ($project, $user, $role, $scope, $mulai, $selesai, $targets, $actor): ProjectTeamMember {
            $member = ProjectTeamMember::create([
                'project_id' => $project->id,
                'user_id' => $user->id,
                'role_id' => $role->id,
                'starts_on' => $mulai->toDateString(),
                'ends_on' => $selesai->toDateString(),
                'grants_access' => false,
                'created_by' => $actor?->id,
            ]);

            $memberi = false;

            foreach ($targets as $scopeId) {
                if ($this->penugasanTetapAda($user, $role, $scope, $scopeId)) {
                    continue;
                }

                $assignment = $this->assignRole->handle(
                    $user, $role, $scope, $scopeId,
                    $mulai->toDateString(), $selesai->toDateString(), $actor,
                );

                $assignment->forceFill(['project_team_member_id' => $member->id])->save();
                $memberi = true;
            }

            if ($memberi) {
                $member->forceFill(['grants_access' => true])->save();
            }

            return $member;
        });

        activity('access')
            ->performedOn($user)
            ->causedBy($actor)
            ->withProperties([
                'project' => $project->code,
                'role' => $role->code,
                'starts_on' => $member->starts_on->toDateString(),
                'ends_on' => $member->ends_on->toDateString(),
                'grants_access' => $member->grants_access,
            ])
            ->log('Ditambahkan ke Tim site');

        return $member->refresh();
    }

    /** Penugasan dengan kunci sama yang bukan milik Tim site — jangan disentuh. */
    private function penugasanTetapAda(User $user, Role $role, ScopeType $scope, int $scopeId): bool
    {
        return RoleAssignment::query()
            ->where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->where('scope_type', $scope->value)
            ->where('scope_id', $scopeId)
            ->whereNull('project_team_member_id')
            ->exists();
    }

    private function tanggal(string $nilai, string $label): Carbon
    {
        try {
            return Carbon::parse(trim($nilai))->startOfDay();
        } catch (\Throwable) {
            throw AccessRuleException::rule('BR-GEN-11', $label.' tidak sah.');
        }
    }
}

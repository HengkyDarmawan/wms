<?php

declare(strict_types=1);

namespace App\Domain\Access\Support;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\ProjectTeamMember;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Support\ProjectClosureChecklist;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Collection;

/**
 * Aturan Tim site (A-337, A-338): peran apa saja yang bisa ditempatkan di site
 * dan cakupan mana yang harus diberikan untuk tiap peran.
 *
 * Jenis cakupan dipilih **sesuai dimensi yang memang dipakai peran itu**
 * (BR-GEN-09). Ini bukan kosmetik: `User::accessibleScopeIds()` membaca "tidak
 * punya penugasan pada satu dimensi" sebagai *tidak dibatasi*, jadi memberi
 * cakupan proyek kepada staf gudang justru akan mempersempit akses proyeknya
 * yang tadinya terbuka.
 */
class SiteTeam
{
    /** Peran yang bisa ditempatkan di site => dimensi cakupannya. */
    public const ROLE_SCOPES = [
        'warehouse_head' => 'warehouse',
        'warehouse_staff' => 'warehouse',
        'internal_requester' => 'project',
        'client_user' => 'project',
    ];

    public function __construct(private readonly ProjectClosureChecklist $checklist) {}

    public static function scopeFor(Role $role): ?ScopeType
    {
        $dimensi = self::ROLE_SCOPES[$role->code] ?? null;

        return $dimensi === null ? null : ScopeType::from($dimensi);
    }

    /** Gudang Site aktif milik proyek. @return Collection<int, Warehouse> */
    public function siteWarehouses(Project $project): Collection
    {
        return $this->checklist->siteWarehouses($project)->filter(fn (Warehouse $w) => (bool) $w->is_active)->values();
    }

    /**
     * Id cakupan yang harus diberikan anggota: tiap Gudang Site untuk peran
     * gudang, atau proyek itu sendiri untuk peran berbasis proyek.
     *
     * @return array<int, int>
     */
    public function targets(Project $project, Role $role): array
    {
        $scope = self::scopeFor($role);

        if ($scope === ScopeType::Project) {
            return [(int) $project->id];
        }

        if ($scope === ScopeType::Warehouse) {
            return $this->siteWarehouses($project)->map(fn (Warehouse $w) => (int) $w->id)->all();
        }

        return [];
    }

    /** Peran yang boleh dipilih di dialog Tambah anggota. @return Collection<int, Role> */
    public function roleOptions(): Collection
    {
        return Role::query()->where('is_active', true)
            ->whereIn('code', array_keys(self::ROLE_SCOPES))
            ->orderBy('name')->get();
    }

    /**
     * Calon anggota: staf kita, dan PIC klien yang sudah punya akun portal
     * untuk klien proyek ini.
     *
     * @return array{internal: Collection<int, User>, klien: Collection<int, User>}
     */
    public function candidates(Project $project): array
    {
        return [
            'internal' => User::query()->active()->internal()->orderBy('name')->get(['id', 'name', 'email']),
            'klien' => $project->client_id === null
                ? collect()
                : User::query()->active()->where('client_id', $project->client_id)
                    ->orderBy('name')->get(['id', 'name', 'email']),
        ];
    }

    /**
     * Memperpanjang boleh oleh pemegang `role.assign`, dan — usulan A-342 —
     * Kepala Gudang yang cakupannya memuat Gudang Site proyek ini, karena
     * dialah yang menerima pengingat H-7 dan paling tahu keadaan di site.
     */
    public function canExtend(User $actor, Project $project): bool
    {
        if ($actor->hasPermission('role.assign')) {
            return true;
        }

        if (! $actor->hasPermission('warehouse.update')) {
            return false;
        }

        return $this->siteWarehouses($project)
            ->contains(fn (Warehouse $w) => $actor->canAccessWarehouse((int) $w->id));
    }

    /**
     * Mencatat penugasan proyek bertanggal yang baru dibuat sebagai keanggotaan
     * Tim site (A-339), supaya periodenya terlihat dan bisa diperpanjang di hub
     * proyek. Dipakai jalur "Buat akun portal" dan form pengguna peran Klien.
     *
     * @param  iterable<int, Project>  $projects
     */
    public function catatPenempatanAwal(User $user, Role $role, iterable $projects, ?User $actor = null): void
    {
        foreach ($projects as $project) {
            $assignment = $user->roleAssignments()
                ->where('role_id', $role->id)
                ->where('scope_type', ScopeType::Project->value)
                ->where('scope_id', $project->id)
                ->first();

            if ($assignment === null || $assignment->project_team_member_id !== null) {
                continue;
            }

            $member = ProjectTeamMember::create([
                'project_id' => $project->id,
                'user_id' => $user->id,
                'role_id' => $role->id,
                'starts_on' => ($assignment->valid_from ?? now())->toDateString(),
                'ends_on' => ($assignment->valid_until ?? now()->addYear())->toDateString(),
                'grants_access' => true,
                'created_by' => $actor?->id,
            ]);

            $assignment->forceFill(['project_team_member_id' => $member->id])->save();
        }
    }

    /** Bawaan tanggal selesai penempatan: target selesai proyek, atau setahun. */
    public static function selesaiBawaan(Project $project): string
    {
        return $project->target_end_date?->toDateString() ?? now()->addYear()->toDateString();
    }
}

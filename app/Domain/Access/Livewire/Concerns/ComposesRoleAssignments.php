<?php

declare(strict_types=1);

namespace App\Domain\Access\Livewire\Concerns;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\RoleGuide;
use App\Domain\Access\Support\SiteTeam;
use App\Domain\Master\Models\Project;
use Illuminate\Support\Collection;

/**
 * Menerjemahkan antara **layar** form pengguna (satu peran utama + beberapa
 * centang) dan **baris** `role_assignments` yang sebenarnya disimpan (A-331).
 *
 * Dipisah dari komponen `UserForm` supaya berkasnya tetap di bawah batas
 * +-450 baris dan aturan penerjemahannya bisa dibaca utuh dalam satu berkas.
 */
trait ComposesRoleAssignments
{
    /**
     * Memecah penugasan tersimpan menjadi satu peran utama + sisanya.
     *
     * Penugasan milik Tim site sengaja tidak dimuat (A-337): ia bertanggal dan
     * dikelola hub proyek. Bila penugasan sebuah peran tidak bisa diringkas jadi
     * satu pertanyaan (mis. satu peran dengan campuran cakupan), semuanya jatuh
     * ke *Pengaturan lanjutan* apa adanya — layar tidak menebak-nebak.
     */
    private function muatPenugasan(User $user): void
    {
        $baris = $user->roleAssignments->filter(fn (RoleAssignment $a) => $a->project_team_member_id === null)->values();

        $this->penugasanSiteSaja = $baris->isEmpty() && $user->roleAssignments->isNotEmpty();

        if ($this->penugasanSiteSaja) {
            $this->peranUtama = (string) $user->roleAssignments->first()->role_id;

            return;
        }

        $utamaDipakai = false;

        foreach ($baris->groupBy('role_id') as $roleId => $rows) {
            $role = Role::query()->find($roleId);

            if (! $utamaDipakai && $role !== null && $this->bisaJadiPeranUtama($role, $rows)) {
                $this->peranUtama = (string) $roleId;
                $this->isiCakupanUtama($role, $rows);
                $utamaDipakai = true;

                continue;
            }

            foreach ($rows as $a) {
                $this->assignments[] = [
                    'role_id' => $a->role_id,
                    'scope_type' => $a->scope_type->value,
                    'scope_id' => $a->scope_id,
                    'valid_from' => $a->valid_from?->toDateString(),
                    'valid_until' => $a->valid_until?->toDateString(),
                ];
            }
        }

        // Mode Ubah membuka Pengaturan lanjutan sendiri bila ada yang perlu dilihat.
        $this->lanjutanTerbuka = $this->assignments !== []
            || $baris->contains(fn (RoleAssignment $a) => $a->valid_from !== null || $a->valid_until !== null);
    }

    /** @param  Collection<int, RoleAssignment>  $rows */
    private function bisaJadiPeranUtama(Role $role, Collection $rows): bool
    {
        return match (RoleGuide::pertanyaan($role)) {
            RoleGuide::SEMUA => $rows->count() === 1 && $rows->first()->scope_type === ScopeType::All,
            RoleGuide::GUDANG => $rows->every(fn (RoleAssignment $a) => $a->scope_type === ScopeType::Warehouse && $a->scope_id !== null),
            RoleGuide::PROYEK, RoleGuide::KLIEN => $rows->every(fn (RoleAssignment $a) => $a->scope_type === ScopeType::Project && $a->scope_id !== null),
            default => false,
        };
    }

    /** @param  Collection<int, RoleAssignment>  $rows */
    private function isiCakupanUtama(Role $role, Collection $rows): void
    {
        $ids = $rows->map(fn (RoleAssignment $a) => (string) $a->scope_id)->values()->all();

        match (RoleGuide::pertanyaan($role)) {
            RoleGuide::GUDANG => $this->gudangDipilih = $ids,
            RoleGuide::PROYEK, RoleGuide::KLIEN => $this->proyekDipilih = $ids,
            default => null,
        };
    }

    /**
     * Penugasan akhir = peran utama (satu baris per gudang/proyek yang dicentang,
     * atau satu baris "semua") + baris Pengaturan lanjutan.
     *
     * @return array<int, array<string, mixed>>
     */
    private function susunPenugasan(?Role $peran, ?string $pertanyaan): array
    {
        $hasil = [];

        if ($peran !== null) {
            $baris = fn (string $scope, ?int $id, ?string $dari = null, ?string $sampai = null) => [
                'role_id' => (int) $peran->id,
                'scope_type' => $scope,
                'scope_id' => $id,
                'valid_from' => $dari,
                'valid_until' => $sampai,
            ];

            if ($pertanyaan === RoleGuide::GUDANG) {
                foreach ($this->gudangDipilih as $id) {
                    $hasil[] = $baris(ScopeType::Warehouse->value, (int) $id);
                }
            } elseif ($pertanyaan === RoleGuide::PROYEK) {
                foreach ($this->proyekDipilih as $id) {
                    $hasil[] = $baris(ScopeType::Project->value, (int) $id);
                }
            } elseif ($pertanyaan === RoleGuide::KLIEN) {
                // Periode awal penempatan; Tim site yang memperpanjang atau mengakhiri.
                foreach ($this->proyekTerpilih() as $proyek) {
                    $hasil[] = $baris(
                        ScopeType::Project->value,
                        (int) $proyek->id,
                        now()->toDateString(),
                        SiteTeam::selesaiBawaan($proyek),
                    );
                }
            } else {
                $hasil[] = $baris(ScopeType::All->value, null);
            }
        }

        foreach ($this->assignments as $a) {
            if (($a['role_id'] ?? null) === null || $a['role_id'] === '') {
                continue;
            }

            $hasil[] = [
                'role_id' => (int) $a['role_id'],
                'scope_type' => $a['scope_type'],
                'scope_id' => $a['scope_id'] !== null && $a['scope_id'] !== '' ? (int) $a['scope_id'] : null,
                // Tanggal tidak lagi bisa diketik, tetapi yang sudah ada dibawa
                // apa adanya supaya penugasan lama tidak menjadi permanen.
                'valid_from' => $a['valid_from'] ?: null,
                'valid_until' => $a['valid_until'] ?: null,
            ];
        }

        return $hasil;
    }

    /** @return Collection<int, Project> */
    private function proyekTerpilih(): Collection
    {
        $ids = array_map('intval', $this->proyekDipilih);

        return $ids === [] ? collect() : Project::query()->whereIn('id', $ids)->get();
    }

    /**
     * Membandingkan penugasan yang dikirim form dengan yang tersimpan, tanpa
     * memedulikan urutan barisnya. Penugasan milik Tim site tidak ikut dihitung:
     * form ini memang tidak mengelolanya.
     *
     * @param  array<int, array<string, mixed>>  $submitted
     */
    private function assignmentsChanged(User $target, array $submitted): bool
    {
        $sidik = static fn (array $a): string => implode('|', [
            $a['role_id'],
            $a['scope_type'],
            $a['scope_id'] ?? '',
            $a['valid_from'] ?? '',
            $a['valid_until'] ?? '',
        ]);

        $baru = array_map($sidik, $submitted);

        $lama = $target->roleAssignments()->whereNull('project_team_member_id')->get()
            ->map(fn ($a) => $sidik([
                'role_id' => (int) $a->role_id,
                'scope_type' => $a->scope_type->value,
                'scope_id' => $a->scope_id === null ? null : (int) $a->scope_id,
                'valid_from' => $a->valid_from?->toDateString(),
                'valid_until' => $a->valid_until?->toDateString(),
            ]))
            ->all();

        sort($baru);
        sort($lama);

        return $baru !== $lama;
    }
}

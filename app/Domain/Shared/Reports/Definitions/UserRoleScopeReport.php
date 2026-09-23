<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\User;
use App\Domain\Shared\Reports\Report;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Master\Models\Project;
use Illuminate\Support\Collection;

/** Laporan 10-access §9 — user × role × cakupan. */
class UserRoleScopeReport extends Report
{
    public function key(): string
    {
        return 'user-role-cakupan';
    }

    public function title(): string
    {
        return 'Pengguna × role × cakupan';
    }

    public function permission(): string
    {
        return 'user.view';
    }

    public function description(): string
    {
        return 'Siapa memegang role apa, di gudang atau proyek mana, dan sampai kapan.';
    }

    public function columns(): array
    {
        return [
            'user' => 'Pengguna',
            'email' => 'Email',
            'role' => 'Role',
            'cakupan' => 'Cakupan',
            'berlaku_dari' => 'Berlaku dari',
            'berlaku_sampai' => 'Berlaku sampai',
            'atasan' => 'Atasan langsung',
            'status' => 'Status pengguna',
        ];
    }

    public function filters(): array
    {
        return [
            'status' => [
                'label' => 'Status pengguna',
                'options' => ['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'],
            ],
            'scope_type' => [
                'label' => 'Jenis cakupan',
                'options' => collect(ScopeType::cases())
                    ->mapWithKeys(fn (ScopeType $s) => [$s->value => $s->label()])
                    ->all(),
            ],
        ];
    }

    public function rows(array $filters): Collection
    {
        $gudang = Warehouse::withoutGlobalScopes()->pluck('name', 'id');
        $proyek = Project::query()->pluck('name', 'id');

        return User::query()
            ->with('roleAssignments.role', 'manager:id,name')
            ->when(($filters['status'] ?? '') !== '', fn ($q) => $q->where('is_active', $filters['status'] === 'aktif'))
            ->orderBy('name')
            ->get()
            ->flatMap(function (User $user) use ($filters, $gudang, $proyek): array {
                $baris = [];

                foreach ($user->roleAssignments as $penugasan) {
                    if (($filters['scope_type'] ?? '') !== '' && $penugasan->scope_type->value !== $filters['scope_type']) {
                        continue;
                    }

                    $baris[] = [
                        'user' => $user->name,
                        'email' => $user->email,
                        'role' => $penugasan->role?->name ?? '—',
                        'cakupan' => match ($penugasan->scope_type) {
                            ScopeType::All => 'Seluruh company',
                            ScopeType::Warehouse => 'Gudang: '.($gudang[$penugasan->scope_id] ?? $penugasan->scope_id),
                            ScopeType::Project => 'Proyek: '.($proyek[$penugasan->scope_id] ?? $penugasan->scope_id),
                        },
                        'berlaku_dari' => $penugasan->valid_from?->format('d/m/Y') ?? '—',
                        'berlaku_sampai' => $penugasan->valid_until?->format('d/m/Y') ?? '—',
                        'atasan' => $user->manager?->name ?? '—',
                        'status' => $user->status()->label(),
                    ];
                }

                return $baris;
            });
    }
}

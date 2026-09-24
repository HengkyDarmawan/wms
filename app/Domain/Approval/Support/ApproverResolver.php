<?php

declare(strict_types=1);

namespace App\Domain\Approval\Support;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\Position;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApproverType;
use App\Domain\Master\Models\Project;

/**
 * Menerjemahkan jenis approver satu lapis menjadi user nyata (Blueprint §8.1).
 *
 * Role dan kepala gudang dibatasi cakupan dokumen: penugasan role bercakupan
 * `all`, atau gudang/proyek yang sama dengan dokumen (BR-GEN-09). User yang
 * nonaktif, user klien, atau yang tidak memegang permission approve jenis
 * dokumen itu tidak memenuhi syarat; lapisnya lalu dieskalasi (BR-APR-06, A-86).
 */
class ApproverResolver
{
    /** @var array<string, bool> */
    private array $eligibleCache = [];

    /** @return array<int, int> kandidat mentah, urut id */
    public function resolve(ApproverType $type, ?int $refId, ApprovalContext $ctx): array
    {
        $ids = match ($type) {
            ApproverType::User => $refId !== null ? [$refId] : [],
            ApproverType::Position => $refId !== null
                ? User::query()->where('position_id', $refId)->pluck('id')->all()
                : [],
            ApproverType::Role => $refId !== null ? $this->roleHolders($refId, $ctx) : [],
            ApproverType::DirectManager => $ctx->requesterId !== null && ($m = $this->managerOf($ctx->requesterId)) !== null ? [$m] : [],
            ApproverType::WarehouseHead => $this->warehouseHeads($ctx),
            ApproverType::ProjectPic => $ctx->projectId !== null
                ? array_filter([(int) Project::query()->whereKey($ctx->projectId)->value('pic_user_id')])
                : [],
        };

        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);

        return $ids;
    }

    public function isEligible(int $userId, string $permission): bool
    {
        $kunci = $userId.'|'.$permission;

        if (! array_key_exists($kunci, $this->eligibleCache)) {
            $user = User::query()->find($userId);

            $this->eligibleCache[$kunci] = $user !== null
                && $user->is_active
                && $user->client_id === null
                && $user->hasPermission($permission);
        }

        return $this->eligibleCache[$kunci];
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    public function eligible(array $ids, string $permission): array
    {
        return array_values(array_filter($ids, fn (int $id) => $this->isEligible($id, $permission)));
    }

    public function managerOf(int $userId): ?int
    {
        $id = User::query()->whereKey($userId)->value('manager_id');

        return $id === null ? null : (int) $id;
    }

    /** @return array<int, int> pemegang role Admin Company (tujuan eskalasi terakhir, BR-APR-06) */
    public function companyAdmins(): array
    {
        $role = Role::findByCode('company_admin');

        if ($role === null) {
            return [];
        }

        $ids = RoleAssignment::query()->valid()->where('role_id', $role->id)->pluck('user_id')->map(fn ($v) => (int) $v)->unique()->sort()->values()->all();

        return $ids;
    }

    /** Label manusiawi untuk simulasi dan riwayat. */
    public function label(ApproverType $type, ?int $refId): string
    {
        $nama = match ($type) {
            ApproverType::User => $refId !== null ? User::query()->whereKey($refId)->value('name') : null,
            ApproverType::Position => $refId !== null ? Position::query()->whereKey($refId)->value('name') : null,
            ApproverType::Role => $refId !== null ? Role::query()->whereKey($refId)->value('name') : null,
            default => null,
        };

        return $type->label().($nama !== null ? ': '.$nama : '');
    }

    /** @return array<int, int> */
    private function roleHolders(int $roleId, ApprovalContext $ctx): array
    {
        return RoleAssignment::query()->valid()->where('role_id', $roleId)->get()
            ->filter(fn (RoleAssignment $a) => $this->dalamCakupan($a, $ctx))
            ->pluck('user_id')
            ->all();
    }

    /**
     * Kepala gudang yang ditugaskan ke gudang dokumen; bila tidak ada,
     * Kepala Gudang bercakupan semua gudang.
     *
     * @return array<int, int>
     */
    private function warehouseHeads(ApprovalContext $ctx): array
    {
        $role = Role::findByCode('warehouse_head');

        if ($role === null) {
            return [];
        }

        $penugasan = RoleAssignment::query()->valid()->where('role_id', $role->id)->get();

        $khusus = $penugasan
            ->filter(fn (RoleAssignment $a) => $a->scope_type === ScopeType::Warehouse
                && in_array((int) $a->scope_id, $ctx->warehouseIds, true))
            ->pluck('user_id')
            ->all();

        if ($khusus !== []) {
            return $khusus;
        }

        return $penugasan->filter(fn (RoleAssignment $a) => $a->scope_type === ScopeType::All)->pluck('user_id')->all();
    }

    private function dalamCakupan(RoleAssignment $a, ApprovalContext $ctx): bool
    {
        return match ($a->scope_type) {
            ScopeType::All => true,
            ScopeType::Warehouse => in_array((int) $a->scope_id, $ctx->warehouseIds, true),
            ScopeType::Project => $ctx->projectId !== null && (int) $a->scope_id === $ctx->projectId,
        };
    }
}

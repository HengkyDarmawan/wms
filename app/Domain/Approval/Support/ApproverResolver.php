<?php

declare(strict_types=1);

namespace App\Domain\Approval\Support;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\OrgUnit;
use App\Domain\Access\Models\Position;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\Atasan;
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

    public function __construct(private readonly Atasan $atasan) {}

    /**
     * @param  int|null  $managerLevels  hanya untuk Atasan langsung: 1 atau 2 tingkat (A-346)
     * @return array<int, int> kandidat mentah, urut id
     */
    public function resolve(ApproverType $type, ?int $refId, ApprovalContext $ctx, ?int $managerLevels = null): array
    {
        $ids = match ($type) {
            ApproverType::User => $refId !== null ? [$refId] : [],
            ApproverType::Position => $refId !== null
                ? User::query()->where('position_id', $refId)->pluck('id')->all()
                : [],
            ApproverType::Role => $refId !== null ? $this->roleHolders($refId, $ctx) : [],
            // A-344/A-345: atasan efektif — isian manual user, atau pemegang jabatan atasan.
            ApproverType::DirectManager => $ctx->requesterId !== null
                ? $this->atasan->dari($ctx->requesterId, self::tingkat($managerLevels))
                : [],
            ApproverType::WarehouseHead => $this->warehouseHeads($ctx),
            ApproverType::ProjectPic => $ctx->projectId !== null
                ? array_filter([(int) Project::query()->whereKey($ctx->projectId)->value('pic_user_id')])
                : [],
        };

        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);

        return $ids;
    }

    /**
     * A-269: divisi pemohon = unit organisasinya beserta unit induknya
     * (approver di Divisi A atau Direksi di atasnya). Null bila pemohon
     * tanpa unit — batas divisi tidak bisa diterapkan.
     *
     * @return array<int, int>|null
     */
    public function requesterOrgUnits(ApprovalContext $ctx): ?array
    {
        $pemohon = $ctx->requesterId ?? ($ctx->requesterIds[0] ?? null);
        $unit = $pemohon === null ? null : User::query()->whereKey($pemohon)->value('org_unit_id');

        if ($unit === null) {
            return null;
        }

        $hasil = [];
        $id = (int) $unit;

        while ($id > 0 && ! in_array($id, $hasil, true) && count($hasil) < 20) {
            $hasil[] = $id;
            $id = (int) OrgUnit::query()->whereKey($id)->value('parent_id');
        }

        return $hasil;
    }

    /**
     * @param  array<int, int>  $ids
     * @param  array<int, int>  $units
     * @return array<int, int>
     */
    public function inOrgUnits(array $ids, array $units): array
    {
        if ($ids === []) {
            return [];
        }

        $boleh = User::query()->whereIn('id', $ids)->whereIn('org_unit_id', $units)->pluck('id')->map(fn ($v) => (int) $v)->all();

        return array_values(array_filter($ids, fn (int $id) => in_array($id, $boleh, true)));
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

    /** Satu atasan efektif (id terkecil) — pengalihan SoD & eskalasi (BR-APR-03, BR-APR-06). */
    public function managerOf(int $userId): ?int
    {
        return $this->atasan->pertama($userId);
    }

    /** @return array<int, int> semua atasan efektif tingkat 1 */
    public function managersOf(int $userId): array
    {
        return $this->atasan->dari($userId);
    }

    /** Tingkat atasan yang sah: 2 bila diminta, selain itu 1. */
    public static function tingkat(mixed $levels): int
    {
        return (int) $levels === 2 ? 2 : 1;
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
    public function label(ApproverType $type, ?int $refId, ?int $managerLevels = null): string
    {
        if ($type === ApproverType::DirectManager) {
            return $type->label().' — '.self::tingkat($managerLevels).' tingkat';
        }

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

<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\CompanyAdminGuard;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `user.update` (+ `role.assign` bila penugasan ikut diubah).
 *
 * Email tidak bisa diubah setelah undangan diterima (10-access §6.3).
 * Penugasan role disinkronkan: yang hilang dicabut, yang baru ditambahkan.
 */
class UpdateUser
{
    public function __construct(
        private readonly AssignRole $assignRole,
        private readonly CompanyAdminGuard $adminGuard,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>|null  $assignments  null = tidak diubah
     */
    public function handle(User $user, array $attributes, ?array $assignments = null, ?User $actor = null): User
    {
        if ($assignments !== null && $assignments === []) {
            throw AccessRuleException::rule(
                'BR-ACC-01',
                'User wajib punya minimal satu penugasan role.',
            );
        }

        DB::transaction(function () use ($user, $attributes, $assignments, $actor): void {
            $data = [
                'name' => $attributes['name'] ?? $user->name,
                'phone' => $attributes['phone'] ?? null,
                'org_unit_id' => $attributes['org_unit_id'] ?? null,
                'position_id' => $attributes['position_id'] ?? null,
                'manager_id' => $attributes['manager_id'] ?? null,
            ];

            // Email hanya boleh diubah selama undangan belum diterima.
            if (array_key_exists('email', $attributes) && $user->email_verified_at === null) {
                $data['email'] = mb_strtolower(trim((string) $attributes['email']));
            }

            if (array_key_exists('client_id', $attributes)) {
                $data['client_id'] = $attributes['client_id'];
            }

            if (($data['manager_id'] ?? null) === $user->id) {
                throw new AccessRuleException('Atasan langsung tidak boleh dirinya sendiri.');
            }

            $user->fill($data)->save();

            if ($assignments !== null) {
                $this->syncAssignments($user, $assignments, $actor);
            }
        });

        $user->forgetPermissionCache();

        activity('access')->performedOn($user)->causedBy($actor)->log('User diubah');

        return $user->refresh();
    }

    /** @param  array<int, array<string, mixed>>  $assignments */
    private function syncAssignments(User $user, array $assignments, ?User $actor): void
    {
        $kunciBaru = [];

        foreach ($assignments as $assignment) {
            $role = Role::findOrFail($assignment['role_id']);
            $scopeType = $this->scopeType($assignment['scope_type'] ?? null);
            $scopeId = $scopeType->needsScopeId() && isset($assignment['scope_id'])
                ? (int) $assignment['scope_id']
                : null;

            $this->assignRole->handle(
                $user,
                $role,
                $scopeType,
                $scopeId,
                $assignment['valid_from'] ?? null,
                $assignment['valid_until'] ?? null,
                $actor,
            );

            $kunciBaru[] = $role->id.'|'.$scopeType->value.'|'.($scopeId ?? '');
        }

        // Cabut penugasan yang tidak ada lagi di daftar.
        $user->refresh()->forgetPermissionCache();

        foreach ($user->roleAssignments()->with('role')->get() as $existing) {
            $kunci = $existing->role_id.'|'.$existing->scope_type->value.'|'.($existing->scope_id ?? '');

            if (in_array($kunci, $kunciBaru, true)) {
                continue;
            }

            if ($existing->role?->code === CompanyAdminGuard::ROLE_CODE) {
                $this->adminGuard->ensureNotLastAdmin($user, 'mencabut role Admin Company');
            }

            $existing->delete();
        }
    }
    /** AD-14: nilai cakupan di luar katalog ditolak sebagai pelanggaran aturan, bukan galat. */
    private function scopeType(mixed $value): ScopeType
    {
        $scope = is_string($value) ? ScopeType::tryFrom($value) : ($value instanceof ScopeType ? $value : null);

        if ($scope === null) {
            throw new AccessRuleException('Jenis cakupan tidak dikenal.');
        }

        return $scope;
    }

}

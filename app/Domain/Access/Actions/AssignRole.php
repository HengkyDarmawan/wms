<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\User;

/**
 * Permission: `role.assign`.
 *
 * Memberi role kepada user dengan cakupan (BR-GEN-09, A-46) sambil menegakkan:
 *  - BR-ACC-03 role Klien eksklusif,
 *  - BR-ACC-04 cakupan `all` hanya untuk role internal.
 */
class AssignRole
{
    public function handle(
        User $user,
        Role $role,
        ScopeType $scopeType,
        ?int $scopeId = null,
        ?string $validFrom = null,
        ?string $validUntil = null,
        ?User $assignedBy = null,
    ): RoleAssignment {
        $this->guardClientExclusivity($user, $role);
        $this->guardScope($role, $scopeType, $scopeId);

        $assignment = RoleAssignment::updateOrCreate(
            [
                'user_id' => $user->id,
                'role_id' => $role->id,
                'scope_type' => $scopeType->value,
                'scope_id' => $scopeType->needsScopeId() ? $scopeId : null,
            ],
            [
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
                'assigned_by' => $assignedBy?->id,
            ],
        );

        $user->forgetPermissionCache();

        activity('access')
            ->performedOn($user)
            ->causedBy($assignedBy)
            ->withProperties([
                'role' => $role->code,
                'scope_type' => $scopeType->value,
                'scope_id' => $assignment->scope_id,
            ])
            ->log('Penugasan role ditambahkan');

        return $assignment;
    }

    /** BR-ACC-03: user klien hanya role Klien; user internal tidak boleh role Klien. */
    private function guardClientExclusivity(User $user, Role $role): void
    {
        if ($user->isClient() && ! $role->is_client_role) {
            throw AccessRuleException::rule(
                'BR-ACC-03',
                'User klien hanya boleh diberi role Klien.',
            );
        }

        if (! $user->isClient() && $role->is_client_role) {
            throw AccessRuleException::rule(
                'BR-ACC-03',
                'Role Klien tidak bisa digabung dengan role internal.',
            );
        }
    }

    /** BR-ACC-04: cakupan `all` hanya untuk role internal; selain itu wajib scope_id. */
    private function guardScope(Role $role, ScopeType $scopeType, ?int $scopeId): void
    {
        if ($scopeType === ScopeType::All && ! $role->allowsAllScope()) {
            throw AccessRuleException::rule(
                'BR-ACC-04',
                'Role Klien tidak boleh bercakupan Semua.',
            );
        }

        if ($scopeType->needsScopeId() && $scopeId === null) {
            throw AccessRuleException::rule(
                'BR-ACC-04',
                'Cakupan '.$scopeType->label().' wajib memilih satu '.strtolower($scopeType->label()).'.',
            );
        }
    }
}

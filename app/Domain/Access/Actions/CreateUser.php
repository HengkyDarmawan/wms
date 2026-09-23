<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `user.create`.
 *
 * Membuat user beserta penugasan role awal (BR-ACC-01: minimal satu penugasan)
 * dan, bila diminta, mengirim undangan agar user mengatur password sendiri.
 *
 * @phpstan-type Penugasan array{role_id:int, scope_type:string, scope_id:?int, valid_from:?string, valid_until:?string}
 */
class CreateUser
{
    public function __construct(
        private readonly AssignRole $assignRole,
        private readonly InviteUser $inviteUser,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $assignments
     */
    public function handle(array $attributes, array $assignments, bool $sendInvitation = true, ?User $actor = null): User
    {
        if ($assignments === []) {
            throw AccessRuleException::rule(
                'BR-ACC-01',
                'User wajib punya minimal satu penugasan role.',
            );
        }

        $user = DB::transaction(function () use ($attributes, $assignments, $actor): User {
            $user = User::create([
                'name' => $attributes['name'],
                'email' => mb_strtolower(trim((string) $attributes['email'])),
                'phone' => $attributes['phone'] ?? null,
                'client_id' => $attributes['client_id'] ?? null,
                'org_unit_id' => $attributes['org_unit_id'] ?? null,
                'position_id' => $attributes['position_id'] ?? null,
                'manager_id' => $attributes['manager_id'] ?? null,
                'password' => null,           // diisi user lewat undangan
                'is_active' => true,
            ]);

            foreach ($assignments as $assignment) {
                $this->applyAssignment($user, $assignment, $actor);
            }

            return $user;
        });

        if ($sendInvitation) {
            $this->inviteUser->handle($user, $actor);
        }

        activity('access')
            ->performedOn($user)
            ->causedBy($actor)
            ->log('User dibuat');

        return $user->refresh();
    }

    /** @param  array<string, mixed>  $assignment */
    private function applyAssignment(User $user, array $assignment, ?User $actor): void
    {
        $role = Role::findOrFail($assignment['role_id']);

        $this->assignRole->handle(
            $user,
            $role,
            $this->scopeType($assignment['scope_type'] ?? null),
            isset($assignment['scope_id']) ? (int) $assignment['scope_id'] : null,
            $assignment['valid_from'] ?? null,
            $assignment['valid_until'] ?? null,
            $actor,
        );
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

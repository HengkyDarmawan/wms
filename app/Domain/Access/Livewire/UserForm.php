<?php

declare(strict_types=1);

namespace App\Domain\Access\Livewire;

use App\Domain\Access\Actions\CreateUser;
use App\Domain\Access\Actions\UpdateUser;
use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\OrgUnit;
use App\Domain\Access\Models\Position;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Project;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Layar 10-access §6.3 — form tambah/ubah pengguna beserta penugasan role × cakupan.
 * Field wajib ditandai `*` di view (BR-GEN-11).
 */
class UserForm extends Component
{
    /**
     * Terkunci: properti publik Livewire bisa ditimpa dari browser. Tanpa ini,
     * pemegang `user.create` dapat menyulap form "tambah pengguna" menjadi form
     * ubah pengguna lain hanya dengan mengirim `userId` lain.
     */
    #[Locked]
    public ?int $userId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public ?int $orgUnitId = null;

    public ?int $positionId = null;

    public ?int $managerId = null;

    public ?int $clientId = null;

    public bool $sendInvitation = true;

    /** @var array<int, array<string, mixed>> */
    public array $assignments = [];

    public function mount(?int $userId = null): void
    {
        $this->userId = $userId;

        if ($userId === null) {
            $this->authorize('create', User::class);
            $this->addAssignment();

            return;
        }

        $user = User::with('roleAssignments')->findOrFail($userId);
        $this->authorize('update', $user);

        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) $user->phone;
        $this->orgUnitId = $user->org_unit_id;
        $this->positionId = $user->position_id;
        $this->managerId = $user->manager_id;
        $this->clientId = $user->client_id;
        $this->sendInvitation = false;

        $this->assignments = $user->roleAssignments
            ->map(fn ($a) => [
                'role_id' => $a->role_id,
                'scope_type' => $a->scope_type->value,
                'scope_id' => $a->scope_id,
                'valid_from' => $a->valid_from?->toDateString(),
                'valid_until' => $a->valid_until?->toDateString(),
            ])
            ->values()
            ->all();

        if ($this->assignments === []) {
            $this->addAssignment();
        }
    }

    public function addAssignment(): void
    {
        $this->assignments[] = [
            'role_id' => null,
            'scope_type' => ScopeType::All->value,
            'scope_id' => null,
            'valid_from' => null,
            'valid_until' => null,
        ];
    }

    public function removeAssignment(int $index): void
    {
        unset($this->assignments[$index]);
        $this->assignments = array_values($this->assignments);

        if ($this->assignments === []) {
            $this->addAssignment();
        }
    }

    public function save(CreateUser $createUser, UpdateUser $updateUser)
    {
        // Otorisasi diulang di sini, bukan hanya di mount(): satu permintaan
        // Livewire bisa memanggil metode ini langsung tanpa pernah melewati mount.
        $target = $this->userId === null ? null : User::findOrFail($this->userId);

        $this->authorize($target === null ? 'create' : 'update', $target ?? User::class);

        $data = $this->validate();

        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $this->phone !== '' ? $this->phone : null,
            'org_unit_id' => $this->orgUnitId,
            'position_id' => $this->positionId,
            'manager_id' => $this->managerId,
            'client_id' => $this->clientId,
        ];

        $assignments = array_map(
            fn (array $a) => [
                'role_id' => (int) $a['role_id'],
                'scope_type' => $a['scope_type'],
                'scope_id' => $a['scope_id'] !== null && $a['scope_id'] !== '' ? (int) $a['scope_id'] : null,
                'valid_from' => $a['valid_from'] ?: null,
                'valid_until' => $a['valid_until'] ?: null,
            ],
            $this->assignments,
        );

        // BR-GEN-09: mengubah penugasan role menuntut `role.assign` tersendiri.
        // Tanpa pemeriksaan ini, pemegang `user.update` bisa menambahkan role
        // Admin Company untuk dirinya sendiri lewat form ini.
        if ($target === null || $this->assignmentsChanged($target, $assignments)) {
            $this->authorize('assignRole', $target ?? User::class);
        }

        try {
            if ($this->userId === null) {
                $user = $createUser->handle($attributes, $assignments, $this->sendInvitation, auth()->user());
                $pesan = $this->sendInvitation
                    ? 'Pengguna dibuat dan undangan dikirim.'
                    : 'Pengguna dibuat.';
            } else {
                $user = $updateUser->handle(User::findOrFail($this->userId), $attributes, $assignments, auth()->user());
                $pesan = 'Perubahan disimpan.';
            }
        } catch (AccessRuleException $e) {
            $this->addError('assignments', $e->getMessage());

            return null;
        }

        session()->flash('status', $pesan);

        return $this->redirectRoute('users.show', ['user' => $user->id], navigate: false);
    }

    /**
     * Membandingkan penugasan yang dikirim form dengan yang tersimpan, tanpa
     * memedulikan urutan barisnya.
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

        $lama = $target->roleAssignments()->get()
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

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'email', 'max:150',
                Rule::unique('users', 'email')->ignore($this->userId),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'orgUnitId' => ['nullable', 'integer', 'exists:org_units,id'],
            'positionId' => ['nullable', 'integer', 'exists:positions,id'],
            'managerId' => ['nullable', 'integer', 'different:userId', 'exists:users,id'],
            'clientId' => ['nullable', 'integer', 'min:1'],
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.role_id' => ['required', 'integer', 'exists:roles,id'],
            'assignments.*.scope_type' => ['required', Rule::enum(ScopeType::class)],
            'assignments.*.scope_id' => ['nullable', 'integer', 'min:1'],
            'assignments.*.valid_from' => ['nullable', 'date'],
            'assignments.*.valid_until' => ['nullable', 'date', 'after_or_equal:assignments.*.valid_from'],
        ];
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return [
            'name' => 'Nama',
            'email' => 'Email',
            'phone' => 'Nomor WhatsApp',
            'orgUnitId' => 'Unit organisasi',
            'positionId' => 'Jabatan',
            'managerId' => 'Atasan langsung',
            'clientId' => 'Klien',
            'assignments' => 'Penugasan role',
            'assignments.*.role_id' => 'Role',
            'assignments.*.scope_type' => 'Cakupan',
            'assignments.*.scope_id' => 'ID cakupan',
        ];
    }

    public function render(): View
    {
        $user = $this->userId !== null ? User::find($this->userId) : null;

        return view('livewire.access.user-form', [
            'user' => $user,
            'emailTerkunci' => $user?->email_verified_at !== null,
            'roles' => Role::query()->where('is_active', true)->orderBy('name')->get(),
            'units' => OrgUnit::query()->where('is_active', true)->orderBy('name')->get(),
            'positions' => Position::query()
                ->when($this->orgUnitId !== null, fn ($q) => $q->where('org_unit_id', $this->orgUnitId))
                ->orderBy('level')->orderBy('name')->get(),
            'managers' => User::query()
                ->active()->internal()
                ->when($this->userId !== null, fn ($q) => $q->whereKeyNot($this->userId))
                ->orderBy('name')->get(),
            'scopeTypes' => ScopeType::cases(),
            // Cakupan dipilih lewat nama, bukan id angka (11-master §12, 12-warehouse §12).
            'projects' => Project::query()->active()->orderBy('name')->get(['id', 'code', 'name']),
            // withoutGlobalScopes: yang memberi cakupan harus melihat seluruh
            // gudang, termasuk yang di luar cakupannya sendiri.
            'warehouses' => Warehouse::withoutGlobalScopes()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }
}

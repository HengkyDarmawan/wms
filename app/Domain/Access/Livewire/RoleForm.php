<?php

declare(strict_types=1);

namespace App\Domain\Access\Livewire;

use App\Domain\Access\Actions\SaveRole;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\Role;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Layar 10-access §6.4 — form role dengan matriks permission per modul dan
 * tombol "salin dari role bawaan".
 */
class RoleForm extends Component
{
    /**
     * Terkunci: tanpa ini pemegang `role.create` bisa mengubah `roleId` dari
     * browser lalu menimpa permission role mana pun tanpa punya `role.update`.
     */
    #[Locked]
    public ?int $roleId = null;

    public string $code = '';

    public string $name = '';

    public bool $isClientRole = false;

    /** @var array<int, string> kunci permission yang dipilih */
    public array $selected = [];

    public string $copyFrom = '';

    public function mount(?int $roleId = null): void
    {
        $this->roleId = $roleId;

        if ($roleId === null) {
            $this->authorize('create', Role::class);

            return;
        }

        $role = Role::with('permissions')->findOrFail($roleId);
        $this->authorize('update', $role);

        $this->code = $role->code;
        $this->name = $role->name;
        $this->isClientRole = $role->is_client_role;
        $this->selected = $role->permissions->pluck('name')->all();
    }

    /** Menyalin permission dari role lain (biasanya role bawaan). */
    public function salin(): void
    {
        if ($this->copyFrom === '') {
            return;
        }

        $sumber = Role::with('permissions')->find((int) $this->copyFrom);

        if ($sumber === null) {
            return;
        }

        $this->selected = $sumber->permissions->pluck('name')->all();
        $this->dispatch('pesan', teks: 'Permission disalin dari role '.$sumber->name.'.');
    }

    public function pilihModul(string $module, bool $aktif): void
    {
        $kunci = Permission::query()->where('module', $module)->pluck('name')->all();

        $this->selected = $aktif
            ? array_values(array_unique([...$this->selected, ...$kunci]))
            : array_values(array_diff($this->selected, $kunci));
    }

    public function save(SaveRole $action)
    {
        // Otorisasi diulang di sini: metode publik Livewire bisa dipanggil
        // langsung tanpa pernah melewati mount().
        $role = $this->roleId === null ? null : Role::findOrFail($this->roleId);

        $this->authorize($role === null ? 'create' : 'update', $role ?? Role::class);

        $data = $this->validate();

        try {
            $role = $action->handle(
                $role,
                [
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'is_client_role' => $this->isClientRole,
                ],
                $this->selected,
                auth()->user(),
            );
        } catch (AccessRuleException $e) {
            $this->addError('name', $e->getMessage());

            return null;
        }

        session()->flash('status', $this->roleId === null ? 'Role dibuat.' : 'Perubahan role disimpan.');
        $this->roleId = $role->id;

        return $this->redirectRoute('roles.index', navigate: false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => $this->roleId === null
                ? ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:roles,code']
                : ['nullable'],
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('roles', 'name')->ignore($this->roleId),
            ],
            'selected' => ['array'],
            'selected.*' => ['string', 'exists:permissions,name'],
        ];
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return [
            'code' => 'Kode role',
            'name' => 'Nama role',
            'selected' => 'Permission',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex' => 'Kode role memakai huruf kecil, angka, dan garis bawah; diawali huruf.',
        ];
    }

    public function render(): View
    {
        $role = $this->roleId !== null ? Role::find($this->roleId) : null;

        return view('livewire.access.role-form', [
            'role' => $role,
            'permissionsPerModul' => Permission::query()
                ->orderBy('module')->orderBy('name')->get()->groupBy('module'),
            'roleLain' => Role::query()
                ->when($this->roleId !== null, fn ($q) => $q->whereKeyNot($this->roleId))
                ->orderByDesc('is_builtin')->orderBy('name')->get(),
        ]);
    }
}

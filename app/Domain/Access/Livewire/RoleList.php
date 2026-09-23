<?php

declare(strict_types=1);

namespace App\Domain\Access\Livewire;

use App\Domain\Access\Actions\DeactivateRole;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Layar 10-access §6.4 — daftar role bawaan & buatan company beserta jumlah
 * permission dan penugasan yang memakainya.
 */
class RoleList extends Component
{
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Role::class);
    }

    public function deactivate(int $roleId, DeactivateRole $action): void
    {
        $role = Role::findOrFail($roleId);

        $this->authorize('deactivate', $role);

        try {
            $action->handle($role, auth()->user());
        } catch (AccessRuleException $e) {
            $this->dispatch('pesan', teks: $e->getMessage(), jenis: 'danger');

            return;
        }

        $this->dispatch('pesan', teks: 'Role '.$role->name.' dinonaktifkan.');
    }

    public function reactivate(int $roleId, DeactivateRole $action): void
    {
        $role = Role::findOrFail($roleId);

        $this->authorize('update', $role);

        $action->reactivate($role, auth()->user());

        $this->dispatch('pesan', teks: 'Role '.$role->name.' diaktifkan kembali.');
    }

    public function render(): View
    {
        return view('livewire.access.role-list', ['roles' => $this->roles()]);
    }

    /** @return Collection<int, Role> */
    private function roles(): Collection
    {
        return Role::query()
            ->withCount(['permissions', 'assignments'])
            ->when($this->search !== '', function ($q): void {
                $cari = '%'.$this->search.'%';
                $q->where(fn ($s) => $s->where('name', 'like', $cari)->orWhere('code', 'like', $cari));
            })
            ->orderByDesc('is_builtin')
            ->orderBy('name')
            ->get();
    }
}

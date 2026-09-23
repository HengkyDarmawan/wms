<?php

declare(strict_types=1);

namespace App\Domain\Access\Livewire;

use App\Domain\Access\Actions\DeactivateUser;
use App\Domain\Access\Actions\InviteUser;
use App\Domain\Access\Actions\ReactivateUser;
use App\Domain\Access\Actions\SendPasswordReset;
use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Enums\UserStatus;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\OrgUnit;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Layar 10-access §6.3 — daftar pengguna dengan filter role, cakupan, status,
 * dan unit; aksi undang ulang, reset password, nonaktifkan/aktifkan.
 */
class UserList extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $roleFilter = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: '')]
    public string $unitFilter = '';

    #[Url(except: '')]
    public string $scopeTypeFilter = '';

    #[Url(except: '')]
    public string $scopeIdFilter = '';

    /** Id user yang sedang dikonfirmasi penonaktifannya. */
    #[Locked]
    public ?int $deactivatingId = null;

    public string $deactivateReason = '';

    public string $deactivateNotes = '';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updated(string $property): void
    {
        if (str_ends_with($property, 'Filter') || $property === 'search') {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'roleFilter', 'statusFilter', 'unitFilter', 'scopeTypeFilter', 'scopeIdFilter']);
        $this->resetPage();
    }

    // ------------------------------------------------------------------ aksi

    public function confirmDeactivate(int $userId): void
    {
        $this->deactivatingId = $userId;
        $this->deactivateReason = '';
        $this->deactivateNotes = '';
        $this->resetValidation();
    }

    public function cancelDeactivate(): void
    {
        $this->deactivatingId = null;
    }

    public function deactivate(DeactivateUser $action): void
    {
        $user = User::findOrFail($this->deactivatingId);

        $this->authorize('deactivate', $user);

        // BR-GEN-11: alasan wajib, keterangan opsional.
        $this->validate(
            ['deactivateReason' => ['required', 'string', 'max:255']],
            [],
            ['deactivateReason' => 'Alasan'],
        );

        try {
            $action->handle($user, $this->deactivateReason, $this->deactivateNotes ?: null, auth()->user());
        } catch (AccessRuleException $e) {
            $this->addError('deactivateReason', $e->getMessage());

            return;
        }

        $this->deactivatingId = null;
        $this->dispatch('pesan', teks: 'User '.$user->name.' dinonaktifkan.');
    }

    public function reactivate(int $userId, ReactivateUser $action): void
    {
        $user = User::findOrFail($userId);

        $this->authorize('deactivate', $user);

        try {
            $action->handle($user, auth()->user());
        } catch (AccessRuleException $e) {
            $this->dispatch('pesan', teks: $e->getMessage(), jenis: 'danger');

            return;
        }

        $this->dispatch('pesan', teks: 'User '.$user->name.' diaktifkan kembali.');
    }

    public function resendInvitation(int $userId, InviteUser $action): void
    {
        $user = User::findOrFail($userId);

        $this->authorize('invite', User::class);

        $action->handle($user, auth()->user());

        $this->dispatch('pesan', teks: 'Undangan dikirim ulang ke '.$user->email.'.');
    }

    public function sendPasswordReset(int $userId, SendPasswordReset $action): void
    {
        $user = User::findOrFail($userId);

        $this->authorize('resetPassword', $user);

        try {
            $action->handle($user, auth()->user());
        } catch (AccessRuleException $e) {
            $this->dispatch('pesan', teks: $e->getMessage(), jenis: 'danger');

            return;
        }

        $this->dispatch('pesan', teks: 'Tautan atur ulang password dikirim ke '.$user->email.'.');
    }

    // ------------------------------------------------------------------ query

    public function render(): View
    {
        return view('livewire.access.user-list', [
            'users' => $this->users(),
            'roles' => Role::query()->orderBy('name')->get(),
            'units' => OrgUnit::query()->orderBy('name')->get(),
            'statuses' => UserStatus::cases(),
            'scopeTypes' => ScopeType::cases(),
        ]);
    }

    private function users(): LengthAwarePaginator
    {
        return User::query()
            ->with(['orgUnit', 'position', 'roleAssignments.role'])
            ->when($this->search !== '', function (Builder $q): void {
                $cari = '%'.$this->search.'%';
                $q->where(fn (Builder $s) => $s->where('name', 'like', $cari)->orWhere('email', 'like', $cari));
            })
            ->when($this->roleFilter !== '', fn (Builder $q) => $q->whereHas(
                'roleAssignments',
                fn (Builder $a) => $a->valid()->where('role_id', (int) $this->roleFilter),
            ))
            ->when($this->unitFilter !== '', fn (Builder $q) => $q->where('org_unit_id', (int) $this->unitFilter))
            ->when($this->scopeTypeFilter !== '', fn (Builder $q) => $q->whereHas(
                'roleAssignments',
                function (Builder $a): void {
                    $a->valid()->where('scope_type', $this->scopeTypeFilter);

                    if ($this->scopeIdFilter !== '') {
                        $a->where('scope_id', (int) $this->scopeIdFilter);
                    }
                },
            ))
            ->when($this->statusFilter !== '', fn (Builder $q) => $this->applyStatusFilter($q))
            ->orderBy('name')
            ->paginate(15);
    }

    /** Status user adalah nilai turunan (10-access §3), jadi difilter lewat kondisi kolom. */
    private function applyStatusFilter(Builder $query): Builder
    {
        return match (UserStatus::tryFrom($this->statusFilter)) {
            UserStatus::Inactive => $query->where('is_active', false),
            UserStatus::Locked => $query->where('is_active', true)->where('locked_until', '>', now()),
            UserStatus::Invited => $query->where('is_active', true)
                ->where(fn (Builder $q) => $q->whereNull('password')
                    ->orWhereHas('invitations', fn (Builder $i) => $i->pending())),
            UserStatus::Active => $query->where('is_active', true)
                ->whereNotNull('password')
                ->where(fn (Builder $q) => $q->whereNull('locked_until')->orWhere('locked_until', '<=', now()))
                ->whereDoesntHave('invitations', fn (Builder $i) => $i->pending()),
            default => $query,
        };
    }
}

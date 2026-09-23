<?php

declare(strict_types=1);

namespace App\Domain\Access\Livewire;

use App\Domain\Access\Models\User;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Layar 10-access §6.3 — detail pengguna: ringkasan, penugasan role, perangkat,
 * dan riwayat dari audit log (BR-GEN-05).
 */
class UserDetail extends Component
{
    /**
     * Terkunci: UserPolicy::view meluluskan user melihat dirinya sendiri, jadi
     * tanpa kunci ini siapa pun bisa mengganti id dari browser dan membaca
     * email, penugasan role, perangkat, serta riwayat pengguna lain.
     */
    #[Locked]
    public int $userId;

    public string $tab = 'ringkasan';

    public function mount(int $userId): void
    {
        $user = User::findOrFail($userId);

        $this->authorize('view', $user);

        $this->userId = $userId;
    }

    public function pilihTab(string $tab): void
    {
        $this->tab = in_array($tab, ['ringkasan', 'role', 'perangkat', 'riwayat'], true)
            ? $tab
            : 'ringkasan';
    }

    public function render(): View
    {
        $user = User::with(['orgUnit', 'position', 'manager', 'devices', 'roleAssignments.role', 'roleAssignments.assignedBy'])
            ->findOrFail($this->userId);

        return view('livewire.access.user-detail', [
            'user' => $user,
            'riwayat' => $this->riwayat($user),
        ]);
    }

    /** @return Collection<int, Activity> */
    private function riwayat(User $user): Collection
    {
        if ($this->tab !== 'riwayat') {
            return collect();
        }

        return Activity::query()
            ->where('subject_type', $user->getMorphClass())
            ->where('subject_id', $user->getKey())
            ->latest('id')
            ->limit(50)
            ->get();
    }
}

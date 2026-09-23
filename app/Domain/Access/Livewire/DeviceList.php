<?php

declare(strict_types=1);

namespace App\Domain\Access\Livewire;

use App\Domain\Access\Actions\RevokeDevice;
use App\Domain\Access\Models\Device;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Layar 10-access §6.6 — perangkat terdaftar untuk PWA (Blueprint §11).
 *
 * User dengan `device.view` melihat perangkat seluruh company; user biasa hanya
 * perangkatnya sendiri. Mencabut perangkat tidak menghapus datanya (P-03).
 */
class DeviceList extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Device::class);
    }

    public function updated(string $property): void
    {
        if ($property === 'search' || str_ends_with($property, 'Filter')) {
            $this->resetPage();
        }
    }

    /** Hanya pemegang `device.view` yang boleh melihat perangkat user lain. */
    public function bolehLihatSemua(): bool
    {
        return auth()->user()?->hasPermission('device.view') ?? false;
    }

    public function cabut(int $deviceId, RevokeDevice $action): void
    {
        $device = Device::findOrFail($deviceId);

        $this->authorize('revoke', $device);

        $action->handle($device, auth()->user());

        $this->dispatch('pesan', teks: 'Perangkat dicabut.');
    }

    public function aktifkan(int $deviceId, RevokeDevice $action): void
    {
        $device = Device::findOrFail($deviceId);

        $this->authorize('revoke', $device);

        $action->reactivate($device, auth()->user());

        $this->dispatch('pesan', teks: 'Perangkat diaktifkan kembali.');
    }

    public function render(): View
    {
        return view('livewire.access.device-list', [
            'devices' => $this->devices(),
            'lihatSemua' => $this->bolehLihatSemua(),
        ]);
    }

    private function devices(): LengthAwarePaginator
    {
        return Device::query()
            ->with('user')
            ->unless($this->bolehLihatSemua(), fn (Builder $q) => $q->where('user_id', auth()->id()))
            ->when($this->search !== '', function (Builder $q): void {
                $cari = '%'.$this->search.'%';
                $q->where(function (Builder $s) use ($cari): void {
                    $s->where('name', 'like', $cari)
                        ->orWhere('device_uid', 'like', $cari)
                        ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $cari));
                });
            })
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('is_active', $this->statusFilter === 'aktif'))
            ->orderByDesc('last_seen_at')
            ->orderBy('id')
            ->paginate(15);
    }
}

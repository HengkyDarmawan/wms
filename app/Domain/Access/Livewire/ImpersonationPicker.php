<?php

declare(strict_types=1);

namespace App\Domain\Access\Livewire;

use App\Domain\Access\Models\Role;
use App\Domain\Access\Support\DemoFlows;
use App\Domain\Access\Support\Impersonation;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Layar 10-access §6.8 — "Masuk sebagai" (A-260): panduan alur demo per peran
 * dan kartu semua pengguna. Komponen ini hanya menampilkan; perpindahan user
 * dijalankan form POST ke `impersonate.store` (transisi bukan lewat GET).
 */
class ImpersonationPicker extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'role', except: '')]
    public string $roleFilter = '';

    public function mount(): void
    {
        abort_unless(Impersonation::enabled(), 404);
        // Saat sedang "masuk sebagai", yang diperiksa adalah Admin asli.
        abort_unless(Impersonation::allowed(), 403);
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'roleFilter']);
    }

    public function render(): View
    {
        $roles = Role::query()->where('is_active', true)->orderBy('id')->get();
        abort_unless(Impersonation::allowed(), 403);
        $kartu = DemoFlows::candidates(Impersonation::actor());

        $cari = mb_strtolower(trim($this->search));
        $tampil = $kartu
            ->when($cari !== '', fn (Collection $c) => $c->filter(fn (array $k) => str_contains(mb_strtolower($k['user']->name.' '.$k['user']->email), $cari)))
            ->when($this->roleFilter !== '', fn (Collection $c) => $c->filter(fn (array $k) => in_array($this->roleFilter, $k['codes'], true)))
            // Yang bisa dipilih lebih dulu, lalu urut nama.
            ->sortBy([fn ($a, $b) => ($a['reason'] !== null) <=> ($b['reason'] !== null), fn ($a, $b) => strcmp($a['user']->name, $b['user']->name)])
            ->values();

        return view('livewire.access.impersonation-picker', [
            'flows' => DemoFlows::resolve($kartu->whereNull('reason')->values(), $roles->pluck('name', 'code')->all()),
            'cards' => $tampil,
            'roles' => $roles->filter(fn (Role $r) => $kartu->contains(fn (array $k) => in_array($r->code, $k['codes'], true)))->values(),
            'total' => $kartu->count(),
        ]);
    }
}

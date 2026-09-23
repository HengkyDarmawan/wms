<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Livewire;

use App\Domain\Warehouse\Actions\SaveWarehouseType;
use App\Domain\Warehouse\Livewire\Concerns\HandlesWarehouseRules;
use App\Domain\Warehouse\Models\WarehouseType;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Layar 12-warehouse §6 — master tipe gudang.
 *
 * Tipe bawaan (`MAIN`, `BRANCH`, `SITE`) boleh diubah namanya tetapi kodenya
 * dikunci, karena BR-WH-04 memakai kode `SITE` untuk menentukan gudang proyek.
 */
class WarehouseTypeList extends Component
{
    use HandlesWarehouseRules;

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    /** @var array<string, string> */
    public array $form = [
        'code' => '',
        'name' => '',
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', WarehouseType::class);
    }

    public function buat(): void
    {
        $this->authorize('create', WarehouseType::class);

        $this->resetValidation();
        $this->ruleError = '';
        $this->editingId = null;
        $this->form = ['code' => '', 'name' => ''];
        $this->showForm = true;
    }

    public function ubah(int $id): void
    {
        $type = WarehouseType::findOrFail($id);

        $this->authorize('update', $type);

        $this->resetValidation();
        $this->ruleError = '';
        $this->editingId = $type->id;
        $this->form = ['code' => (string) $type->code, 'name' => (string) $type->name];
        $this->showForm = true;
    }

    public function simpan(SaveWarehouseType $action): void
    {
        $type = $this->editingId === null ? null : WarehouseType::findOrFail($this->editingId);

        $this->authorize($type === null ? 'create' : 'update', $type ?? WarehouseType::class);

        $this->validate([
            'form.code' => ['required', 'string', 'max:20'],
            'form.name' => ['required', 'string', 'max:60'],
        ], attributes: [
            'form.code' => __('Kode tipe'),
            'form.name' => __('Nama tipe'),
        ]);

        $berhasil = $this->jalankan(fn () => $action->handle($type, $this->form, auth()->user()));

        if (! $berhasil) {
            return;
        }

        $this->showForm = false;
        $this->dispatch('pesan', teks: __('Tipe gudang disimpan.'));
    }

    public function batalForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
        $this->ruleError = '';
    }

    public function nonaktifkan(int $id, SaveWarehouseType $action): void
    {
        $type = WarehouseType::findOrFail($id);

        $this->authorize('deactivate', $type);

        if ($this->jalankan(fn () => $action->deactivate($type, auth()->user()))) {
            $this->dispatch('pesan', teks: __('Tipe gudang dinonaktifkan.'));
        }
    }

    public function aktifkan(int $id, SaveWarehouseType $action): void
    {
        $type = WarehouseType::findOrFail($id);

        $this->authorize('update', $type);

        $action->reactivate($type, auth()->user());

        $this->dispatch('pesan', teks: __('Tipe gudang diaktifkan kembali.'));
    }

    public function render(): View
    {
        return view('livewire.warehouse.type-list', [
            'types' => WarehouseType::query()->withCount('warehouses')->orderBy('name')->get(),
        ]);
    }
}

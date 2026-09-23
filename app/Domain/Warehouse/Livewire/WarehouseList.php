<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Livewire;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Project;
use App\Domain\Warehouse\Actions\DeactivateWarehouse;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Livewire\Concerns\HandlesWarehouseRules;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Layar 12-warehouse §6 — pohon gudang beserta formnya.
 *
 * Daftar dibatasi cakupan penugasan role lewat global scope pada model
 * (BR-ACC-05), jadi komponen ini tidak perlu menyaring sendiri.
 */
class WarehouseList extends Component
{
    use HandlesWarehouseRules;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $typeFilter = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [
        'code' => '',
        'name' => '',
        'warehouse_type_id' => '',
        'parent_id' => '',
        'project_id' => '',
        'head_user_id' => '',
        'address' => '',
    ];

    #[Locked]
    public ?int $deactivatingId = null;

    public string $reasonCode = '';

    public string $reasonNotes = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Warehouse::class);
    }

    public function buat(): void
    {
        $this->authorize('create', Warehouse::class);

        $this->resetValidation();
        $this->ruleError = '';
        $this->editingId = null;
        $this->form = array_map(fn () => '', $this->form);
        $this->showForm = true;
    }

    public function ubah(int $id): void
    {
        $gudang = Warehouse::findOrFail($id);

        $this->authorize('update', $gudang);

        $this->resetValidation();
        $this->ruleError = '';
        $this->editingId = $gudang->id;
        $this->form = [
            'code' => (string) $gudang->code,
            'name' => (string) $gudang->name,
            'warehouse_type_id' => (string) $gudang->warehouse_type_id,
            'parent_id' => (string) $gudang->parent_id,
            'project_id' => (string) $gudang->project_id,
            'head_user_id' => (string) $gudang->head_user_id,
            'address' => (string) $gudang->address,
        ];
        $this->showForm = true;
    }

    public function simpan(SaveWarehouse $action): void
    {
        $gudang = $this->editingId === null ? null : Warehouse::findOrFail($this->editingId);

        $this->authorize($gudang === null ? 'create' : 'update', $gudang ?? Warehouse::class);

        $this->validate([
            'form.code' => ['required', 'string', 'max:10'],
            'form.name' => ['required', 'string', 'max:100'],
            'form.warehouse_type_id' => ['required', 'integer'],
            'form.parent_id' => ['nullable', 'integer'],
            'form.project_id' => ['nullable', 'integer'],
            'form.head_user_id' => ['nullable', 'integer'],
        ], attributes: [
            'form.code' => __('Kode gudang'),
            'form.name' => __('Nama gudang'),
            'form.warehouse_type_id' => __('Tipe gudang'),
        ]);

        $berhasil = $this->jalankan(fn () => $action->handle($gudang, $this->form, auth()->user()));

        if (! $berhasil) {
            return;
        }

        $this->showForm = false;
        $this->dispatch('pesan', teks: __('Gudang disimpan.'));
    }

    public function batalForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
        $this->ruleError = '';
    }

    public function mintaNonaktif(int $id): void
    {
        $gudang = Warehouse::findOrFail($id);

        $this->authorize('deactivate', $gudang);

        $this->ruleError = '';
        $this->deactivatingId = $gudang->id;
        $this->reasonCode = '';
        $this->reasonNotes = '';
    }

    public function nonaktifkan(DeactivateWarehouse $action): void
    {
        $gudang = Warehouse::findOrFail($this->deactivatingId);

        $this->authorize('deactivate', $gudang);

        $this->validate(['reasonCode' => ['required', 'string']], attributes: ['reasonCode' => __('Alasan')]);

        $berhasil = $this->jalankan(
            fn () => $action->handle($gudang, $this->reasonCode, $this->reasonNotes ?: null, auth()->user()),
        );

        if (! $berhasil) {
            return;
        }

        $this->deactivatingId = null;
        $this->dispatch('pesan', teks: __('Gudang dinonaktifkan.'));
    }

    public function batalNonaktif(): void
    {
        $this->deactivatingId = null;
        $this->ruleError = '';
        $this->resetValidation();
    }

    public function aktifkan(int $id, DeactivateWarehouse $action): void
    {
        $gudang = Warehouse::findOrFail($id);

        $this->authorize('update', $gudang);

        $action->reactivate($gudang, auth()->user());

        $this->dispatch('pesan', teks: __('Gudang diaktifkan kembali.'));
    }

    public function render(): View
    {
        $semua = Warehouse::query()
            ->with('type:id,code,name', 'project:id,code,name', 'head:id,name')
            ->withCount(['bins', 'zones'])
            ->when($this->search !== '', function (Builder $q): void {
                $cari = '%'.$this->search.'%';
                $q->where(fn (Builder $s) => $s->where('name', 'like', $cari)->orWhere('code', 'like', $cari));
            })
            ->when($this->typeFilter !== '', fn (Builder $q) => $q->where('warehouse_type_id', (int) $this->typeFilter))
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('is_active', $this->statusFilter === 'aktif'))
            ->orderBy('code')
            ->get();

        return view('livewire.warehouse.warehouse-list', [
            'pohon' => $this->pohon($semua, null),
            'semua' => $semua,
            'types' => WarehouseType::query()->active()->orderBy('name')->get(['id', 'code', 'name']),
            'projects' => Project::query()->active()->orderBy('name')->get(['id', 'code', 'name']),
            'heads' => User::query()->internal()->active()->orderBy('name')->get(['id', 'name']),
            'alasan' => $this->pilihanAlasan(),
        ]);
    }

    /**
     * Daftar datar bertingkat supaya pohon bisa digambar satu tabel.
     *
     * Gudang yang induknya berada di luar cakupan user tetap ditampilkan di
     * tingkat atas, bukan hilang, supaya daftarnya tidak berlubang.
     *
     * @param  Collection<int, Warehouse>  $semua
     * @return array<int, array{gudang: Warehouse, level: int}>
     */
    private function pohon(Collection $semua, ?int $parentId, int $level = 0): array
    {
        if ($level > 8) {
            return [];
        }

        $terlihat = $semua->pluck('id')->map(fn ($id) => (int) $id)->all();

        $anak = $semua->filter(function (Warehouse $w) use ($parentId, $level, $terlihat): bool {
            $induk = $w->parent_id === null ? null : (int) $w->parent_id;

            if ($level === 0) {
                // Induk di luar cakupan user dianggap tidak ada, supaya gudang
                // yang boleh dilihat tidak hilang dari daftar.
                return $induk === null || ! in_array($induk, $terlihat, true);
            }

            return $induk === $parentId;
        });

        $hasil = [];

        foreach ($anak as $gudang) {
            $hasil[] = ['gudang' => $gudang, 'level' => $level];
            $hasil = array_merge($hasil, $this->pohon($semua, (int) $gudang->id, $level + 1));
        }

        return $hasil;
    }

}

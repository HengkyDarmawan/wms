<?php

declare(strict_types=1);

namespace App\Domain\Master\Livewire;

use App\Domain\Master\Actions\DeactivateItemCategory;
use App\Domain\Master\Actions\SaveItemCategory;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Enums\RemovalStrategy;
use App\Domain\Master\Livewire\Concerns\HandlesMasterRules;
use App\Domain\Master\Models\ItemCategory;
use App\Domain\Master\Models\StorageCategory;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Layar 11-master §6 — pohon kategori item beserta nilai bawaan yang diwarisi:
 * kategori penyimpanan, strategi pengambilan, dan ambang toleransi opname
 * (BR-OPN-04).
 */
class ItemCategoryList extends Component
{
    use HandlesMasterRules;

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [
        'code' => '',
        'name' => '',
        'parent_id' => '',
        'storage_category_id' => '',
        'removal_strategy' => '',
        'tolerance_pct' => '',
        'tolerance_abs' => '',
    ];

    #[Locked]
    public ?int $deactivatingId = null;

    public string $reasonCode = '';

    public string $reasonNotes = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ItemCategory::class);
    }

    public function buat(?int $parentId = null): void
    {
        $this->authorize('create', ItemCategory::class);

        $this->resetValidation();
        $this->ruleError = '';
        $this->editingId = null;
        $this->form = [
            'code' => '',
            'name' => '',
            'parent_id' => $parentId === null ? '' : (string) $parentId,
            'storage_category_id' => '',
            'removal_strategy' => '',
            'tolerance_pct' => '',
            'tolerance_abs' => '',
        ];
        $this->showForm = true;
    }

    public function ubah(int $id): void
    {
        $category = ItemCategory::findOrFail($id);

        $this->authorize('update', $category);

        $this->resetValidation();
        $this->ruleError = '';
        $this->editingId = $category->id;
        $this->form = [
            'code' => (string) $category->code,
            'name' => (string) $category->name,
            'parent_id' => (string) $category->parent_id,
            'storage_category_id' => (string) $category->storage_category_id,
            'removal_strategy' => $category->removal_strategy?->value ?? '',
            'tolerance_pct' => (string) $category->tolerance_pct,
            'tolerance_abs' => (string) $category->tolerance_abs,
        ];
        $this->showForm = true;
    }

    public function simpan(SaveItemCategory $action): void
    {
        $category = $this->editingId === null ? null : ItemCategory::findOrFail($this->editingId);

        $this->authorize($category === null ? 'create' : 'update', $category ?? ItemCategory::class);

        $this->validate([
            'form.code' => ['required', 'string', 'max:30'],
            'form.name' => ['required', 'string', 'max:100'],
            'form.removal_strategy' => ['nullable', Rule::enum(RemovalStrategy::class)],
            'form.tolerance_pct' => ['nullable', 'numeric', 'between:0,100'],
            'form.tolerance_abs' => ['nullable', 'numeric', 'min:0'],
        ], attributes: [
            'form.code' => __('Kode'),
            'form.name' => __('Nama kategori'),
            'form.tolerance_pct' => __('Toleransi persen'),
            'form.tolerance_abs' => __('Toleransi mutlak'),
        ]);

        $berhasil = $this->jalankan(fn () => $action->handle($category, $this->form, auth()->user()));

        if (! $berhasil) {
            return;
        }

        $this->showForm = false;
        $this->dispatch('pesan', teks: __('Kategori disimpan.'));
    }

    public function batalForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
        $this->ruleError = '';
    }

    public function mintaNonaktif(int $id): void
    {
        $category = ItemCategory::findOrFail($id);

        $this->authorize('deactivate', $category);

        $this->ruleError = '';
        $this->deactivatingId = $category->id;
        $this->reasonCode = '';
        $this->reasonNotes = '';
    }

    public function nonaktifkan(DeactivateItemCategory $action): void
    {
        $category = ItemCategory::findOrFail($this->deactivatingId);

        $this->authorize('deactivate', $category);

        $this->validate(['reasonCode' => ['required', 'string']], attributes: ['reasonCode' => __('Alasan')]);

        $berhasil = $this->jalankan(
            fn () => $action->handle($category, $this->reasonCode, $this->reasonNotes ?: null, auth()->user()),
        );

        if (! $berhasil) {
            return;
        }

        $this->deactivatingId = null;
        $this->dispatch('pesan', teks: __('Kategori dinonaktifkan.'));
    }

    public function batalNonaktif(): void
    {
        $this->deactivatingId = null;
        $this->ruleError = '';
        $this->resetValidation();
    }

    public function aktifkan(int $id, DeactivateItemCategory $action): void
    {
        $category = ItemCategory::findOrFail($id);

        $this->authorize('update', $category);

        $action->reactivate($category, auth()->user());

        $this->dispatch('pesan', teks: __('Kategori diaktifkan kembali.'));
    }

    public function render(): View
    {
        $semua = ItemCategory::query()
            ->with('storageCategory:id,name')
            ->withCount('items')
            ->orderBy('name')
            ->get();

        return view('livewire.master.item-category-list', [
            'pohon' => $this->pohon($semua, null),
            'semua' => $semua,
            'storageCategories' => StorageCategory::query()->active()->orderBy('name')->get(['id', 'name']),
            'strategies' => RemovalStrategy::options(),
            'alasan' => $this->pilihanAlasan(ReasonContext::Cancel),
        ]);
    }

    /**
     * Menyusun daftar datar bertingkat supaya pohon bisa digambar satu tabel.
     *
     * @param  Collection<int, ItemCategory>  $semua
     * @return array<int, array{kategori: ItemCategory, level: int}>
     */
    private function pohon(Collection $semua, ?int $parentId, int $level = 0): array
    {
        if ($level > 8) {
            return [];
        }

        $hasil = [];

        foreach ($semua->where('parent_id', $parentId) as $kategori) {
            $hasil[] = ['kategori' => $kategori, 'level' => $level];
            $hasil = array_merge($hasil, $this->pohon($semua, (int) $kategori->id, $level + 1));
        }

        return $hasil;
    }
}

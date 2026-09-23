<?php

declare(strict_types=1);

namespace App\Domain\Master\Livewire;

use App\Domain\Master\Actions\SaveUom;
use App\Domain\Master\Actions\SaveUomCategory;
use App\Domain\Master\Livewire\Concerns\HandlesMasterRules;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Models\UomCategory;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Layar 11-master §6 — kategori satuan beserta satuan di dalamnya.
 *
 * D-11: konversi hanya sah dalam satu kategori. BR-MST-03: setiap kategori
 * punya satu satuan acuan berfaktor 1, dan acuan itu tidak bisa dinonaktifkan.
 */
class UomList extends Component
{
    use HandlesMasterRules;

    #[Locked]
    public ?int $activeCategoryId = null;

    public bool $showCategoryForm = false;

    #[Locked]
    public ?int $editingCategoryId = null;

    /** @var array<string, string> */
    public array $categoryForm = [
        'code' => '',
        'name' => '',
        'reference_uom_id' => '',
        'reference_code' => '',
        'reference_name' => '',
    ];

    public bool $showUomForm = false;

    #[Locked]
    public ?int $editingUomId = null;

    /** @var array<string, string> */
    public array $form = [
        'code' => '',
        'name' => '',
        'uom_category_id' => '',
        'factor_to_reference' => '',
        'rounding' => '',
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', Uom::class);

        $this->activeCategoryId = UomCategory::query()->active()->orderBy('code')->value('id');
    }

    public function pilihKategori(int $id): void
    {
        $this->activeCategoryId = $id;
        $this->showUomForm = false;
        $this->showCategoryForm = false;
    }

    public function buatKategori(): void
    {
        $this->authorize('create', UomCategory::class);

        $this->resetValidation();
        $this->ruleError = '';
        $this->editingCategoryId = null;
        $this->categoryForm = [
            'code' => '',
            'name' => '',
            'reference_uom_id' => '',
            'reference_code' => '',
            'reference_name' => '',
        ];
        $this->showCategoryForm = true;
    }

    public function ubahKategori(int $id): void
    {
        $category = UomCategory::findOrFail($id);

        $this->authorize('update', $category);

        $this->resetValidation();
        $this->ruleError = '';
        $this->editingCategoryId = $category->id;
        $this->categoryForm = [
            'code' => (string) $category->code,
            'name' => (string) $category->name,
            'reference_uom_id' => (string) $category->reference_uom_id,
            'reference_code' => '',
            'reference_name' => '',
        ];
        $this->showCategoryForm = true;
    }

    public function simpanKategori(SaveUomCategory $action): void
    {
        $category = $this->editingCategoryId === null
            ? null
            : UomCategory::findOrFail($this->editingCategoryId);

        $this->authorize($category === null ? 'create' : 'update', $category ?? UomCategory::class);

        $this->validate([
            'categoryForm.code' => ['required', 'string', 'max:20'],
            'categoryForm.name' => ['required', 'string', 'max:60'],
        ], attributes: [
            'categoryForm.code' => __('Kode kategori'),
            'categoryForm.name' => __('Nama kategori'),
        ]);

        $acuan = null;

        if ($category === null) {
            $this->validate([
                'categoryForm.reference_code' => ['required', 'string', 'max:15'],
                'categoryForm.reference_name' => ['required', 'string', 'max:60'],
            ], attributes: [
                'categoryForm.reference_code' => __('Kode satuan acuan'),
                'categoryForm.reference_name' => __('Nama satuan acuan'),
            ]);

            $acuan = [
                'code' => $this->categoryForm['reference_code'],
                'name' => $this->categoryForm['reference_name'],
            ];
        }

        $tersimpan = null;

        $berhasil = $this->jalankan(function () use ($action, $category, $acuan, &$tersimpan): void {
            $tersimpan = $action->handle($category, $this->categoryForm, $acuan, auth()->user());
        }, 'categoryForm');

        if (! $berhasil) {
            return;
        }

        $this->activeCategoryId = $tersimpan->id;
        $this->showCategoryForm = false;
        $this->dispatch('pesan', teks: __('Kategori satuan disimpan.'));
    }

    public function batalKategori(): void
    {
        $this->showCategoryForm = false;
        $this->resetValidation();
        $this->ruleError = '';
    }

    public function buatSatuan(): void
    {
        $this->authorize('create', Uom::class);

        $this->resetValidation();
        $this->ruleError = '';
        $this->editingUomId = null;
        $this->form = [
            'code' => '',
            'name' => '',
            'uom_category_id' => (string) $this->activeCategoryId,
            'factor_to_reference' => '',
            'rounding' => '',
        ];
        $this->showUomForm = true;
    }

    public function ubahSatuan(int $id): void
    {
        $uom = Uom::findOrFail($id);

        $this->authorize('update', $uom);

        $this->resetValidation();
        $this->ruleError = '';
        $this->editingUomId = $uom->id;
        $this->form = [
            'code' => (string) $uom->code,
            'name' => (string) $uom->name,
            'uom_category_id' => (string) $uom->uom_category_id,
            'factor_to_reference' => (string) $uom->factor_to_reference,
            'rounding' => (string) $uom->rounding,
        ];
        $this->showUomForm = true;
    }

    public function simpanSatuan(SaveUom $action): void
    {
        $uom = $this->editingUomId === null ? null : Uom::findOrFail($this->editingUomId);

        $this->authorize($uom === null ? 'create' : 'update', $uom ?? Uom::class);

        $this->validate([
            'form.code' => ['required', 'string', 'max:15'],
            'form.name' => ['required', 'string', 'max:60'],
            'form.uom_category_id' => ['required'],
            'form.factor_to_reference' => ['required', 'numeric', 'gt:0'],
            'form.rounding' => ['nullable', 'numeric', 'min:0'],
        ], attributes: [
            'form.code' => __('Kode satuan'),
            'form.name' => __('Nama satuan'),
            'form.uom_category_id' => __('Kategori satuan'),
            'form.factor_to_reference' => __('Faktor ke satuan acuan'),
        ]);

        $berhasil = $this->jalankan(fn () => $action->handle($uom, $this->form, auth()->user()));

        if (! $berhasil) {
            return;
        }

        $this->showUomForm = false;
        $this->dispatch('pesan', teks: __('Satuan disimpan.'));
    }

    public function batalSatuan(): void
    {
        $this->showUomForm = false;
        $this->resetValidation();
        $this->ruleError = '';
    }

    public function nonaktifkanSatuan(int $id, SaveUom $action): void
    {
        $uom = Uom::findOrFail($id);

        $this->authorize('deactivate', $uom);

        $berhasil = $this->jalankan(fn () => $action->deactivate($uom, auth()->user()));

        if ($berhasil) {
            $this->dispatch('pesan', teks: __('Satuan dinonaktifkan.'));
        }
    }

    public function aktifkanSatuan(int $id, SaveUom $action): void
    {
        $uom = Uom::findOrFail($id);

        $this->authorize('update', $uom);

        $action->reactivate($uom, auth()->user());

        $this->dispatch('pesan', teks: __('Satuan diaktifkan kembali.'));
    }

    public function render(): View
    {
        $kategoriAktif = $this->activeCategoryId === null
            ? null
            : UomCategory::query()->with('referenceUom')->find($this->activeCategoryId);

        return view('livewire.master.uom-list', [
            'categories' => UomCategory::query()->withCount('uoms')->orderBy('code')->get(),
            'kategoriAktif' => $kategoriAktif,
            'uoms' => $kategoriAktif === null
                ? collect()
                : $kategoriAktif->uoms()->orderByDesc('factor_to_reference')->get(),
        ]);
    }
}

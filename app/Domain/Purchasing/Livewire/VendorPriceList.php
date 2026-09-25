<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Livewire;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Vendor;
use App\Domain\Purchasing\Actions\SaveVendorPrice;
use App\Domain\Purchasing\Livewire\Concerns\HandlesPurchasingRules;
use App\Domain\Purchasing\Models\VendorPrice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Layar purchasing/02 §6 — harga beli vendor × item (A-211): daftar harga
 * aktif, tambah harga baru (menggantikan harga bertanggal sama), nonaktifkan.
 */
class VendorPriceList extends Component
{
    use HandlesPurchasingRules;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'vendor', except: '')]
    public string $vendorFilter = '';

    public bool $semua = false;

    public bool $formTerbuka = false;

    /** @var array<string, string> */
    public array $form = ['vendor_id' => '', 'item_id' => '', 'unit_price' => '', 'valid_from' => '', 'notes' => ''];

    public function mount(): void
    {
        $this->authorize('viewAny', VendorPrice::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'vendorFilter', 'semua'], true)) {
            $this->resetPage();
        }
    }

    public function buat(): void
    {
        $this->authorize('create', VendorPrice::class);

        $this->form = ['vendor_id' => $this->vendorFilter, 'item_id' => '', 'unit_price' => '', 'valid_from' => now()->toDateString(), 'notes' => ''];
        $this->formTerbuka = true;
        $this->resetValidation();
    }

    public function batalForm(): void
    {
        $this->formTerbuka = false;
        $this->resetValidation();
    }

    public function simpan(SaveVendorPrice $action): void
    {
        $this->authorize('create', VendorPrice::class);

        $this->validate([
            'form.vendor_id' => ['required'],
            'form.item_id' => ['required'],
            'form.unit_price' => ['required', 'numeric', 'gt:0'],
            'form.valid_from' => ['required', 'date'],
            'form.notes' => ['nullable', 'max:255'],
        ], attributes: [
            'form.vendor_id' => __('Vendor'),
            'form.item_id' => __('Item'),
            'form.unit_price' => __('Harga satuan'),
            'form.valid_from' => __('Berlaku mulai'),
        ]);

        if ($this->jalankan(fn () => $action->handle($this->form, auth()->user()))) {
            $this->formTerbuka = false;
            $this->dispatch('pesan', teks: __('Harga beli tersimpan.'));
        }
    }

    public function nonaktifkan(int $id, SaveVendorPrice $action): void
    {
        $harga = VendorPrice::query()->findOrFail($id);
        $this->authorize('update', $harga);

        $action->deactivate($harga, auth()->user());
        $this->dispatch('pesan', teks: __('Harga dinonaktifkan.'));
    }

    public function render(): View
    {
        return view('livewire.purchasing.vendor-price-list', [
            'prices' => $this->daftar(),
            'vendors' => Vendor::query()->where('status', '!=', VendorStatus::Inactive->value)->orderBy('name')->get(['id', 'code', 'name']),
            'items' => $this->formTerbuka ? Item::query()->where('status', ItemStatus::Active->value)->orderBy('code')->get(['id', 'code', 'name']) : collect(),
        ]);
    }

    private function daftar(): LengthAwarePaginator
    {
        return VendorPrice::query()
            ->with('vendor:id,code,name', 'item:id,code,name,base_uom_id', 'item.baseUom:id,code', 'creator:id,name')
            ->when(! $this->semua, fn (Builder $q) => $q->where('is_active', true))
            ->when($this->vendorFilter !== '', fn (Builder $q) => $q->where('vendor_id', (int) $this->vendorFilter))
            ->when($this->search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereHas('item', fn (Builder $i) => $i->where('code', 'like', '%'.$this->search.'%')->orWhere('name', 'like', '%'.$this->search.'%'))
                ->orWhereHas('vendor', fn (Builder $v) => $v->where('name', 'like', '%'.$this->search.'%'))))
            ->orderBy('item_id')->orderBy('vendor_id')->orderByDesc('valid_from')->orderByDesc('id')
            ->paginate(30);
    }
}

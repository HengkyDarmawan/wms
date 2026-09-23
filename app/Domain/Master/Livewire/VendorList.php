<?php

declare(strict_types=1);

namespace App\Domain\Master\Livewire;

use App\Domain\Master\Actions\DeactivateVendor;
use App\Domain\Master\Actions\SaveVendor;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Enums\VendorType;
use App\Domain\Master\Livewire\Concerns\HandlesMasterRules;
use App\Domain\Master\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Layar 11-master §6 — daftar dan form vendor.
 *
 * A-52 membedakan perusahaan, toko, lapak marketplace, dan perorangan.
 * A-53: vendor yang dibuat mendadak saat memesan berstatus Sementara dan
 * ditandai di daftar sampai Admin melengkapi datanya. Tanpa harga (D-07).
 */
class VendorList extends Component
{
    use HandlesMasterRules;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: '')]
    public string $typeFilter = '';

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    /** @var array<string, string> */
    public array $form = [
        'code' => '',
        'name' => '',
        'vendor_type' => 'company',
        'status' => 'active',
        'tax_id' => '',
        'contact_name' => '',
        'phone' => '',
        'email' => '',
        'address' => '',
        'payment_terms' => '',
    ];

    #[Locked]
    public ?int $deactivatingId = null;

    public string $reasonCode = '';

    public string $reasonNotes = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Vendor::class);
    }

    public function updated(string $property): void
    {
        if ($property === 'search' || str_ends_with($property, 'Filter')) {
            $this->resetPage();
        }
    }

    public function buat(): void
    {
        $this->authorize('create', Vendor::class);

        $this->resetValidation();
        $this->ruleError = '';
        $this->editingId = null;
        $this->form = [
            'code' => '',
            'name' => '',
            'vendor_type' => VendorType::Company->value,
            'status' => VendorStatus::Active->value,
            'tax_id' => '',
            'contact_name' => '',
            'phone' => '',
            'email' => '',
            'address' => '',
            'payment_terms' => '',
        ];
        $this->showForm = true;
    }

    public function ubah(int $id): void
    {
        $vendor = Vendor::findOrFail($id);

        $this->authorize('update', $vendor);

        $this->resetValidation();
        $this->ruleError = '';
        $this->editingId = $vendor->id;
        $this->form = [
            'code' => (string) $vendor->code,
            'name' => (string) $vendor->name,
            'vendor_type' => $vendor->vendor_type->value,
            'status' => $vendor->status->value,
            'tax_id' => (string) $vendor->tax_id,
            'contact_name' => (string) $vendor->contact_name,
            'phone' => (string) $vendor->phone,
            'email' => (string) $vendor->email,
            'address' => (string) $vendor->address,
            'payment_terms' => (string) $vendor->payment_terms,
        ];
        $this->showForm = true;
    }

    public function simpan(SaveVendor $action): void
    {
        $vendor = $this->editingId === null ? null : Vendor::findOrFail($this->editingId);

        $this->authorize($vendor === null ? 'create' : 'update', $vendor ?? Vendor::class);

        $this->validate([
            'form.name' => ['required', 'string', 'max:150'],
            'form.code' => ['nullable', 'string', 'max:30'],
            'form.vendor_type' => ['required', Rule::enum(VendorType::class)],
            'form.status' => ['required', Rule::enum(VendorStatus::class)],
            'form.phone' => ['nullable', 'string', 'max:20'],
            'form.email' => ['nullable', 'email', 'max:150'],
            'form.payment_terms' => ['nullable', 'string', 'max:60'],
        ], attributes: [
            'form.name' => __('Nama vendor'),
            'form.vendor_type' => __('Jenis vendor'),
            'form.status' => __('Status'),
            'form.email' => __('Email'),
        ]);

        $berhasil = $this->jalankan(fn () => $action->handle($vendor, $this->form, auth()->user()));

        if (! $berhasil) {
            return;
        }

        $this->showForm = false;
        $this->dispatch('pesan', teks: __('Vendor disimpan.'));
    }

    public function batalForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
        $this->ruleError = '';
    }

    public function mintaNonaktif(int $id): void
    {
        $vendor = Vendor::findOrFail($id);

        $this->authorize('deactivate', $vendor);

        $this->ruleError = '';
        $this->deactivatingId = $vendor->id;
        $this->reasonCode = '';
        $this->reasonNotes = '';
    }

    public function nonaktifkan(DeactivateVendor $action): void
    {
        $vendor = Vendor::findOrFail($this->deactivatingId);

        $this->authorize('deactivate', $vendor);

        $this->validate(['reasonCode' => ['required', 'string']], attributes: ['reasonCode' => __('Alasan')]);

        $berhasil = $this->jalankan(
            fn () => $action->handle($vendor, $this->reasonCode, $this->reasonNotes ?: null, auth()->user()),
        );

        if (! $berhasil) {
            return;
        }

        $this->deactivatingId = null;
        $this->dispatch('pesan', teks: __('Vendor dinonaktifkan.'));
    }

    public function batalNonaktif(): void
    {
        $this->deactivatingId = null;
        $this->ruleError = '';
        $this->resetValidation();
    }

    public function aktifkan(int $id, DeactivateVendor $action): void
    {
        $vendor = Vendor::findOrFail($id);

        $this->authorize('update', $vendor);

        $action->reactivate($vendor, auth()->user());

        $this->dispatch('pesan', teks: __('Vendor diaktifkan kembali.'));
    }

    public function render(): View
    {
        return view('livewire.master.vendor-list', [
            'vendors' => $this->vendors(),
            'jenis' => VendorType::options(),
            'statuses' => VendorStatus::options(),
            'alasan' => $this->pilihanAlasan(ReasonContext::Cancel),
        ]);
    }

    private function vendors(): LengthAwarePaginator
    {
        return Vendor::query()
            ->withCount('itemVendors')
            ->when($this->search !== '', function (Builder $q): void {
                $cari = '%'.$this->search.'%';
                $q->where(fn (Builder $s) => $s->where('name', 'like', $cari)
                    ->orWhere('code', 'like', $cari)
                    ->orWhere('contact_name', 'like', $cari));
            })
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter !== '', fn (Builder $q) => $q->where('vendor_type', $this->typeFilter))
            ->orderBy('name')
            ->paginate(15);
    }
}

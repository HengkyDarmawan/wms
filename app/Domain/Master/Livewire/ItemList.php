<?php

declare(strict_types=1);

namespace App\Domain\Master\Livewire;

use App\Domain\Master\Actions\DeactivateItem;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Livewire\Concerns\HandlesMasterRules;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\ItemCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Layar 11-master §6 — daftar item dengan filter kategori, mode pelacakan,
 * kepemilikan, dan status. Item Sementara (A-51, BR-REQ-03) ditandai agar
 * Admin tahu mana yang masih harus dilengkapi.
 */
class ItemList extends Component
{
    use HandlesMasterRules;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $categoryFilter = '';

    #[Url(except: '')]
    public string $trackingFilter = '';

    #[Url(except: '')]
    public string $ownershipFilter = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Locked]
    public ?int $deactivatingId = null;

    public string $reasonCode = '';

    public string $reasonNotes = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Item::class);
    }

    public function updated(string $property): void
    {
        if ($property === 'search' || str_ends_with($property, 'Filter')) {
            $this->resetPage();
        }
    }

    public function mintaNonaktif(int $id): void
    {
        $item = Item::findOrFail($id);

        $this->authorize('deactivate', $item);

        $this->ruleError = '';
        $this->deactivatingId = $item->id;
        $this->reasonCode = '';
        $this->reasonNotes = '';
    }

    public function nonaktifkan(DeactivateItem $action): void
    {
        $item = Item::findOrFail($this->deactivatingId);

        $this->authorize('deactivate', $item);

        $this->validate(['reasonCode' => ['required', 'string']], attributes: ['reasonCode' => __('Alasan')]);

        $berhasil = $this->jalankan(
            fn () => $action->handle($item, $this->reasonCode, $this->reasonNotes ?: null, auth()->user()),
        );

        if (! $berhasil) {
            return;
        }

        $this->deactivatingId = null;
        $this->dispatch('pesan', teks: __('Item dinonaktifkan.'));
    }

    public function batalNonaktif(): void
    {
        $this->deactivatingId = null;
        $this->ruleError = '';
        $this->resetValidation();
    }

    /** Juga dipakai untuk meresmikan item Sementara (BR-REQ-03). */
    public function aktifkan(int $id, DeactivateItem $action): void
    {
        $item = Item::findOrFail($id);

        $this->authorize('update', $item);

        $action->reactivate($item, auth()->user());

        $this->dispatch('pesan', teks: __('Item diaktifkan.'));
    }

    public function render(): View
    {
        return view('livewire.master.item-list', [
            'items' => $this->items(),
            'categories' => ItemCategory::query()->active()->orderBy('name')->get(['id', 'name']),
            'trackingModes' => TrackingMode::options(),
            'ownerships' => OwnershipModel::options(),
            'statuses' => ItemStatus::options(),
            'alasan' => $this->pilihanAlasan(ReasonContext::Cancel),
        ]);
    }

    private function items(): LengthAwarePaginator
    {
        return Item::query()
            ->with('category:id,name', 'baseUom:id,code,name')
            ->when($this->search !== '', function (Builder $q): void {
                $cari = '%'.$this->search.'%';
                $q->where(fn (Builder $s) => $s->where('name', 'like', $cari)
                    ->orWhere('code', 'like', $cari)
                    ->orWhere('barcode', 'like', $cari));
            })
            ->when($this->categoryFilter !== '', fn (Builder $q) => $q->where('item_category_id', (int) $this->categoryFilter))
            ->when($this->trackingFilter !== '', fn (Builder $q) => $q->where('tracking_mode', $this->trackingFilter))
            ->when($this->ownershipFilter !== '', fn (Builder $q) => $q->where('ownership_model', $this->ownershipFilter))
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->orderBy('name')
            ->paginate(20);
    }
}

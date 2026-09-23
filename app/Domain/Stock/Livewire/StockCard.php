<?php

declare(strict_types=1);

namespace App\Domain\Stock\Livewire;

use App\Domain\Master\Models\Item;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Layar 13-stock §6 — kartu stok satu item.
 *
 * Dua bagian: saldo per bin saat ini, dan riwayat pergerakan yang membentuknya.
 * Riwayat inilah bukti yang dipakai saat angka dipertanyakan, jadi setiap baris
 * menyebut dokumen asalnya dan siapa yang mencatat.
 */
class StockCard extends Component
{
    use WithPagination;

    #[Locked]
    public int $itemId;

    #[Url(except: '')]
    public string $warehouseFilter = '';

    #[Url(except: '')]
    public string $binFilter = '';

    #[Url(except: '')]
    public string $dariTanggal = '';

    #[Url(except: '')]
    public string $sampaiTanggal = '';

    public function mount(Item $item): void
    {
        $this->authorize('viewAny', StockBalance::class);

        $this->itemId = (int) $item->id;
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }

        // Mengganti gudang membuat pilihan bin lama tidak lagi masuk akal.
        if ($property === 'warehouseFilter') {
            $this->binFilter = '';
        }
    }

    public function render(): View
    {
        $item = Item::query()->with('baseUom:id,code')->findOrFail($this->itemId);

        return view('livewire.stock.stock-card', [
            'item' => $item,
            'saldo' => $this->saldoPerBin(),
            'pergerakan' => $this->pergerakan(),
            'warehouses' => Warehouse::query()->orderBy('code')->get(['id', 'code', 'name']),
            'bins' => $this->pilihanBin(),
        ]);
    }

    /** @return Collection<int, StockBalance> */
    private function saldoPerBin(): Collection
    {
        return StockBalance::query()
            ->with('bin:id,code,warehouse_id,bin_type', 'bin.warehouse:id,code,name', 'lot:id,lot_no', 'serial:id,serial_no')
            ->where('item_id', $this->itemId)
            ->nonZero()
            ->when($this->warehouseFilter !== '', fn (Builder $q) => $q
                ->inWarehouse((int) $this->warehouseFilter))
            ->when($this->binFilter !== '', fn (Builder $q) => $q->where('bin_id', (int) $this->binFilter))
            ->join('bins as b', 'b.id', '=', 'stock_balances.bin_id')
            ->orderBy('b.code')
            ->select('stock_balances.*')
            ->get();
    }

    private function pergerakan(): LengthAwarePaginator
    {
        $bin = $this->binFilter !== '' ? (int) $this->binFilter : null;
        $gudang = $this->warehouseFilter !== '' ? (int) $this->warehouseFilter : null;

        return StockMovement::query()
            ->with([
                'fromBin:id,code,warehouse_id', 'toBin:id,code,warehouse_id',
                'lot:id,lot_no', 'serial:id,serial_no', 'performer:id,name', 'reasonCode:id,code,name',
            ])
            ->where('item_id', $this->itemId)
            ->when($bin !== null, fn (Builder $q) => $q->touchingBin($bin))
            ->when($gudang !== null, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereHas('fromBin', fn (Builder $b) => $b->where('warehouse_id', $gudang))
                ->orWhereHas('toBin', fn (Builder $b) => $b->where('warehouse_id', $gudang))))
            ->when($this->dariTanggal !== '', fn (Builder $q) => $q
                ->whereDate('occurred_at', '>=', $this->dariTanggal))
            ->when($this->sampaiTanggal !== '', fn (Builder $q) => $q
                ->whereDate('occurred_at', '<=', $this->sampaiTanggal))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(30);
    }

    /** @return Collection<int, Bin> */
    private function pilihanBin(): Collection
    {
        return Bin::query()
            ->when($this->warehouseFilter !== '', fn (Builder $q) => $q
                ->where('warehouse_id', (int) $this->warehouseFilter))
            ->whereIn('id', StockBalance::query()->where('item_id', $this->itemId)->select('bin_id'))
            ->orderBy('code')
            ->get(['id', 'code']);
    }
}

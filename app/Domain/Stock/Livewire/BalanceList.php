<?php

declare(strict_types=1);

namespace App\Domain\Stock\Livewire;

use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Stock\Enums\ReservationStatus;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Layar 13-stock §6 — saldo per item dan gudang.
 *
 * Yang ditampilkan adalah agregat `stock_balances`, bukan penjumlahan kartu
 * stok saat layar dibuka: menjumlah ulang jutaan baris tiap kali halaman
 * dibuka tidak akan sanggup memenuhi NFR-01.
 *
 * Kolom *Tersedia* sudah dikurangi reservasi aktif (BR-STK-03), jadi angka di
 * layar ini adalah yang benar-benar bisa dijanjikan.
 */
class BalanceList extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $warehouseFilter = '';

    /** Menyembunyikan item bersaldo nol; defaultnya disembunyikan. */
    #[Url(except: false)]
    public bool $tampilkanNol = false;

    public function mount(): void
    {
        $this->authorize('viewAny', StockBalance::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'warehouseFilter', 'tampilkanNol'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $baris = $this->saldo();

        return view('livewire.stock.balance-list', [
            'baris' => $baris,
            'reservasi' => $this->reservasiPerBaris($baris),
            'warehouses' => Warehouse::query()->active()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    /**
     * Satu baris per item × gudang, dengan kondisi stok dijabarkan menjadi
     * kolom agar mata bisa membandingkan tanpa menggulir.
     */
    private function saldo(): LengthAwarePaginator
    {
        $gudang = Warehouse::query()->pluck('id')->all();

        return StockBalance::query()
            ->join('bins', 'bins.id', '=', 'stock_balances.bin_id')
            ->join('items', 'items.id', '=', 'stock_balances.item_id')
            ->join('warehouses', 'warehouses.id', '=', 'bins.warehouse_id')
            ->whereIn('bins.warehouse_id', $gudang)
            ->when($this->warehouseFilter !== '', fn (Builder $q) => $q
                ->where('bins.warehouse_id', (int) $this->warehouseFilter))
            ->when($this->search !== '', fn (Builder $q) => $q
                ->where(fn (Builder $w) => $w
                    ->where('items.code', 'like', '%'.$this->search.'%')
                    ->orWhere('items.name', 'like', '%'.$this->search.'%')
                    // Hasil pindai barcode item (A-201).
                    ->orWhere('items.barcode', $this->search)))
            ->unless($this->tampilkanNol, fn (Builder $q) => $q->havingRaw('SUM(stock_balances.qty_base) <> 0'))
            ->groupBy(
                'stock_balances.item_id', 'bins.warehouse_id',
                'items.code', 'items.name', 'items.tracking_mode',
                'warehouses.code', 'warehouses.name',
            )
            ->orderBy('items.code')
            ->orderBy('warehouses.code')
            ->select([
                'stock_balances.item_id',
                'bins.warehouse_id',
                'items.code as item_code',
                'items.name as item_name',
                'items.tracking_mode',
                'warehouses.code as warehouse_code',
                'warehouses.name as warehouse_name',
                DB::raw("SUM(CASE WHEN stock_balances.stock_status = 'available' THEN stock_balances.qty_base ELSE 0 END) as qty_available"),
                DB::raw("SUM(CASE WHEN stock_balances.stock_status = 'quarantine' THEN stock_balances.qty_base ELSE 0 END) as qty_quarantine"),
                DB::raw("SUM(CASE WHEN stock_balances.stock_status = 'damaged' THEN stock_balances.qty_base ELSE 0 END) as qty_damaged"),
                DB::raw('SUM(stock_balances.piece_count) as piece_count'),
            ])
            ->paginate(25);
    }

    /**
     * Reservasi aktif untuk baris yang sedang tampil saja.
     *
     * Dikumpulkan sekali dalam satu query untuk seluruh halaman, bukan per
     * baris, agar daftar sepanjang apa pun tetap dua query.
     *
     * @return Collection<string, float>
     */
    private function reservasiPerBaris(LengthAwarePaginator $baris): Collection
    {
        $items = collect($baris->items())->pluck('item_id')->unique()->all();

        if ($items === []) {
            return collect();
        }

        return StockReservation::query()
            ->where('status', ReservationStatus::Active->value)
            ->whereIn('item_id', $items)
            ->groupBy('item_id', 'warehouse_id')
            ->select('item_id', 'warehouse_id', DB::raw('SUM(qty_base) as qty'))
            ->get()
            ->mapWithKeys(fn ($r) => [$r->item_id.':'.$r->warehouse_id => (float) $r->qty]);
    }

    /** Hanya item per potong yang kolom potongannya bermakna (BR-STK-08). */
    public function pakaiPotongan(string $trackingMode): bool
    {
        return TrackingMode::tryFrom($trackingMode) === TrackingMode::Piece;
    }
}

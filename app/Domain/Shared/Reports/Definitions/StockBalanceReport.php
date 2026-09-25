<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Shared\Reports\Report;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Collection;

/**
 * Laporan *Saldo stok* (Blueprint §6.9a): per gudang, bin, lot/serial/potongan,
 * kondisi; potongan dengan jumlah potong + total panjang (BR-STK-09). Cakupan
 * gudang pengguna (BR-ACC-05). Tanpa nilai uang (D-07).
 */
class StockBalanceReport extends Report
{
    public function key(): string
    {
        return 'saldo-stok';
    }

    public function title(): string
    {
        return 'Saldo stok';
    }

    public function permission(): string
    {
        return 'stock.view';
    }

    public function description(): string
    {
        return 'Saldo per gudang, bin, lot/serial/potongan, dan kondisi; potongan disertai jumlah potong.';
    }

    public function columns(): array
    {
        return [
            'gudang' => 'Gudang', 'bin' => 'Bin', 'kode_item' => 'Kode item', 'nama_item' => 'Nama item',
            'pelacakan' => 'Lot / serial / potongan', 'kondisi' => 'Kondisi', 'jumlah' => 'Jumlah', 'satuan' => 'Satuan', 'potong' => 'Jumlah potong',
        ];
    }

    public function filters(): array
    {
        return [
            'warehouse_id' => ['label' => 'Gudang', 'options' => Warehouse::query()->orderBy('code')->get(['id', 'code', 'name'])
                ->mapWithKeys(fn (Warehouse $w) => [$w->id => $w->code.' — '.$w->name])->all()],
            'stock_status' => ['label' => 'Kondisi', 'options' => collect(StockStatus::cases())->mapWithKeys(fn (StockStatus $s) => [$s->value => $s->label()])->all()],
            'item' => ['label' => 'Kode item'],
        ];
    }

    public function rows(array $filters): Collection
    {
        $gudang = auth()->user()?->accessibleWarehouseIds();

        return StockBalance::query()
            ->with('item:id,code,name,base_uom_id', 'item.baseUom:id,code', 'bin:id,code,warehouse_id', 'bin.warehouse:id,code', 'lot:id,lot_no', 'serial:id,serial_no', 'piece:id,piece_no,length')
            ->where('qty_base', '>', 0)
            ->whereHas('bin', fn ($q) => $q->withoutGlobalScopes()
                ->when($gudang !== null, fn ($w) => $w->whereIn('warehouse_id', $gudang))
                ->when((int) ($filters['warehouse_id'] ?? 0) > 0, fn ($w) => $w->where('warehouse_id', (int) $filters['warehouse_id'])))
            ->when(($filters['stock_status'] ?? '') !== '', fn ($q) => $q->where('stock_status', $filters['stock_status']))
            ->when(trim((string) ($filters['item'] ?? '')) !== '', fn ($q) => $q->whereHas('item', fn ($i) => $i->where('code', 'like', '%'.trim((string) $filters['item']).'%')))
            ->get()
            ->sortBy(fn (StockBalance $b) => $b->bin?->warehouse?->code.'|'.$b->bin?->code.'|'.$b->item?->code)
            ->values()
            ->map(fn (StockBalance $b) => [
                'gudang' => $b->bin?->warehouse?->code,
                'bin' => $b->bin?->code,
                'kode_item' => $b->item?->code,
                'nama_item' => $b->item?->name,
                'pelacakan' => $b->lot?->lot_no ?? $b->serial?->serial_no ?? $b->piece?->piece_no,
                'kondisi' => $b->stock_status?->label(),
                'jumlah' => round((float) $b->qty_base, 4),
                'satuan' => $b->item?->baseUom?->code,
                'potong' => $b->piece_id !== null ? (int) $b->piece_count : null,
            ]);
    }
}

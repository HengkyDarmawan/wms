<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\ItemCategory;
use App\Domain\Shared\Reports\Concerns\PeriodFilter;
use App\Domain\Shared\Reports\Report;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Warehouse\Enums\BinType;
use Illuminate\Support\Collection;

/** Laporan 13-stock §9 *Stok di bawah titik pesan ulang* (BR-REQ-11): tersedia di bin penyimpanan vs `items.reorder_point`. */
class ReorderPointReport extends Report
{
    use PeriodFilter;

    public function key(): string
    {
        return 'titik-pesan-ulang';
    }

    public function title(): string
    {
        return 'Stok di bawah titik pesan ulang';
    }

    public function permission(): string
    {
        return 'stock.view';
    }

    public function description(): string
    {
        return 'Item ber-titik pesan ulang yang stok tersedianya (bin penyimpanan, gudang dalam cakupan/terpilih) sudah di bawah titik itu.';
    }

    public function columns(): array
    {
        return ['item' => 'Item', 'nama' => 'Nama', 'kategori' => 'Kategori', 'tersedia' => 'Tersedia', 'titik' => 'Titik pesan ulang', 'selisih' => 'Selisih', 'satuan' => 'Satuan'];
    }

    public function filters(): array
    {
        return [
            'warehouse_id' => $this->penyaringGudang(),
            'item_category_id' => ['label' => 'Kategori', 'options' => ItemCategory::query()->orderBy('name')->pluck('name', 'id')->all()],
        ];
    }

    public function rows(array $filters): Collection
    {
        $gudang = $this->gudangDipilih($filters);

        $tersedia = StockBalance::query()->withoutGlobalScopes()
            ->join('bins as b', 'b.id', '=', 'stock_balances.bin_id')
            ->where('b.bin_type', BinType::Storage->value)
            ->where('stock_balances.stock_status', StockStatus::Available->value)
            ->when($gudang !== null, fn ($q) => $q->whereIn('b.warehouse_id', $gudang))
            ->selectRaw('stock_balances.item_id, SUM(stock_balances.qty_base) as jumlah')
            ->groupBy('stock_balances.item_id')
            ->pluck('jumlah', 'item_id');

        return Item::query()
            ->with('category:id,name', 'baseUom:id,code')
            ->whereNotNull('reorder_point')
            ->when((int) ($filters['item_category_id'] ?? 0) > 0, fn ($q) => $q->where('item_category_id', (int) $filters['item_category_id']))
            ->orderBy('code')
            ->get()
            ->map(fn (Item $i) => [
                'item' => $i->code,
                'nama' => $i->name,
                'kategori' => $i->category?->name ?? '—',
                'tersedia' => round((float) ($tersedia[$i->id] ?? 0), 4),
                'titik' => round((float) $i->reorder_point, 4),
                'selisih' => round((float) $i->reorder_point - (float) ($tersedia[$i->id] ?? 0), 4),
                'satuan' => $i->baseUom?->code,
            ])
            ->filter(fn ($r) => $r['selisih'] > 0)
            ->sortByDesc('selisih')
            ->values();
    }
}

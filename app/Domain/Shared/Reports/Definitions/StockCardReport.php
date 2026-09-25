<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Shared\Reports\Concerns\PeriodFilter;
use App\Domain\Shared\Reports\Report;
use App\Domain\Stock\Models\StockMovement;
use Illuminate\Support\Collection;

/** Laporan 13-stock §9 *Kartu stok*: mutasi per item dari kartu stok permanen (P-01, BR-STK-01). */
class StockCardReport extends Report
{
    use PeriodFilter;

    public function key(): string
    {
        return 'kartu-stok';
    }

    public function title(): string
    {
        return 'Kartu stok';
    }

    public function permission(): string
    {
        return 'stock.view';
    }

    public function description(): string
    {
        return 'Riwayat mutasi item: waktu, dokumen, bin asal & tujuan, jumlah, kondisi, dan pelaku; rentang bawaan bulan berjalan.';
    }

    public function columns(): array
    {
        return [
            'waktu' => 'Waktu', 'item' => 'Item', 'dokumen' => 'Dokumen', 'asal' => 'Asal', 'tujuan' => 'Tujuan',
            'jumlah' => 'Jumlah', 'satuan' => 'Satuan', 'kondisi' => 'Kondisi', 'pelaku' => 'Pelaku',
        ];
    }

    public function filters(): array
    {
        return ['item' => ['label' => 'Kode item mengandung'], 'warehouse_id' => $this->penyaringGudang()] + $this->penyaringPeriode();
    }

    public function rows(array $filters): Collection
    {
        [$dari, $sampai] = $this->periode($filters);
        $gudang = $this->gudangDipilih($filters);
        $item = trim((string) ($filters['item'] ?? ''));

        return StockMovement::query()
            ->with('item:id,code,name,base_uom_id', 'item.baseUom:id,code', 'fromBin:id,code,warehouse_id', 'toBin:id,code,warehouse_id', 'performer:id,name')
            ->whereBetween('occurred_at', [$dari, $sampai])
            ->when($item !== '', fn ($q) => $q->whereHas('item', fn ($i) => $i->where('code', 'like', '%'.$item.'%')))
            ->when($gudang !== null, fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('fromBin', fn ($b) => $b->withoutGlobalScopes()->whereIn('warehouse_id', $gudang))
                ->orWhereHas('toBin', fn ($b) => $b->withoutGlobalScopes()->whereIn('warehouse_id', $gudang))))
            ->orderBy('occurred_at')->orderBy('id')
            ->limit(2000)
            ->get()
            ->map(fn (StockMovement $m) => [
                'waktu' => $m->occurred_at?->lokal()->format('d/m/Y H:i'),
                'item' => $m->item?->code,
                'dokumen' => $m->document_number ?? ($m->document_type ? $m->document_type.'#'.$m->document_id : '—'),
                'asal' => $m->fromBin?->code ?? '—',
                'tujuan' => $m->toBin?->code ?? '—',
                'jumlah' => round((float) $m->qty_base, 4),
                'satuan' => $m->item?->baseUom?->code,
                'kondisi' => $m->stock_status?->label(),
                'pelaku' => $m->performer?->name ?? 'Sistem',
            ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Shared\Reports\Concerns\LatestInbound;
use App\Domain\Shared\Reports\Concerns\PeriodFilter;
use App\Domain\Shared\Reports\Report;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Warehouse\Enums\BinType;
use Illuminate\Support\Collection;

/**
 * Laporan 22-retur-transfer §9 *Posisi barang rusak di bin Retur*: saldo
 * berkondisi Rusak di bin Retur (hasil pilah rusak yang belum diputuskan —
 * RTV, waste, atau perbaikan). Umur dan dokumen = baris kartu stok terakhir
 * yang memasukkan barang rusak ke saldo itu (`LatestInbound`), biasanya
 * pemilahan RET.
 */
class DamagedReturnBinReport extends Report
{
    use LatestInbound;
    use PeriodFilter;

    public function key(): string
    {
        return 'rusak-bin-retur';
    }

    public function title(): string
    {
        return 'Barang rusak di bin Retur';
    }

    public function permission(): string
    {
        return 'return.view';
    }

    public function description(): string
    {
        return 'Saldo berkondisi Rusak di bin Retur per gudang: item, jumlah, dokumen masuk terakhir, dan umurnya dalam hari.';
    }

    public function columns(): array
    {
        return ['gudang' => 'Gudang', 'bin' => 'Bin', 'item' => 'Item', 'pelacakan' => 'Lot / serial / potongan', 'jumlah' => 'Jumlah', 'satuan' => 'Satuan', 'dokumen' => 'Dokumen masuk', 'masuk' => 'Masuk', 'umur' => 'Umur (hari)'];
    }

    public function filters(): array
    {
        return ['warehouse_id' => $this->penyaringGudang()];
    }

    public function rows(array $filters): Collection
    {
        $gudang = $this->gudangDipilih($filters);

        $saldo = StockBalance::query()
            ->with('item:id,code,base_uom_id', 'item.baseUom:id,code', 'bin:id,code,warehouse_id', 'bin.warehouse:id,code', 'lot:id,lot_no', 'serial:id,serial_no', 'piece:id,piece_no')
            ->where('stock_status', StockStatus::Damaged->value)
            ->where('qty_base', '>', 0)
            ->whereHas('bin', fn ($q) => $q->where('bin_type', BinType::Return->value)
                ->when($gudang !== null, fn ($w) => $w->whereIn('warehouse_id', $gudang)))
            ->get();

        $masuk = $this->masukTerakhir($saldo);

        return $saldo->map(function (StockBalance $b) use ($masuk) {
            $m = $masuk[$this->kunciUntuk($b)] ?? null;

            return [
                'gudang' => $b->bin?->warehouse?->code,
                'bin' => $b->bin?->code,
                'item' => $b->item?->code,
                'pelacakan' => $b->lot?->lot_no ?? $b->serial?->serial_no ?? $b->piece?->piece_no ?? '—',
                'jumlah' => round((float) $b->qty_base, 4),
                'satuan' => $b->item?->baseUom?->code,
                'dokumen' => $m?->document_number ?? '—',
                'masuk' => $m?->occurred_at?->lokal()->format('d/m/Y') ?? '—',
                'umur' => $this->umurHari($m) ?? '—',
            ];
        })
            ->sortBy([['gudang', 'asc'], ['bin', 'asc'], ['item', 'asc']])
            ->values();
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Shared\Reports\Concerns\LatestInbound;
use App\Domain\Shared\Reports\Concerns\PeriodFilter;
use App\Domain\Shared\Reports\Report;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Transfer\Models\Transfer;
use App\Domain\Warehouse\Enums\BinType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Laporan 22-retur-transfer §9 *Barang dalam perjalanan antar gudang*: saldo
 * di bin Dalam Perjalanan. Bin itu milik gudang **asal** (BR-STK-13), jadi
 * kolom gudang = gudang asal dan penyaring gudang bekerja pada gudang asal.
 *
 * SJ = dokumen baris kartu stok terakhir yang memasukkan barang ke saldo itu
 * (`LatestInbound`); umur dihitung dari baris itu. Tujuan dibaca dari SJ, dan
 * TRF diturunkan dari SJ → baris PCK → PCK bersumber `transfer`. SJ ke proyek
 * (bukan transfer) tetap tampil dengan TRF "—".
 */
class InTransitStockReport extends Report
{
    use LatestInbound;
    use PeriodFilter;

    public function key(): string
    {
        return 'barang-dalam-perjalanan';
    }

    public function title(): string
    {
        return 'Barang dalam perjalanan';
    }

    public function permission(): string
    {
        return 'transfer.view';
    }

    public function description(): string
    {
        return 'Saldo di bin Dalam Perjalanan per gudang asal: item, jumlah, SJ yang membawanya, tujuan, TRF terkait, dan umurnya dalam hari.';
    }

    public function columns(): array
    {
        return ['gudang' => 'Gudang asal', 'item' => 'Item', 'pelacakan' => 'Lot / serial / potongan', 'kondisi' => 'Kondisi', 'jumlah' => 'Jumlah', 'satuan' => 'Satuan', 'sj' => 'SJ', 'tujuan' => 'Tujuan', 'trf' => 'TRF', 'umur' => 'Umur (hari)'];
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
            ->where('qty_base', '>', 0)
            ->whereHas('bin', fn ($q) => $q->where('bin_type', BinType::InTransit->value)
                ->when($gudang !== null, fn ($w) => $w->whereIn('warehouse_id', $gudang)))
            ->get();

        $masuk = $this->masukTerakhir($saldo);
        $sjIds = collect($masuk)->filter(fn ($m) => $m->document_type === 'shipment')->pluck('document_id')->unique()->values();
        [$sj, $trf] = $this->asalSj($sjIds);

        return $saldo->map(function (StockBalance $b) use ($masuk, $sj, $trf) {
            $m = $masuk[$this->kunciUntuk($b)] ?? null;
            $kiriman = $m?->document_type === 'shipment' ? $sj->get($m->document_id) : null;

            return [
                'gudang' => $b->bin?->warehouse?->code,
                'item' => $b->item?->code,
                'pelacakan' => $b->lot?->lot_no ?? $b->serial?->serial_no ?? $b->piece?->piece_no ?? '—',
                'kondisi' => $b->stock_status->label(),
                'jumlah' => round((float) $b->qty_base, 4),
                'satuan' => $b->item?->baseUom?->code,
                'sj' => $m?->document_number ?? '—',
                'tujuan' => $kiriman?->destinationWarehouse?->code ?? $kiriman?->destinationProject?->code ?? '—',
                'trf' => $kiriman !== null ? ($trf[$kiriman->id] ?? '—') : '—',
                'umur' => $this->umurHari($m) ?? '—',
            ];
        })
            ->sortBy([['gudang', 'asc'], ['item', 'asc']])
            ->values();
    }

    /**
     * SJ dan nomor TRF-nya dicari sekali untuk semua baris, bukan per baris.
     * Tanpa global scope hanya untuk pencarian label: saldonya sudah dibatasi
     * cakupan lewat bin (BR-ACC-05).
     *
     * @param  Collection<int, int>  $ids
     * @return array{0: Collection<int, Shipment>, 1: array<int, string>}
     */
    private function asalSj(Collection $ids): array
    {
        if ($ids->isEmpty()) {
            return [collect(), []];
        }

        $sj = Shipment::query()->withoutGlobalScopes()
            ->with(['destinationWarehouse' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'code'), 'destinationProject:id,code'])
            ->whereIn('id', $ids)
            ->get(['id', 'number', 'destination_warehouse_id', 'destination_project_id'])
            ->keyBy('id');

        $sumber = DB::table('shipment_lines')
            ->join('pick_task_lines', 'pick_task_lines.id', '=', 'shipment_lines.pick_task_line_id')
            ->join('pick_tasks', 'pick_tasks.id', '=', 'pick_task_lines.pick_task_id')
            ->whereIn('shipment_lines.shipment_id', $ids)
            ->where('pick_tasks.source_type', 'transfer')
            ->distinct()
            ->get(['shipment_lines.shipment_id', 'pick_tasks.source_id']);

        $nomor = Transfer::query()->withoutGlobalScopes()->whereIn('id', $sumber->pluck('source_id')->unique())->pluck('number', 'id');
        $trf = [];

        foreach ($sumber->groupBy('shipment_id') as $sjId => $grup) {
            $trf[(int) $sjId] = $grup->map(fn ($s) => $nomor[$s->source_id] ?? null)->filter()->unique()->implode(', ');
        }

        return [$sj, $trf];
    }
}

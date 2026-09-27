<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Definitions;

use App\Domain\Label\Models\PackageLabel;
use App\Domain\Label\Models\PackageLabelMove;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Vendor;
use App\Domain\Receipt\Enums\GoodsReceiptStatus;
use App\Domain\Receipt\Enums\QcResult;
use App\Domain\Receipt\Enums\ReceiptType;
use App\Domain\Receipt\Models\GoodsReceiptLine;
use App\Domain\Return\Enums\ReturnSorting;
use App\Domain\Return\Models\GoodsReturnLine;
use App\Domain\Shared\Reports\Concerns\PeriodFilter;
use App\Domain\Shared\Reports\Report;
use App\Domain\Shipment\Models\PickTaskLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Laporan 19-receipt-putaway §9 *Barang bermasalah per vendor* (A-303): barang
 * yang diterima dari vendor dalam periode (tanggal terima GRN asal) beserta
 * masalahnya — rusak & kurang saat terima, ditolak QC, rusak saat dikirim
 * (bukti terima SJ) dan retur yang dipilah rusak/waste. Dua yang terakhir
 * ditelusuri ke GRN asal lewat label kemasan (A-296), cadangan lewat lot.
 * Jumlah satuan dasar per item, tanpa nilai uang (D-07).
 */
class VendorProblemReport extends Report
{
    use PeriodFilter;

    private const EPS = 0.00005;

    public function key(): string
    {
        return 'barang-bermasalah-vendor';
    }

    public function title(): string
    {
        return 'Barang bermasalah per vendor';
    }

    public function permission(): string
    {
        return 'receipt.view';
    }

    public function description(): string
    {
        return 'Per vendor & item yang diterima dalam periode: rusak dan kurang saat terima, ditolak QC, rusak saat dikirim, dan retur rusak/waste yang ditelusuri lewat label kemasan atau lot — plus yang sudah diretur ke vendor.';
    }

    public function columns(): array
    {
        return [
            'vendor' => 'Vendor', 'item' => 'Item', 'satuan' => 'Satuan', 'diterima' => 'Diterima', 'rusak' => 'Rusak saat terima',
            'kurang' => 'Kurang', 'ditolak_qc' => 'Ditolak QC', 'rusak_kirim' => 'Rusak saat dikirim', 'retur_rusak' => 'Retur rusak/waste',
            'persen' => '% bermasalah', 'diretur' => 'Diretur ke vendor', 'dilacak' => 'Dilacak lewat',
        ];
    }

    public function filters(): array
    {
        return [
            'warehouse_id' => $this->penyaringGudang(),
            'vendor_id' => ['label' => 'Vendor', 'options' => Vendor::query()->orderBy('code')->get(['id', 'code', 'name'])
                ->mapWithKeys(fn (Vendor $v) => [$v->id => $v->code.' — '.$v->name])->all()],
        ] + $this->penyaringPeriode();
    }

    public function rows(array $filters): Collection
    {
        [$dari, $sampai] = $this->periode($filters);
        $gudang = $this->gudangDipilih($filters);
        $vendor = (int) ($filters['vendor_id'] ?? 0);

        $baris = GoodsReceiptLine::query()
            ->join('goods_receipts as g', 'g.id', '=', 'goods_receipt_lines.goods_receipt_id')
            ->where('g.receipt_type', ReceiptType::Vendor->value)
            ->whereIn('g.status', [GoodsReceiptStatus::Received->value, GoodsReceiptStatus::Completed->value])
            ->whereBetween('g.received_at', [$dari, $sampai])
            ->when($gudang !== null, fn ($q) => $q->whereIn('g.warehouse_id', $gudang))
            ->when($vendor > 0, fn ($q) => $q->where('g.vendor_id', $vendor))
            ->whereNotNull('g.vendor_id')
            ->get(['goods_receipt_lines.id', 'goods_receipt_lines.item_id', 'goods_receipt_lines.lot_id', 'goods_receipt_lines.qty_received',
                'goods_receipt_lines.qty_damaged', 'goods_receipt_lines.qty_short', 'goods_receipt_lines.qc_result', 'g.vendor_id'])
            ->keyBy('id');

        if ($baris->isEmpty()) {
            return collect();
        }

        $hasil = [];
        $tambah = function (int $lineId, string $kolom, float $qty, ?string $lewat = null) use (&$hasil, $baris): void {
            $l = $baris->get($lineId);

            if ($l === null || abs($qty) <= self::EPS) {
                return;
            }

            $k = $l->vendor_id.':'.$l->item_id;
            $hasil[$k] ??= ['vendor_id' => (int) $l->vendor_id, 'item_id' => (int) $l->item_id, 'diterima' => 0.0, 'rusak' => 0.0, 'kurang' => 0.0,
                'ditolak_qc' => 0.0, 'rusak_kirim' => 0.0, 'retur_rusak' => 0.0, 'diretur' => 0.0, 'dilacak' => ['GRN' => true]];
            $hasil[$k][$kolom] += $qty;

            if ($lewat !== null) {
                $hasil[$k]['dilacak'][$lewat] = true;
            }
        };

        foreach ($baris as $l) {
            $baik = (float) $l->qty_received;
            $tambah((int) $l->id, 'diterima', $baik + (float) $l->qty_damaged);
            $tambah((int) $l->id, 'rusak', (float) $l->qty_damaged);
            $tambah((int) $l->id, 'kurang', (float) $l->qty_short);

            if ($l->qc_result === QcResult::Rejected) {
                $tambah((int) $l->id, 'ditolak_qc', $baik);
            }
        }

        // Diretur ke vendor (RTV yang tidak batal).
        DB::table('vendor_return_lines as v')->join('vendor_returns as r', 'r.id', '=', 'v.vendor_return_id')
            ->where('r.status', '!=', 'cancelled')->whereIn('v.goods_receipt_line_id', $baris->keys())
            ->groupBy('v.goods_receipt_line_id')->selectRaw('v.goods_receipt_line_id as line_id, SUM(v.qty_base) as qty')->get()
            ->each(fn ($r) => $tambah((int) $r->line_id, 'diretur', (float) $r->qty));

        $labelKeGrn = PackageLabel::query()->whereIn('goods_receipt_line_id', $baris->keys())->pluck('goods_receipt_line_id', 'id')->map(fn ($v) => (int) $v);
        $lotKeGrn = $baris->filter(fn ($l) => $l->lot_id !== null)->sortKeysDesc()->mapWithKeys(fn ($l) => [(int) $l->lot_id => (int) $l->id])->all();

        // Retur dipilah rusak/waste: lewat label, cadangan lewat lot.
        $returLabel = PackageLabelMove::query()->where('document_type', 'goods_return')->where('qty_change', '<', 0)
            ->whereIn('package_label_id', $labelKeGrn->keys())->get(['package_label_id', 'qty_change', 'document_line_id']);
        $returLabel->each(fn ($m) => $tambah($labelKeGrn[(int) $m->package_label_id], 'retur_rusak', -(float) $m->qty_change, 'label'));
        $sudah = $returLabel->pluck('document_line_id')->map(fn ($v) => (int) $v)->all();

        if ($lotKeGrn !== []) {
            GoodsReturnLine::query()->whereIn('sorting', [ReturnSorting::Damaged->value, ReturnSorting::Waste->value])
                ->whereIn('lot_id', array_keys($lotKeGrn))->whereNotIn('id', $sudah ?: [0])->get(['id', 'lot_id', 'sorted_qty'])
                ->each(fn ($r) => $tambah($lotKeGrn[(int) $r->lot_id], 'retur_rusak', (float) $r->sorted_qty, 'lot'));
        }

        // Rusak saat dikirim (bukti terima SJ): label yang keluar lewat PCK baris itu, cadangan lot.
        $pod = DB::table('proof_of_delivery_lines as p')->join('shipment_lines as s', 's.id', '=', 'p.shipment_line_id')
            ->where('p.qty_damaged', '>', 0)->get(['s.pick_task_line_id', 'p.qty_damaged']);

        foreach ($pod as $p) {
            $sisa = (float) $p->qty_damaged;
            $keluar = PackageLabelMove::query()->where('document_type', 'pick_task')->where('document_line_id', $p->pick_task_line_id)
                ->where('qty_change', '<', 0)->whereIn('package_label_id', $labelKeGrn->keys())->orderBy('id')->get(['package_label_id', 'qty_change']);

            foreach ($keluar as $m) {
                $ambil = min($sisa, -(float) $m->qty_change);
                $tambah($labelKeGrn[(int) $m->package_label_id], 'rusak_kirim', $ambil, 'label');
                $sisa -= $ambil;

                if ($sisa <= self::EPS) {
                    break;
                }
            }

            $lot = $keluar->isEmpty() ? PickTaskLine::query()->whereKey($p->pick_task_line_id)->value('lot_id') : null;

            if ($lot !== null && isset($lotKeGrn[(int) $lot])) {
                $tambah($lotKeGrn[(int) $lot], 'rusak_kirim', $sisa, 'lot');
            }
        }

        $vendors = Vendor::query()->whereIn('id', array_column($hasil, 'vendor_id'))->get(['id', 'code', 'name'])->keyBy('id');
        $items = Item::query()->with('baseUom:id,code')->whereIn('id', array_column($hasil, 'item_id'))->get(['id', 'code', 'name', 'base_uom_id'])->keyBy('id');

        return collect($hasil)
            ->map(function (array $r) use ($vendors, $items) {
                $masalah = $r['rusak'] + $r['kurang'] + $r['ditolak_qc'] + $r['rusak_kirim'] + $r['retur_rusak'];
                $dasar = $r['diterima'] + $r['kurang'];
                $v = $vendors->get($r['vendor_id']);
                $i = $items->get($r['item_id']);

                return [
                    'vendor' => $v !== null ? $v->code.' — '.$v->name : '—',
                    'item' => $i !== null ? $i->code.' — '.$i->name : '—',
                    'satuan' => $i?->baseUom?->code,
                    'diterima' => round($r['diterima'], 4),
                    'rusak' => round($r['rusak'], 4),
                    'kurang' => round($r['kurang'], 4),
                    'ditolak_qc' => round($r['ditolak_qc'], 4),
                    'rusak_kirim' => round($r['rusak_kirim'], 4),
                    'retur_rusak' => round($r['retur_rusak'], 4),
                    'persen' => $dasar > self::EPS ? round($masalah / $dasar * 100, 1) : 0.0,
                    'diretur' => round($r['diretur'], 4),
                    'dilacak' => implode(', ', array_keys($r['dilacak'])),
                    '_masalah' => $masalah,
                ];
            })
            ->filter(fn (array $r) => $r['_masalah'] > self::EPS)
            ->sortBy([['vendor', 'asc'], ['persen', 'desc']])
            ->map(fn (array $r) => array_diff_key($r, ['_masalah' => true]))
            ->values();
    }
}

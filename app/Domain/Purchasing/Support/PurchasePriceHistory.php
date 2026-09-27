<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Support;

use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Models\PurchaseOrderLine;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Riwayat harga beli (A-309): semua baris PO item — tanggal PO, nomor, vendor,
 * jumlah, harga satuan, tanda PPN, status — dan ringkasan per vendor
 * (terakhir / termurah / rata-rata 3, 6, 12 bulan). Ringkasan hanya menghitung
 * PO yang sudah disetujui (`VendorSuggestions::STATUS_HARGA`).
 */
class PurchasePriceHistory
{
    public const JENDELA = [3, 6, 12];

    /**
     * @return Collection<int, array{id: int, po_id: int, tanggal: Carbon, po: string, vendor_id: int, vendor: string, item_id: int, item: string, satuan: ?string, jumlah: float, harga: float, ppn: bool, status: PurchaseOrderStatus, dihitung: bool}>
     */
    public function lines(?int $itemId, ?int $vendorId = null, ?CarbonInterface $dari = null, ?CarbonInterface $sampai = null, ?string $itemCode = null): Collection
    {
        $dihitung = array_map(fn (PurchaseOrderStatus $s) => $s->value, VendorSuggestions::STATUS_HARGA);

        return PurchaseOrderLine::query()
            ->join('purchase_orders as o', 'o.id', '=', 'purchase_order_lines.purchase_order_id')
            ->join('vendors as v', 'v.id', '=', 'o.vendor_id')
            ->join('items as i', 'i.id', '=', 'purchase_order_lines.item_id')
            ->leftJoin('uoms as u', 'u.id', '=', 'i.base_uom_id')
            ->when($itemId !== null, fn ($q) => $q->where('purchase_order_lines.item_id', $itemId))
            ->when($itemCode !== null && $itemCode !== '', fn ($q) => $q->where('i.code', 'like', '%'.$itemCode.'%'))
            ->when($vendorId !== null, fn ($q) => $q->where('o.vendor_id', $vendorId))
            ->when($dari !== null, fn ($q) => $q->whereDate('o.order_date', '>=', $dari->toDateString()))
            ->when($sampai !== null, fn ($q) => $q->whereDate('o.order_date', '<=', $sampai->toDateString()))
            ->orderByDesc('o.order_date')->orderByDesc('purchase_order_lines.id')
            ->get(['purchase_order_lines.id', 'purchase_order_lines.qty_base', 'purchase_order_lines.unit_price', 'purchase_order_lines.item_id',
                'o.id as po_id', 'o.number', 'o.order_date', 'o.status', 'o.price_includes_tax', 'o.vendor_id', 'v.name as vendor_name',
                'i.code as item_code', 'u.code as uom_code'])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'po_id' => (int) $r->po_id,
                'tanggal' => Carbon::parse($r->order_date),
                'po' => (string) $r->number,
                'vendor_id' => (int) $r->vendor_id,
                'vendor' => (string) $r->vendor_name,
                'item_id' => (int) $r->item_id,
                'item' => (string) $r->item_code,
                'satuan' => $r->uom_code,
                'jumlah' => round((float) $r->qty_base, 4),
                'harga' => (float) $r->unit_price,
                'ppn' => (bool) $r->price_includes_tax,
                'status' => PurchaseOrderStatus::from((string) $r->status),
                'dihitung' => in_array((string) $r->status, $dihitung, true),
            ]);
    }

    /**
     * Ringkasan per vendor dari baris yang dihitung.
     *
     * @param  Collection<int, array<string, mixed>>  $lines
     * @return Collection<int, array{vendor_id: int, vendor: string, terakhir: array{harga: float, tanggal: Carbon, ppn: bool}, jendela: array<int, array{termurah: ?float, rata: ?float, jumlah_po: int}>}>
     */
    public function summary(Collection $lines): Collection
    {
        return $lines->where('dihitung', true)->groupBy('vendor_id')->map(function (Collection $g) {
            $akhir = $g->sortByDesc(fn ($l) => $l['tanggal']->timestamp * 1_000_000 + $l['id'])->first();
            $jendela = [];

            foreach (self::JENDELA as $bulan) {
                $batas = now()->subMonths($bulan)->startOfDay();
                $isi = $g->filter(fn ($l) => $l['tanggal']->greaterThanOrEqualTo($batas));
                $jumlah = (float) $isi->sum('jumlah');

                $jendela[$bulan] = [
                    'termurah' => $isi->isEmpty() ? null : (float) $isi->min('harga'),
                    // Rata-rata tertimbang jumlah dipesan.
                    'rata' => $jumlah > 0 ? Money::round($isi->sum(fn ($l) => $l['harga'] * $l['jumlah']) / $jumlah) : null,
                    'jumlah_po' => $isi->pluck('po_id')->unique()->count(),
                ];
            }

            return [
                'vendor_id' => (int) $akhir['vendor_id'],
                'vendor' => $akhir['vendor'],
                'terakhir' => ['harga' => $akhir['harga'], 'tanggal' => $akhir['tanggal'], 'ppn' => $akhir['ppn']],
                'jendela' => $jendela,
            ];
        })->sortBy('vendor')->values();
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Support;

use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Models\Vendor;
use App\Domain\PurchaseRequest\Models\PurchaseRequestOrder;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Models\PurchaseOrderLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Saran vendor dari riwayat (A-304): pemilihan vendor tugas Purchasing, sistem
 * hanya menyarankan **vendor terakhir** (catatan pemesanan PRQ atau PO terbaru
 * item itu) dan **vendor termurah** (harga satuan terendah baris PO 6 bulan
 * terakhir). Hanya vendor aktif. Satu-satunya pembaca harga untuk saran (D-07):
 * layar WMS hanya menerima nama vendor, tanpa angka.
 */
class VendorSuggestions
{
    public const BULAN_TERMURAH = 6;

    /** Status PO yang harganya dianggap sungguhan (sudah disetujui). */
    public const STATUS_HARGA = [
        PurchaseOrderStatus::Approved,
        PurchaseOrderStatus::PartiallyFulfilled,
        PurchaseOrderStatus::Completed,
        PurchaseOrderStatus::ClosedShort,
    ];

    /**
     * @param  array<int, int>  $itemIds
     * @return array<int, array{terakhir: ?Vendor, termurah: ?Vendor}> item_id => saran
     */
    public function forItems(array $itemIds): array
    {
        $hasil = [];

        foreach (array_unique(array_map('intval', $itemIds)) as $id) {
            $hasil[$id] = ['terakhir' => $this->lastVendor($id), 'termurah' => $this->cheapest($id)['vendor'] ?? null];
        }

        return $hasil;
    }

    /**
     * Satu vendor untuk isian bawaan dokumen berbaris banyak: vendor terakhir
     * yang paling sering muncul di antara item-itemnya (seri → yang terbaru).
     *
     * @param  array<int, int>  $itemIds
     */
    public function defaultVendor(array $itemIds): ?Vendor
    {
        return collect($this->forItems($itemIds))->pluck('terakhir')->filter()
            ->groupBy('id')->sortByDesc(fn (Collection $g) => $g->count())->first()?->first();
    }

    /** Vendor aktif dari catatan pemesanan PRQ / PO terbaru untuk item ini. */
    public function lastVendor(int $itemId): ?Vendor
    {
        // Catatan pemesanan yang lahir dari PO dibaca lewat baris PO (tanggal PO), bukan tanggal catatannya.
        $pesanan = PurchaseRequestOrder::query()->whereNull('purchase_order_id')
            ->whereHas('lines.line', fn ($q) => $q->where('item_id', $itemId))
            ->whereHas('vendor', fn ($q) => $this->aktif($q))
            ->latest('ordered_at')->latest('id')->first(['id', 'vendor_id', 'ordered_at']);

        $po = $this->baris($itemId)->latest('o.order_date')->latest('purchase_order_lines.id')
            ->first(['o.vendor_id', 'o.order_date']);

        $dariPesanan = $pesanan?->ordered_at;
        $dariPo = $po?->order_date === null ? null : Carbon::parse($po->order_date)->endOfDay();

        $vendorId = match (true) {
            $pesanan === null && $po === null => null,
            $po === null => $pesanan->vendor_id,
            $pesanan === null => $po->vendor_id,
            default => $dariPo->greaterThan($dariPesanan) ? $po->vendor_id : $pesanan->vendor_id,
        };

        return $vendorId === null ? null : Vendor::query()->find($vendorId);
    }

    /**
     * Harga satuan terendah baris PO item ini dalam 6 bulan (vendor aktif),
     * dibandingkan apa adanya — tanda PPN ikut dikembalikan (A-307).
     *
     * @return array{vendor: Vendor, price: float, includes_tax: bool, date: string}|null
     */
    public function cheapest(int $itemId, int $bulan = self::BULAN_TERMURAH): ?array
    {
        $b = $this->baris($itemId)->whereDate('o.order_date', '>=', now()->subMonths($bulan)->toDateString())
            ->orderBy('purchase_order_lines.unit_price')->latest('o.order_date')
            ->first(['o.vendor_id', 'o.order_date', 'o.price_includes_tax', 'purchase_order_lines.unit_price']);

        return $b === null ? null : [
            'vendor' => Vendor::query()->find($b->vendor_id),
            'price' => (float) $b->unit_price,
            'includes_tax' => (bool) $b->price_includes_tax,
            'date' => (string) $b->order_date,
        ];
    }

    /**
     * Harga PO terakhir item ini (vendor mana pun) — pembanding kenaikan harga (A-306).
     *
     * @return array{vendor: ?Vendor, price: float, includes_tax: bool, date: string, number: string}|null
     */
    public function lastPrice(int $itemId, ?int $exceptPurchaseOrderId = null): ?array
    {
        $b = $this->baris($itemId, false)
            ->when($exceptPurchaseOrderId !== null, fn ($q) => $q->where('o.id', '!=', $exceptPurchaseOrderId))
            ->latest('o.order_date')->latest('purchase_order_lines.id')
            ->first(['o.vendor_id', 'o.order_date', 'o.number', 'o.price_includes_tax', 'purchase_order_lines.unit_price']);

        return $b === null ? null : [
            'vendor' => Vendor::query()->find($b->vendor_id),
            'price' => (float) $b->unit_price,
            'includes_tax' => (bool) $b->price_includes_tax,
            'date' => (string) $b->order_date,
            'number' => (string) $b->number,
        ];
    }

    /** Baris PO disetujui untuk item; `$aktif` = hanya vendor aktif. */
    private function baris(int $itemId, bool $aktif = true)
    {
        return PurchaseOrderLine::query()
            ->join('purchase_orders as o', 'o.id', '=', 'purchase_order_lines.purchase_order_id')
            ->where('purchase_order_lines.item_id', $itemId)
            ->whereIn('o.status', array_map(fn (PurchaseOrderStatus $s) => $s->value, self::STATUS_HARGA))
            ->when($aktif, fn ($q) => $q->whereIn('o.vendor_id', Vendor::query()->where(fn ($v) => $this->aktif($v))->select('id')));
    }

    private function aktif($q)
    {
        return $q->where('is_active', true)->where('status', VendorStatus::Active->value);
    }
}

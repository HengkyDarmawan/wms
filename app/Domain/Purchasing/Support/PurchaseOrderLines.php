<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Support;

use App\Domain\PurchaseRequest\Enums\PurchaseRequestStatus;
use App\Domain\PurchaseRequest\Models\PurchaseRequestLine;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Exceptions\PurchasingRuleException;
use App\Domain\Purchasing\Models\PurchaseOrderLine;
use Illuminate\Support\Collection;

/**
 * Baris PO dari baris PRQ (A-210, A-211): PRQ gudang yang sama dan menerima
 * pesanan; jumlah di atas sisa belum dipesan (dikurangi yang dipegang PO
 * draf/menunggu approval lain) wajib beralasan (A-246); harga satuan > 0.
 */
class PurchaseOrderLines
{
    /**
     * Baris PRQ gudang ini yang masih bisa dipesan, dengan sisa yang tersedia untuk PO.
     *
     * @return Collection<int, array{line: PurchaseRequestLine, available: float}>
     */
    public function openRequestLines(int $warehouseId, ?int $exceptPoId = null): Collection
    {
        $baris = PurchaseRequestLine::query()
            ->with('item:id,code,name,base_uom_id,item_category_id', 'item.baseUom:id,code', 'purchaseRequest:id,number,status,warehouse_id,project_id')
            ->whereColumn('qty_ordered', '<', 'qty_base')
            ->whereHas('purchaseRequest', fn ($q) => $q->withoutGlobalScopes()
                ->where('warehouse_id', $warehouseId)
                ->whereIn('status', [PurchaseRequestStatus::Approved->value, PurchaseRequestStatus::Forwarded->value, PurchaseRequestStatus::PartiallyFulfilled->value]))
            ->orderBy('purchase_request_id')->orderBy('id')
            ->get();

        $dipegang = $this->heldByOtherOrders($baris->pluck('id')->all(), $exceptPoId);

        return $baris->map(fn (PurchaseRequestLine $l) => [
            'line' => $l,
            'available' => round(max(0, $l->unorderedQty() - ($dipegang[(int) $l->id] ?? 0)), 4),
        ])->filter(fn (array $r) => $r['available'] > 0)->values();
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines  purchase_request_line_id, qty_base, unit_price, notes
     * @return array{lines: array<int, array<string, mixed>>, total: float}
     */
    public function normalize(int $warehouseId, array $lines, ?int $exceptPoId = null): array
    {
        $hasil = [];

        foreach ($lines as $isi) {
            $id = (int) ($isi['purchase_request_line_id'] ?? 0);
            $qty = is_numeric($isi['qty_base'] ?? null) ? round((float) $isi['qty_base'], 4) : 0.0;

            if ($id === 0 || $qty == 0.0) {
                continue;
            }

            $l = PurchaseRequestLine::query()->with('item:id,code', 'purchaseRequest')->find($id)
                ?? throw PurchasingRuleException::field('BR-GEN-11', 'lines', 'Baris PRQ tidak ditemukan.');
            $prq = $l->purchaseRequest;
            $label = $l->item?->code.' ('.$prq?->number.')';

            if ($prq === null || (int) $prq->warehouse_id !== $warehouseId || ! $prq->status->acceptsOrders()) {
                throw PurchasingRuleException::field('A-210', 'lines', $label.': PRQ harus disetujui dan bertujuan ke gudang PO ini.');
            }

            if (isset($hasil[$id])) {
                throw PurchasingRuleException::field('BR-GEN-11', 'lines', $label.': baris PRQ yang sama muncul dua kali.');
            }

            if ($qty < 0) {
                throw PurchasingRuleException::field('BR-LED-02', 'lines', $label.': jumlah harus lebih dari nol.');
            }

            $tersedia = round(max(0, $l->unorderedQty() - ($this->heldByOtherOrders([$id], $exceptPoId)[$id] ?? 0)), 4);
            $lebih = $qty - $tersedia > 0.00005 ? round($qty - $tersedia, 4) : 0.0;
            $alasan = trim((string) ($isi['over_order_reason'] ?? ''));

            // A-246: pesan di atas sisa (MOQ vendor, tambah stok) boleh asal beralasan;
            // kelebihannya menjadi stok gudang biasa setelah diterima.
            if ($lebih > 0 && $alasan === '') {
                throw PurchasingRuleException::field('A-246', 'lines', $label.': dipesan '.$qty.' melebihi sisa permintaan ('.$tersedia.'); isi alasan kelebihan (mis. MOQ vendor).');
            }

            $harga = is_numeric($isi['unit_price'] ?? null) ? Money::round((float) $isi['unit_price']) : 0.0;

            if ($harga <= 0) {
                throw PurchasingRuleException::field('A-211', 'lines', $label.': harga satuan wajib lebih dari nol.');
            }

            $catatan = trim((string) ($isi['notes'] ?? ''));

            $hasil[$id] = [
                'purchase_request_line_id' => $id,
                'item_id' => (int) $l->item_id,
                'qty_base' => $qty,
                'qty_over_request' => $lebih,
                'over_order_reason' => $lebih > 0 ? mb_substr($alasan, 0, 255) : null,
                'unit_price' => $harga,
                'line_amount' => Money::round($qty * $harga),
                'notes' => $catatan === '' ? null : mb_substr($catatan, 0, 255),
            ];
        }

        if ($hasil === []) {
            throw PurchasingRuleException::field('BR-GEN-11', 'lines', 'Isi jumlah minimal satu baris PRQ.');
        }

        return ['lines' => array_values($hasil), 'total' => Money::round(array_sum(array_column($hasil, 'line_amount')))];
    }

    /**
     * Jumlah baris PRQ yang sedang dipegang PO draf/menunggu approval (belum
     * menjadi catatan pemesanan).
     *
     * @param  array<int, int>  $prqLineIds
     * @return array<int, float>
     */
    private function heldByOtherOrders(array $prqLineIds, ?int $exceptPoId): array
    {
        if ($prqLineIds === []) {
            return [];
        }

        $status = array_map(fn (PurchaseOrderStatus $s) => $s->value, array_filter(PurchaseOrderStatus::cases(), fn (PurchaseOrderStatus $s) => $s->holdsPrqQty()));

        return PurchaseOrderLine::query()
            ->whereIn('purchase_request_line_id', $prqLineIds)
            ->whereHas('purchaseOrder', fn ($q) => $q->withoutGlobalScopes()->whereIn('status', $status)
                ->when($exceptPoId !== null, fn ($p) => $p->whereKeyNot($exceptPoId)))
            ->selectRaw('purchase_request_line_id, SUM(qty_base) as jumlah')
            ->groupBy('purchase_request_line_id')
            ->pluck('jumlah', 'purchase_request_line_id')
            ->map(fn ($v) => round((float) $v, 4))
            ->all();
    }
}

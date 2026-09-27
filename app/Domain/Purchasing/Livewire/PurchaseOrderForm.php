<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Livewire;

use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Models\CompanySetting;
use App\Domain\Master\Models\Vendor;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\PurchaseRequest\Models\PurchaseRequestLine;
use App\Domain\Purchasing\Actions\CreatePurchaseOrder;
use App\Domain\Purchasing\Actions\SubmitPurchaseOrder;
use App\Domain\Purchasing\Livewire\Concerns\HandlesPurchasingRules;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Support\Money;
use App\Domain\Purchasing\Support\PurchaseOrderLines;
use App\Domain\Purchasing\Support\VendorPrices;
use App\Domain\Purchasing\Support\VendorSuggestions;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Layar purchasing/02 §6 — PO baru / ubah draf: vendor `*`, gudang tujuan `*`,
 * lalu jumlah & harga satuan per baris PRQ terbuka gudang itu (A-210, A-211).
 * Harga bawaan dari daftar harga vendor; vendor tetap item ditandai (A-52).
 * Centang "harga sudah termasuk PPN" bawaan menyala (A-265).
 */
class PurchaseOrderForm extends Component
{
    use HandlesPurchasingRules;

    #[Locked]
    public ?int $poId = null;

    /** @var array<string, string|bool> */
    public array $form = ['vendor_id' => '', 'warehouse_id' => '', 'eta_date' => '', 'notes' => '', 'vendor_choice_note' => '', 'price_includes_tax' => true];

    /** @var array<int|string, string> purchase_request_line_id => jumlah */
    public array $qty = [];

    /** @var array<int|string, string> purchase_request_line_id => harga satuan */
    public array $price = [];

    /** @var array<int|string, string> purchase_request_line_id => alasan pesan lebih (A-246) */
    public array $reason = [];

    public function mount(?PurchaseOrder $purchaseOrder = null): void
    {
        if ($purchaseOrder !== null && $purchaseOrder->exists) {
            $this->authorize('update', $purchaseOrder);

            $this->poId = (int) $purchaseOrder->id;
            $this->form = [
                'vendor_id' => (string) $purchaseOrder->vendor_id,
                'warehouse_id' => (string) $purchaseOrder->warehouse_id,
                'eta_date' => $purchaseOrder->eta_date?->toDateString() ?? '',
                'notes' => (string) $purchaseOrder->notes,
                'vendor_choice_note' => (string) $purchaseOrder->vendor_choice_note,
                'price_includes_tax' => (bool) $purchaseOrder->price_includes_tax,
            ];

            foreach ($purchaseOrder->lines as $l) {
                $this->qty[$l->purchase_request_line_id] = $this->angka((float) $l->qty_base);
                $this->price[$l->purchase_request_line_id] = $this->angka((float) $l->unit_price);
                $this->reason[$l->purchase_request_line_id] = (string) $l->over_order_reason;
            }

            return;
        }

        $this->authorize('create', PurchaseOrder::class);

        $prq = is_numeric(request()->query('prq')) ? PurchaseRequest::query()->find((int) request()->query('prq')) : null;

        if ($prq !== null) {
            $this->form['warehouse_id'] = (string) $prq->warehouse_id;

            foreach ($this->terbuka() as $r) {
                if ((int) $r['line']->purchase_request_id === (int) $prq->id) {
                    $this->qty[$r['line']->id] = $this->angka($r['available']);
                }
            }

            // A-304: bawaan = vendor terakhir item-item PRQ; Purchasing bebas mengganti.
            $saran = app(VendorSuggestions::class)->defaultVendor($prq->lines()->pluck('item_id')->map(fn ($v) => (int) $v)->all());

            if ($saran !== null) {
                $this->form['vendor_id'] = (string) $saran->id;
                $this->isiHarga(true);
            }
        }
    }

    public function updatedFormWarehouseId(): void
    {
        $this->qty = [];
        $this->price = [];
    }

    /** Ganti vendor = harga diisi ulang dari daftar harga vendor itu. */
    public function updatedFormVendorId(): void
    {
        $this->isiHarga(true);
    }

    /** Isi jumlah semua baris dengan sisa yang bisa dipesan. */
    public function pesanSemua(): void
    {
        foreach ($this->terbuka() as $r) {
            $this->qty[$r['line']->id] = $this->angka($r['available']);
        }

        $this->isiHarga(false);
    }

    public function simpan(CreatePurchaseOrder $action): void
    {
        $po = $this->simpanDraf($action);

        if ($po !== null) {
            $this->redirectRoute('purchase-orders.show', $po, navigate: true);
        }
    }

    public function simpanDanAjukan(CreatePurchaseOrder $action, SubmitPurchaseOrder $ajukan): void
    {
        $po = $this->simpanDraf($action);

        if ($po === null) {
            return;
        }

        $this->authorize('submit', $po);

        if ($this->jalankan(fn () => $ajukan->handle($po, auth()->user()))) {
            $this->redirectRoute('purchase-orders.show', $po, navigate: true);
        }
    }

    public function render(): View
    {
        $terbuka = $this->terbuka();
        $itemIds = $terbuka->map(fn ($r) => (int) $r['line']->item_id)->all();

        return view('livewire.purchasing.purchase-order-form', [
            'warehouses' => Warehouse::query()->active()->orderBy('code')->get(['id', 'code', 'name']),
            'vendors' => Vendor::query()->where('is_active', true)->where('status', VendorStatus::Active->value)->orderBy('name')->get(['id', 'code', 'name', 'vendor_type', 'payment_terms']),
            'terbuka' => $terbuka,
            'riwayat' => $this->riwayatHarga($itemIds),
            'batasNaik' => (float) CompanySetting::get('po_price_increase_pct', 10),
            'total' => Money::round($terbuka->sum(fn ($r) => $this->nilai($r['line']->id))),
            'nomor' => $this->poId !== null ? PurchaseOrder::query()->whereKey($this->poId)->value('number') : null,
        ]);
    }

    public function nilai(int|string $lineId): float
    {
        $q = $this->qty[$lineId] ?? '';
        $h = $this->price[$lineId] ?? '';

        return is_numeric($q) && is_numeric($h) ? Money::round((float) $q * (float) $h) : 0.0;
    }

    /**
     * A-304/A-306: per item harga PO terakhir (pembanding kenaikan) dan vendor
     * termurah 6 bulan.
     *
     * @param  array<int, int>  $itemIds
     * @return array<int, array{terakhir: ?array, termurah: ?array}>
     */
    private function riwayatHarga(array $itemIds): array
    {
        $saran = app(VendorSuggestions::class);
        $hasil = [];

        foreach (array_unique($itemIds) as $id) {
            $hasil[$id] = ['terakhir' => $saran->lastPrice($id, $this->poId), 'termurah' => $saran->cheapest($id)];
        }

        return $hasil;
    }

    /** Persen kenaikan harga isian terhadap harga PO terakhir, atau null. */
    public function kenaikan(int|string $lineId, ?array $terakhir): ?float
    {
        $h = $this->price[$lineId] ?? '';

        if ($terakhir === null || ! is_numeric($h) || $terakhir['price'] <= 0) {
            return null;
        }

        return round(((float) $h - $terakhir['price']) / $terakhir['price'] * 100, 1);
    }

    /** Jumlah isian di atas sisa permintaan (A-246). */
    public function lebih(int|string $lineId, float $sisa): float
    {
        $q = $this->qty[$lineId] ?? '';

        return is_numeric($q) && (float) $q - $sisa > 0.00005 ? round((float) $q - $sisa, 4) : 0.0;
    }

    private function simpanDraf(CreatePurchaseOrder $action): ?PurchaseOrder
    {
        $this->validate([
            'form.vendor_id' => ['required'],
            'form.warehouse_id' => ['required'],
        ], attributes: ['form.vendor_id' => __('Vendor'), 'form.warehouse_id' => __('Gudang tujuan')]);

        $baris = [];

        foreach ($this->qty as $lineId => $jumlah) {
            if (is_numeric($jumlah) && (float) $jumlah != 0.0) {
                $baris[] = ['purchase_request_line_id' => (int) $lineId, 'qty_base' => $jumlah, 'unit_price' => $this->price[$lineId] ?? null, 'over_order_reason' => $this->reason[$lineId] ?? null];
            }
        }

        $po = null;

        $this->jalankan(function () use ($action, $baris, &$po) {
            if ($this->poId !== null) {
                $lama = PurchaseOrder::query()->findOrFail($this->poId);
                $this->authorize('update', $lama);
                $po = $action->update($lama, $this->form, $baris, auth()->user());

                return;
            }

            $this->authorize('create', PurchaseOrder::class);
            $po = $action->handle($this->form, $baris, auth()->user());
        });

        return $po;
    }

    /** @return Collection<int, array{line: PurchaseRequestLine, available: float}> */
    private function terbuka()
    {
        return $this->form['warehouse_id'] === ''
            ? collect()
            : app(PurchaseOrderLines::class)->openRequestLines((int) $this->form['warehouse_id'], $this->poId);
    }

    private function isiHarga(bool $timpa): void
    {
        if ($this->form['vendor_id'] === '') {
            return;
        }

        $baris = $this->terbuka();
        $harga = app(VendorPrices::class)->currentMany((int) $this->form['vendor_id'], $baris->map(fn ($r) => (int) $r['line']->item_id)->all());

        foreach ($baris as $r) {
            $id = $r['line']->id;

            if (($timpa || ($this->price[$id] ?? '') === '') && isset($harga[(int) $r['line']->item_id])) {
                $this->price[$id] = $this->angka($harga[(int) $r['line']->item_id]);
            }
        }
    }

    private function angka(float $n): string
    {
        return rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
    }
}

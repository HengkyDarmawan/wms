<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Livewire;

use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Vendor;
use App\Domain\Receipt\Actions\SaveGoodsReceipt;
use App\Domain\Receipt\Enums\ReceiptType;
use App\Domain\Receipt\Enums\VendorReturnStatus;
use App\Domain\Receipt\Livewire\Concerns\HandlesReceiptRules;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\VendorReturn;
use App\Domain\Return\Enums\GoodsReturnStatus;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Return\Models\GoodsReturnLine;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Layar 19-receipt-putaway §6.2 — membuat dan mengubah GRN Draf.
 *
 * Satu baris layar bisa melahirkan beberapa baris GRN: untuk item berserial,
 * setiap nomor serial menjadi satu baris; untuk item per potong, setiap panjang
 * menjadi satu potongan (BR-LED-03, BR-STK-09).
 */
class ReceiptForm extends Component
{
    use HandlesReceiptRules;

    #[Locked]
    public ?int $receiptId = null;

    /** @var array<string, mixed> */
    public array $form = [
        'receipt_type' => 'vendor',
        'warehouse_id' => '',
        'vendor_id' => '',
        'vendor_doc_no' => '',
        'po_ref' => '',
        'shipment_id' => '',
        'vendor_return_id' => '',
        'goods_return_id' => '',
        'notes' => '',
    ];

    /** @var array<int, array<string, string>> baris layar untuk GRN vendor */
    public array $rows = [];

    /** @var array<int|string, string> shipment_line_id => jumlah diterima (GRN transfer) */
    public array $transferQty = [];

    /** @var array<int|string, string> goods_return_line_id => jumlah diterima (GRN retur, A-112) */
    public array $returnQty = [];

    public function mount(?GoodsReceipt $goodsReceipt = null): void
    {
        if ($goodsReceipt !== null && $goodsReceipt->exists) {
            $this->authorize('update', $goodsReceipt);
            $this->muatDraf($goodsReceipt);

            return;
        }

        $this->authorize('create', GoodsReceipt::class);

        $gudang = Warehouse::query()->active()->orderBy('code')->first();
        $this->form['warehouse_id'] = $gudang === null ? '' : (string) $gudang->id;

        $sj = (int) request()->query('shipment', 0);

        if ($sj > 0) {
            $this->form['receipt_type'] = ReceiptType::Transfer->value;
            $this->form['shipment_id'] = (string) $sj;
            $this->updatedFormShipmentId();
        }

        $ret = (int) request()->query('goods_return', 0);

        if ($ret > 0) {
            $this->form['receipt_type'] = ReceiptType::Return->value;
            $this->form['goods_return_id'] = (string) $ret;
            $this->updatedFormGoodsReturnId();
        }

        if ($this->rows === []) {
            $this->tambahBaris();
        }
    }

    public function tambahBaris(): void
    {
        $this->rows[] = ['item_id' => '', 'qty' => '', 'lot_no' => '', 'expiry_date' => '', 'units' => '', 'notes' => ''];
    }

    public function hapusBaris(int $index): void
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
    }

    public function updatedFormShipmentId(): void
    {
        $sj = $this->sj();
        $this->transferQty = [];

        if ($sj === null) {
            return;
        }

        $this->form['warehouse_id'] = (string) $sj->destination_warehouse_id;

        foreach ($sj->lines as $l) {
            if ((float) $l->qty_delivered > 0) {
                $this->transferQty[$l->id] = (string) (float) $l->qty_delivered;
            }
        }
    }

    /** GRN retur: gudang = gudang tujuan RET; jumlah bawaan = yang dikirim (A-112). */
    public function updatedFormGoodsReturnId(): void
    {
        $this->returnQty = [];
        $ret = $this->ret();

        if ($ret === null) {
            return;
        }

        $this->form['warehouse_id'] = (string) $ret->to_warehouse_id;

        foreach ($this->barisRetur($ret) as $b) {
            if ($b['max'] > 0) {
                $this->returnQty[$b['line']->id] = (string) $b['max'];
            }
        }
    }

    public function simpan(SaveGoodsReceipt $action): void
    {
        $grn = $this->receiptId !== null ? GoodsReceipt::query()->findOrFail($this->receiptId) : null;

        $grn !== null ? $this->authorize('update', $grn) : $this->authorize('create', GoodsReceipt::class);

        $this->resetValidation();

        $baris = match ($this->form['receipt_type']) {
            ReceiptType::Transfer->value => collect($this->transferQty)->map(fn ($q, $id) => ['shipment_line_id' => (int) $id, 'qty_received' => (float) $q])->values()->all(),
            ReceiptType::Return->value => collect($this->returnQty)->map(fn ($q, $id) => ['goods_return_line_id' => (int) $id, 'qty_received' => (float) $q])->values()->all(),
            default => $this->barisVendor(),
        };

        $hasil = null;

        $berhasil = $this->jalankan(function () use ($action, $grn, $baris, &$hasil) {
            $hasil = $action->handle($grn, $this->form, $baris, auth()->user());
        });

        if (! $berhasil || $hasil === null) {
            return;
        }

        $this->redirectRoute('receipts.show', $hasil, navigate: true);
    }

    public function render(): View
    {
        $itemIds = collect($this->rows)->pluck('item_id')->filter()->map(fn ($v) => (int) $v)->all();

        return view('livewire.receipt.receipt-form', [
            'types' => ReceiptType::options(),
            'warehouses' => Warehouse::query()->active()->orderBy('code')->get(['id', 'code', 'name']),
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'items' => Item::query()->active()->orderBy('code')->get(['id', 'code', 'name', 'tracking_mode', 'has_expiry']),
            'modes' => Item::query()->whereIn('id', $itemIds)->pluck('tracking_mode', 'id')->all(),
            'incoming' => $this->sjMenunggu(),
            'sj' => $this->sj(),
            'returns' => $this->rtvTerbuka(),
            'returnDocs' => $this->retMenunggu(),
            'ret' => $this->ret(),
            'retLines' => ($r = $this->ret()) === null ? [] : $this->barisRetur($r),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function barisVendor(): array
    {
        $modes = Item::query()
            ->whereIn('id', collect($this->rows)->pluck('item_id')->filter()->map(fn ($v) => (int) $v)->all())
            ->pluck('tracking_mode', 'id');

        $hasil = [];

        foreach ($this->rows as $r) {
            $mode = $modes[(int) ($r['item_id'] ?? 0)] ?? null;
            $dasar = [
                'item_id' => (int) ($r['item_id'] ?? 0),
                'qty_received' => $r['qty'] ?? '',
                'lot_no' => $r['lot_no'] ?? '',
                'expiry_date' => $r['expiry_date'] ?? '',
                'notes' => $r['notes'] ?? '',
            ];

            $unit = collect(preg_split('/[\r\n,;]+/', (string) ($r['units'] ?? '')) ?: [])
                ->map(fn ($v) => trim($v))->filter()->values();

            if ($mode === TrackingMode::Serial || $mode === TrackingMode::Piece) {
                // Tanpa isian unit: biarkan aksi menolak dengan pesan yang tepat.
                foreach ($unit->isEmpty() ? collect(['']) : $unit as $u) {
                    $hasil[] = $dasar + ($mode === TrackingMode::Serial
                        ? ['serial_no' => $u, 'qty_received' => 1]
                        : ['piece_length' => str_replace(',', '.', $u)]);
                }

                continue;
            }

            $hasil[] = $dasar;
        }

        return $hasil;
    }

    private function muatDraf(GoodsReceipt $grn): void
    {
        $this->receiptId = (int) $grn->id;
        $this->form = [
            'receipt_type' => $grn->receipt_type->value,
            'warehouse_id' => (string) $grn->warehouse_id,
            'vendor_id' => (string) ($grn->vendor_id ?? ''),
            'vendor_doc_no' => (string) ($grn->vendor_doc_no ?? ''),
            'po_ref' => (string) ($grn->po_ref ?? ''),
            'shipment_id' => (string) ($grn->shipment_id ?? ''),
            'vendor_return_id' => (string) ($grn->source_type === 'vendor_return' ? $grn->source_id : ''),
            'goods_return_id' => (string) ($grn->goods_return_id ?? ''),
            'notes' => (string) ($grn->notes ?? ''),
        ];

        foreach ($grn->lines()->orderBy('id')->get() as $l) {
            if ($grn->receipt_type === ReceiptType::Transfer) {
                $this->transferQty[$l->shipment_line_id] = (string) (float) $l->qty_received;

                continue;
            }

            if ($grn->receipt_type === ReceiptType::Return) {
                $this->returnQty[$l->goods_return_line_id] = (string) (float) $l->qty_received;

                continue;
            }

            $this->rows[] = [
                'item_id' => (string) $l->item_id,
                'qty' => (string) (float) $l->qty_received,
                'lot_no' => (string) ($l->lot_no ?? ''),
                'expiry_date' => (string) ($l->expiry_date?->toDateString() ?? ''),
                'units' => (string) ($l->serial_no ?? ($l->piece_length !== null ? (float) $l->piece_length : '')),
                'notes' => (string) ($l->notes ?? ''),
            ];
        }
    }

    private function sj(): ?Shipment
    {
        $id = (int) ($this->form['shipment_id'] ?? 0);

        if ($id === 0 || $this->form['receipt_type'] !== ReceiptType::Transfer->value) {
            return null;
        }

        $sj = Shipment::query()->withoutGlobalScopes()
            ->with('warehouse:id,code,name', 'lines.pickTaskLine.item:id,code,name')
            ->find($id);

        // Hanya SJ yang tujuannya gudang dalam cakupan user.
        if ($sj === null || ! Warehouse::query()->whereKey($sj->destination_warehouse_id)->exists()) {
            return null;
        }

        return $sj;
    }

    private function ret(): ?GoodsReturn
    {
        $id = (int) ($this->form['goods_return_id'] ?? 0);

        if ($id === 0 || $this->form['receipt_type'] !== ReceiptType::Return->value) {
            return null;
        }

        $ret = GoodsReturn::query()->withoutGlobalScopes()->with('project:id,code', 'returnShipment.lines.pickTaskLine.pickTask')->find($id);

        // Hanya RET yang gudang tujuannya dalam cakupan user.
        if ($ret === null || ! Warehouse::query()->whereKey($ret->to_warehouse_id)->exists()) {
            return null;
        }

        return $ret;
    }

    /**
     * Baris RET beserta jumlah maksimum yang bisa diterima: jumlah baik bukti
     * terima SJ balik, atau jumlah yang diajukan bila diantar sendiri (BR-GRN-05).
     *
     * @return array<int, array{line: GoodsReturnLine, max: float}>
     */
    private function barisRetur(GoodsReturn $ret): array
    {
        $hasil = [];

        foreach ($ret->requestedLines()->with('item:id,code,name', 'lot', 'serial', 'piece')->orderBy('id')->get() as $l) {
            $max = $ret->returnShipment === null
                ? (float) $l->qty_base
                : (float) $ret->returnShipment->lines
                    ->filter(fn ($x) => $x->pickTaskLine?->pickTask?->source_type === 'goods_return' && (int) $x->pickTaskLine->source_line_id === (int) $l->id)
                    ->sum('qty_delivered');

            $hasil[] = ['line' => $l, 'max' => round($max, 4)];
        }

        return $hasil;
    }

    /** @return Collection<int, GoodsReturn> RET diproses yang belum punya GRN, ke gudang dalam cakupan. */
    private function retMenunggu(): Collection
    {
        return GoodsReturn::query()->withoutGlobalScopes()
            ->with('project:id,code')
            ->where('status', GoodsReturnStatus::InProgress->value)
            ->whereIn('to_warehouse_id', Warehouse::query()->pluck('id')->all())
            ->whereNotIn('id', GoodsReceipt::query()->withoutGlobalScopes()->active()->whereNotNull('goods_return_id')
                ->when($this->receiptId !== null, fn ($q) => $q->where('id', '!=', $this->receiptId))
                ->select('goods_return_id'))
            ->orderByDesc('id')
            ->get(['id', 'number', 'project_id', 'to_warehouse_id']);
    }

    /** @return Collection<int, Shipment> */
    private function sjMenunggu(): Collection
    {
        return Shipment::query()->withoutGlobalScopes()
            ->with('warehouse:id,code')
            ->whereIn('destination_type', ['warehouse', 'site_warehouse'])
            ->whereIn('destination_warehouse_id', Warehouse::query()->pluck('id')->all())
            ->whereIn('status', [ShipmentStatus::Delivered->value, ShipmentStatus::PartiallyDelivered->value])
            // SJ balik RET diterima lewat GRN retur (A-112).
            ->whereNotIn('id', GoodsReturn::query()->withoutGlobalScopes()->whereNotNull('return_shipment_id')->select('return_shipment_id'))
            ->whereNotIn('id', GoodsReceipt::query()->withoutGlobalScopes()->active()->whereNotNull('shipment_id')
                ->when($this->receiptId !== null, fn ($q) => $q->where('id', '!=', $this->receiptId))
                ->select('shipment_id'))
            ->orderByDesc('id')
            ->get(['id', 'number', 'warehouse_id', 'destination_warehouse_id']);
    }

    /** @return Collection<int, VendorReturn> RTV terkirim yang bisa dirujuk barang pengganti (BR-GRN-04). */
    private function rtvTerbuka(): Collection
    {
        if ($this->form['vendor_id'] === '') {
            return collect();
        }

        return VendorReturn::query()
            ->where('vendor_id', (int) $this->form['vendor_id'])
            ->whereIn('status', [VendorReturnStatus::Shipped->value, VendorReturnStatus::Completed->value])
            ->whereNull('replacement_receipt_id')
            ->orderByDesc('id')
            ->get(['id', 'number']);
    }
}

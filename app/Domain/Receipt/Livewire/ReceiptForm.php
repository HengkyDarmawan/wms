<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Livewire;

use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Livewire\Concerns\PicksItemUnit;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Vendor;
use App\Domain\PurchaseRequest\Models\PurchaseRequestOrderLine;
use App\Domain\PurchaseRequest\Support\PurchaseReceipts;
use App\Domain\Receipt\Actions\SaveGoodsReceipt;
use App\Domain\Receipt\Enums\ReceiptType;
use App\Domain\Receipt\Enums\VendorReturnStatus;
use App\Domain\Receipt\Livewire\Concerns\HandlesReceiptRules;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\VendorReturn;
use App\Domain\Return\Enums\GoodsReturnStatus;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Return\Models\GoodsReturnLine;
use App\Domain\Shared\Livewire\Concerns\CariPilihan;
use App\Domain\Shared\Pilihan\Pilihan;
use App\Domain\Shared\Pilihan\SumberPilihan;
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
 *
 * Baris vendor mencatat **Dikirim vendor**, **Baik**, **Rusak** (+ alasan) dan
 * **Kurang** (A-287); Baik terisi otomatis = dikirim − rusak − kurang, Kurang
 * otomatis bila dikosongkan. Jumlah boleh diketik dalam kemasan item
 * (mis. 10 DUS), termasuk kemasan baru "1 DUS = … BOX" (A-291, A-292).
 */
class ReceiptForm extends Component
{
    use CariPilihan;
    use HandlesReceiptRules;
    use PicksItemUnit;

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
        $this->rows[] = $this->barisKosong();
    }

    /**
     * @param  array<string, mixed>  $isi
     * @return array<string, mixed>
     */
    private function barisKosong(array $isi = []): array
    {
        return $isi + [
            'item_id' => '', 'vendor' => '', 'qty' => '', 'damaged' => '', 'short' => '', 'damage_reason' => '',
            'uom' => '', 'uom_lain' => '', 'uom_factor' => '', 'uom_isi' => '', 'ingat' => false,
            'lot_no' => '', 'expiry_date' => '', 'units' => '', 'units_damaged' => '', 'ada_rusak' => false,
            'notes' => '', 'order_line_id' => '', 'bonus' => false,
        ];
    }

    /** A-174: tambah baris yang merujuk baris catatan pemesanan PRQ; jumlah bawaan = sisa pesanan. */
    public function pakaiPesanan(int $orderLineId): void
    {
        $ol = $this->pesananTerbuka()->firstWhere('id', $orderLineId);

        if (! $ol instanceof PurchaseRequestOrderLine) {
            return;
        }

        $sisa = (string) $ol->outstandingQty();
        $baris = $this->barisKosong(['item_id' => (string) $ol->line->item_id, 'vendor' => $sisa, 'qty' => $sisa, 'order_line_id' => (string) $ol->id]);
        $kosong = collect($this->rows)->search(fn ($r) => ($r['item_id'] ?? '') === '' && ($r['qty'] ?? '') === '');

        if ($kosong === false) {
            $this->rows[] = $baris;
        } else {
            $this->rows[$kosong] = $baris;
        }
    }

    /**
     * A-267: jumlah di atas sisa pesanan dipisah menjadi baris Bonus vendor
     * (mis. PO 100, datang 150 karena promo beli 2 gratis 1).
     */
    public function pisahkanBonus(int $index): void
    {
        $r = $this->rows[$index] ?? null;
        $ol = $r === null ? null : $this->pesananTerbuka()->firstWhere('id', (int) ($r['order_line_id'] ?? 0));

        // Pemisahan dihitung dalam satuan dasar; baris berkemasan diubah dulu ke satuan dasar oleh staf.
        if (! $ol instanceof PurchaseRequestOrderLine || ! is_numeric($r['qty'] ?? null) || ($r['uom'] ?? '') !== '') {
            return;
        }

        $rusak = is_numeric($r['damaged'] ?? null) ? (float) $r['damaged'] : 0.0;
        $sisaBaik = round(max(0, $ol->outstandingQty() - $rusak), 4);
        $lebih = round((float) $r['qty'] - $sisaBaik, 4);

        if ($lebih <= 0.00005) {
            return;
        }

        $this->rows[$index]['qty'] = (string) $sisaBaik;
        $this->rows[$index]['vendor'] = '';
        $this->rows[$index]['short'] = '';
        array_splice($this->rows, $index + 1, 0, [$this->barisKosong([
            'item_id' => $r['item_id'], 'qty' => (string) $lebih, 'lot_no' => $r['lot_no'] ?? '', 'expiry_date' => $r['expiry_date'] ?? '',
            'notes' => __('Bonus vendor'), 'bonus' => true,
        ])]);
    }

    /** Baris ditandai bonus = lepas dari pesanan (A-267). */
    public function updatedRows(mixed $value, string $key): void
    {
        [$i, $kolom] = array_pad(explode('.', $key, 2), 2, null);

        if (! isset($this->rows[(int) $i])) {
            return;
        }

        $r = &$this->rows[(int) $i];

        if ($kolom === 'bonus' && $value) {
            $r['order_line_id'] = '';
        }

        if ($kolom === 'item_id') {
            $r = array_merge($r, ['uom' => '', 'uom_lain' => '', 'uom_factor' => '', 'uom_isi' => '', 'ingat' => false]);
        }

        // A-287: Baik = dikirim vendor − rusak − kurang; mengubah Baik membuat Kurang dihitung ulang.
        if (in_array($kolom, ['vendor', 'damaged', 'short'], true) && is_numeric($r['vendor'] ?? null)) {
            $sisa = (float) $r['vendor'] - (float) (is_numeric($r['damaged'] ?? null) ? $r['damaged'] : 0) - (float) (is_numeric($r['short'] ?? null) ? $r['short'] : 0);
            $r['qty'] = (string) round(max(0, $sisa), 4);
        }

        if ($kolom === 'qty') {
            $r['short'] = '';
        }
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

        if ($this->form['receipt_type'] === ReceiptType::Vendor->value) {
            $this->validate($this->aturanPilihan($grn), attributes: ['form.vendor_id' => __('Vendor')]
                + collect($this->rows)->keys()->mapWithKeys(fn ($i) => ["rows.$i.item_id" => __('Item')])->all());
        }

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
        $vendorGrn = $this->form['receipt_type'] === ReceiptType::Vendor->value;

        return view('livewire.receipt.receipt-form', [
            'types' => ReceiptType::options(),
            'warehouses' => Warehouse::query()->active()->orderBy('code')->get(['id', 'code', 'name']),
            'opsiVendor' => $vendorGrn ? $this->pilihanVendor()->awalDengan($this->form['vendor_id']) : [],
            'opsiItem' => $vendorGrn ? $this->pilihanItem()->awalPerBaris(collect($this->rows)->pluck('item_id')->all()) : [],
            'modes' => Item::query()->whereIn('id', $itemIds)->pluck('tracking_mode', 'id')->all(),
            'incoming' => $this->sjMenunggu(),
            'sj' => $this->sj(),
            'returns' => $this->rtvTerbuka(),
            'returnDocs' => $this->retMenunggu(),
            'ret' => $this->ret(),
            'retLines' => ($r = $this->ret()) === null ? [] : $this->barisRetur($r),
            'openOrders' => $pesanan = $this->pesananTerbuka(),
            'sisaPesanan' => $pesanan->mapWithKeys(fn ($ol) => [(int) $ol->id => $ol->outstandingQty()])->all(),
            'unitOpsi' => $opsi = $this->opsiSatuan($itemIds),
            'satuanLain' => $this->satuanKemasan(),
            'hasilSatuan' => collect($this->rows)->map(fn (array $r) => $this->hasilSatuan(
                $r,
                is_numeric($r['vendor'] ?? null) ? $r['vendor'] : (float) ($r['qty'] ?: 0) + (float) ($r['damaged'] ?: 0),
                $opsi[(int) ($r['item_id'] ?: 0)] ?? null,
            ))->all(),
            'alasanRusak' => $this->pilihanAlasan(ReasonContext::Damage),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function barisVendor(): array
    {
        $modes = Item::query()
            ->whereIn('id', collect($this->rows)->pluck('item_id')->filter()->map(fn ($v) => (int) $v)->all())
            ->pluck('tracking_mode', 'id');

        $hasil = [];
        $opsi = $this->opsiSatuan(collect($this->rows)->pluck('item_id')->all());

        foreach ($this->rows as $r) {
            $mode = $modes[(int) ($r['item_id'] ?? 0)] ?? null;
            $alasan = $this->alasanId((string) ($r['damage_reason'] ?? ''), ReasonContext::Damage);
            $dasar = [
                'item_id' => (int) ($r['item_id'] ?? 0),
                'qty_received' => $r['qty'] ?? '',
                'qty_damaged' => $r['damaged'] ?? '',
                'qty_vendor' => $r['vendor'] ?? '',
                'qty_short' => $r['short'] ?? '',
                'damage_reason_id' => $alasan,
                'lot_no' => $r['lot_no'] ?? '',
                'expiry_date' => $r['expiry_date'] ?? '',
                'notes' => $r['notes'] ?? '',
                'purchase_request_order_line_id' => $r['order_line_id'] ?? '',
                'is_bonus' => (bool) ($r['bonus'] ?? false),
            ] + $this->isianSatuan($r, $opsi[(int) ($r['item_id'] ?? 0)] ?? null);

            if ($mode === TrackingMode::Serial || $mode === TrackingMode::Piece) {
                $baik = $this->daftarUnit($r['units'] ?? '');
                $rusak = ($r['ada_rusak'] ?? false) ? $this->daftarUnit($r['units_damaged'] ?? '') : collect();
                $unit = $baik->map(fn ($u) => [$u, false])->merge($rusak->map(fn ($u) => [$u, true]));

                // Kurang (serial): jumlah unit menurut surat jalan vendor − unit yang datang.
                $kurang = is_numeric($r['vendor'] ?? null) && $mode === TrackingMode::Serial
                    ? max(0, (int) $r['vendor'] - $unit->count())
                    : (is_numeric($r['short'] ?? null) ? (float) $r['short'] : 0);

                // Tanpa isian unit: biarkan aksi menolak dengan pesan yang tepat.
                foreach ($unit->isEmpty() ? collect([['', false]]) : $unit as $n => [$u, $unitRusak]) {
                    $hasil[] = array_merge($dasar, [
                        'qty_received' => '', 'qty_damaged' => '', 'qty_vendor' => '',
                        'qty_short' => $n === 0 ? $kurang : 0,
                        'damaged_unit' => $unitRusak,
                        'damage_reason_id' => $unitRusak ? $alasan : null,
                    ], $mode === TrackingMode::Serial
                        ? ['serial_no' => $u]
                        : ['piece_length' => str_replace(',', '.', (string) $u)]);
                }

                continue;
            }

            $hasil[] = $dasar;
        }

        return $hasil;
    }

    /** @return Collection<int, string> */
    private function daftarUnit(mixed $teks): Collection
    {
        return collect(preg_split('/[\r\n,;]+/', (string) $teks) ?: [])->map(fn ($v) => trim($v))->filter()->values();
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

        $lines = $grn->lines()->with('damageReason:id,code')->orderBy('id')->get();
        $opsi = $this->opsiSatuan($lines->pluck('item_id')->all());

        foreach ($lines as $l) {
            if ($grn->receipt_type === ReceiptType::Transfer) {
                $this->transferQty[$l->shipment_line_id] = (string) (float) $l->qty_received;

                continue;
            }

            if ($grn->receipt_type === ReceiptType::Return) {
                $this->returnQty[$l->goods_return_line_id] = (string) (float) $l->qty_received;

                continue;
            }

            // A-291: jumlah dibuka lagi dalam satuan yang diketik (mis. 10 DUS).
            $f = $l->uom_id !== null && (float) $l->uom_qty_base > 0 ? (float) $l->uom_qty_base : 1.0;
            $angka = fn ($v) => $v === null ? '' : (string) round((float) $v / $f, 4);
            $unit = (string) ($l->serial_no ?? ($l->piece_length !== null ? (float) $l->piece_length : ''));
            $unitRusak = ($l->serial_no !== null || $l->piece_length !== null) && (float) $l->qty_damaged > 0;

            $this->rows[] = $this->barisKosong([
                'item_id' => (string) $l->item_id,
                'vendor' => $l->serial_no !== null || $l->piece_length !== null ? '' : $angka($l->qty_vendor),
                'qty' => $angka($l->qty_received),
                'damaged' => (float) $l->qty_damaged > 0 && ! $unitRusak ? $angka($l->qty_damaged) : '',
                'short' => (float) $l->qty_short > 0 ? $angka($l->qty_short) : '',
                'damage_reason' => (string) ($l->damageReason?->code ?? ''),
                'lot_no' => (string) ($l->vendor_batch_no ?? $l->lot_no ?? ''),
                'expiry_date' => (string) ($l->expiry_date?->toDateString() ?? ''),
                'units' => $unitRusak ? '' : $unit,
                'units_damaged' => $unitRusak ? $unit : '',
                'ada_rusak' => $unitRusak,
                'notes' => (string) ($l->notes ?? ''),
                'order_line_id' => (string) ($l->purchase_request_order_line_id ?? ''),
                'bonus' => (bool) $l->is_bonus,
            ] + $this->satuanTersimpan($l->uom_id === null ? null : (int) $l->uom_id, $l->uom_qty_base, $opsi[(int) $l->item_id] ?? null));
        }
    }

    /** @return Collection<int, PurchaseRequestOrderLine> catatan pemesanan PRQ yang menunggu barang dari vendor ini (A-174). */
    private function pesananTerbuka(): Collection
    {
        if ($this->form['receipt_type'] !== ReceiptType::Vendor->value || $this->form['vendor_id'] === '' || $this->form['warehouse_id'] === '') {
            return collect();
        }

        return app(PurchaseReceipts::class)->openLines((int) $this->form['warehouse_id'], (int) $this->form['vendor_id']);
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
                    ->filter(fn ($x) => $ret->returnShipment->isReturnPickup()
                        ? (int) $x->source_line_id === (int) $l->id // SJ jemput (A-248)
                        : $x->pickTaskLine?->pickTask?->source_type === 'goods_return' && (int) $x->pickTaskLine->source_line_id === (int) $l->id)
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

    /** Vendor = daftar lama persis (vendor `is_active`), dicari ke server (A-384, A-391). */
    private function pilihanVendor(): Pilihan
    {
        return Pilihan::dari(Vendor::query()->where('is_active', true)->orderBy('name'), ['code', 'name'], fn (Vendor $v) => [
            'value' => (int) $v->id,
            'text' => $v->code.' — '.$v->name,
        ]);
    }

    /** Item aktif (daftar lama `Item::active()`), dicari ke server. */
    private function pilihanItem(): Pilihan
    {
        return SumberPilihan::item();
    }

    protected function pilihanServer(string $model): ?Pilihan
    {
        // Izin layar diulang seperti aksi simpan.
        $grn = $this->receiptId === null ? null : GoodsReceipt::query()->find($this->receiptId);
        $boleh = $grn === null
            ? $this->receiptId === null && auth()->user()?->can('create', GoodsReceipt::class)
            : auth()->user()?->can('update', $grn);

        if (! $boleh) {
            return null;
        }

        return match (true) {
            $model === 'form.vendor_id' => $this->pilihanVendor(),
            (bool) preg_match('/^rows\.\d+\.item_id$/', $model) => $this->pilihanItem(),
            default => null,
        };
    }

    /**
     * Id dari browser harus ada di daftar (A-384). Dikecualikan: nilai tersimpan
     * di draf ini dan item dari pesanan PRQ terbuka (baris PRQ/bonus), supaya
     * draf lama tetap bisa disimpan seperti sebelumnya (A-391).
     *
     * @return array<string, array<int, mixed>>
     */
    private function aturanPilihan(?GoodsReceipt $grn): array
    {
        $bebas = collect($grn?->lines()->pluck('item_id')->all() ?? [])
            ->merge($this->pesananTerbuka()->map(fn ($ol) => $ol->line?->item_id))
            ->filter()->map(fn ($id) => (string) $id)->unique()->all();

        $aturan = ['form.vendor_id' => (string) $this->form['vendor_id'] === (string) ($grn?->vendor_id ?? '')
            ? [] : [$this->pilihanVendor()->aturan()]];

        foreach ($this->rows as $i => $r) {
            if (! in_array((string) ($r['item_id'] ?? ''), $bebas, true)) {
                $aturan["rows.$i.item_id"] = [$this->pilihanItem()->aturan()];
            }
        }

        return $aturan;
    }
}

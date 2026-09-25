<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Vendor;
use App\Domain\PurchaseRequest\Support\PurchaseReceipts;
use App\Domain\Receipt\Enums\GoodsReceiptStatus;
use App\Domain\Receipt\Enums\ReceiptType;
use App\Domain\Receipt\Enums\VendorReturnStatus;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\GoodsReceiptLine;
use App\Domain\Receipt\Models\VendorReturn;
use App\Domain\Receipt\Support\TrackingRecords;
use App\Domain\Return\Enums\GoodsReturnStatus;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Return\Models\GoodsReturnLine;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Models\ShipmentLine;
use App\Domain\Stock\Support\DocumentNumber;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `receipt.create` — membuat dan mengubah GRN berstatus Draf
 * (Katalog Status §2.5).
 *
 * Dua sumber di Fase 1:
 *
 * - **vendor** — manual tanpa PO (Purchasing baru di Fase 1b, D-29). Baris
 *   diisi item, jumlah, dan isian pelacakan sesuai mode item; boleh merujuk
 *   baris catatan pemesanan PRQ (A-51, 26-purchase-request).
 * - **transfer** — SJ yang tujuannya gudang ini dan sudah punya bukti terima.
 *   Barisnya diturunkan dari baris SJ; jumlahnya tidak boleh melebihi jumlah
 *   **baik** yang diterima (BR-GRN-05), karena yang rusak dan kurang masih
 *   menunggu DSC (BR-SJ-10).
 *
 * - **return** — RET `in_progress` yang gudang tujuannya gudang ini (A-112).
 *   Barisnya diturunkan dari baris RET; jumlahnya tidak boleh melebihi yang
 *   dikirim — jumlah **baik** bukti terima SJ balik, atau jumlah RET bila
 *   diantar sendiri (BR-GRN-05). Satu GRN aktif per RET.
 */
class SaveGoodsReceipt
{
    public function __construct(
        private readonly DocumentNumber $nomor,
        private readonly TrackingRecords $pelacakan,
    ) {}

    /**
     * @param  array<string, mixed>  $header
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function handle(?GoodsReceipt $receipt, array $header, array $lines, ?User $actor = null): GoodsReceipt
    {
        if ($receipt !== null && $receipt->status !== GoodsReceiptStatus::Draft) {
            throw ReceiptRuleException::rule('BR-GEN-01', 'Hanya GRN berstatus Draf yang bisa diubah.');
        }

        $jenis = $receipt?->receipt_type ?? ReceiptType::tryFrom((string) ($header['receipt_type'] ?? ''));

        if ($jenis === null) {
            throw ReceiptRuleException::field('BR-GRN-01', 'receipt_type', 'Sumber penerimaan wajib dipilih.');
        }

        $gudang = $this->gudang($receipt, $header);

        [$kolom, $baris] = match ($jenis) {
            ReceiptType::Vendor => $this->dariVendor($receipt, $gudang, $header, $lines),
            ReceiptType::Transfer => $this->dariTransfer($receipt, $gudang, $header, $lines),
            ReceiptType::Return => $this->dariRetur($receipt, $gudang, $header, $lines),
        };

        return DB::transaction(function () use ($receipt, $jenis, $gudang, $kolom, $baris, $header, $actor) {
            $baru = $receipt === null;

            $receipt ??= new GoodsReceipt([
                'number' => $this->nomor->next('GRN', (string) $gudang->code),
                'warehouse_id' => $gudang->id,
                'receipt_type' => $jenis,
                'status' => GoodsReceiptStatus::Draft,
            ]);

            $receipt->fill($kolom + ['notes' => $this->teks($header['notes'] ?? null)]);
            $receipt->save();

            // Draf boleh ditulis ulang utuh: barisnya belum menyentuh stok.
            // Setelah Diterima baris tidak pernah dihapus (P-03).
            $receipt->lines()->delete();

            foreach ($baris as $b) {
                GoodsReceiptLine::create($b + ['goods_receipt_id' => $receipt->id]);
            }

            activity('receipt')
                ->performedOn($receipt)
                ->causedBy($actor)
                ->withProperties(['baris' => count($baris), 'sumber' => $jenis->value])
                ->log($baru ? 'GRN dibuat' : 'GRN diubah');

            return $receipt->refresh();
        });
    }

    /** @param  array<string, mixed>  $header */
    private function gudang(?GoodsReceipt $receipt, array $header): Warehouse
    {
        if ($receipt !== null) {
            return $receipt->warehouse;
        }

        $id = (int) ($header['warehouse_id'] ?? 0);

        // Global scope membatasi pada gudang dalam cakupan user (BR-ACC-05).
        $gudang = $id > 0 ? Warehouse::query()->find($id) : null;

        if ($gudang === null) {
            throw ReceiptRuleException::field('BR-ACC-05', 'warehouse_id', 'Gudang penerima wajib dipilih dari gudang dalam cakupan Anda.');
        }

        if (! $gudang->is_active) {
            throw ReceiptRuleException::field('BR-WH-07', 'warehouse_id', 'Gudang '.$gudang->code.' nonaktif dan tidak bisa menerima barang.');
        }

        return $gudang;
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function dariVendor(?GoodsReceipt $receipt, Warehouse $gudang, array $header, array $lines): array
    {
        $vendor = Vendor::query()->find((int) ($header['vendor_id'] ?? 0));

        if ($vendor === null) {
            throw ReceiptRuleException::field('BR-GRN-01', 'vendor_id', 'Vendor wajib dipilih.');
        }

        if (! $vendor->is_active || $vendor->status === VendorStatus::Inactive) {
            throw ReceiptRuleException::field('BR-MST-05', 'vendor_id', 'Vendor '.$vendor->name.' nonaktif.');
        }

        $sumber = $this->rtvDiganti($header, $vendor);

        if ($lines === []) {
            throw ReceiptRuleException::rule('BR-GRN-01', 'GRN wajib punya minimal satu baris.');
        }

        $items = Item::query()->whereIn('id', array_map(fn ($l) => (int) ($l['item_id'] ?? 0), $lines))->get()->keyBy('id');
        $baris = [];
        $serial = [];
        $pesanan = [];

        foreach (array_values($lines) as $i => $l) {
            $item = $items->get((int) ($l['item_id'] ?? 0));

            if ($item === null) {
                throw ReceiptRuleException::field('BR-GRN-01', 'item_id', 'Baris '.($i + 1).': item wajib dipilih.');
            }

            $b = $this->pelacakan->normalize($item, $l, $i);

            // A-51: baris boleh merujuk baris catatan pemesanan PRQ (26-purchase-request).
            $ref = is_numeric($l['purchase_request_order_line_id'] ?? null) ? (int) $l['purchase_request_order_line_id'] : 0;

            if ($ref > 0) {
                // Jumlah kumulatif baris yang merujuk catatan yang sama (serial = satu baris per unit).
                $pesanan[$ref] = ($pesanan[$ref] ?? 0) + (float) $b['qty_received'];
                app(PurchaseReceipts::class)->guard($ref, (int) $gudang->id, (int) $vendor->id, (int) $item->id, $pesanan[$ref], $receipt?->id, 'Baris '.($i + 1).' ('.$item->code.')');
                $b['purchase_request_order_line_id'] = $ref;
            }

            if ($b['serial_no'] !== null) {
                $kunci = $item->id.'|'.$b['serial_no'];

                if (isset($serial[$kunci])) {
                    throw ReceiptRuleException::field('BR-LED-04', 'serial_no', 'Serial '.$b['serial_no'].' ditulis dua kali di GRN ini.');
                }

                $serial[$kunci] = true;
            }

            $baris[] = $b;
        }

        return [[
            'vendor_id' => $vendor->id,
            'vendor_doc_no' => $this->teks($header['vendor_doc_no'] ?? null),
            'po_ref' => $this->teks($header['po_ref'] ?? null),
            'source_type' => $sumber === null ? null : 'vendor_return',
            'source_id' => $sumber?->id,
        ], $baris];
    }

    /**
     * BR-GRN-04: barang pengganti masuk lewat GRN baru yang merujuk RTV.
     *
     * @param  array<string, mixed>  $header
     */
    private function rtvDiganti(array $header, Vendor $vendor): ?VendorReturn
    {
        $id = (int) ($header['vendor_return_id'] ?? 0);

        if ($id === 0) {
            return null;
        }

        $rtv = VendorReturn::query()->withoutGlobalScopes()->find($id);

        if ($rtv === null
            || ! in_array($rtv->status, [VendorReturnStatus::Shipped, VendorReturnStatus::Completed], true)
            || (int) $rtv->vendor_id !== (int) $vendor->id) {
            throw ReceiptRuleException::field(
                'BR-GRN-04',
                'vendor_return_id',
                'Barang pengganti hanya untuk RTV yang sudah dikirim ke vendor yang sama.',
            );
        }

        return $rtv;
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function dariTransfer(?GoodsReceipt $receipt, Warehouse $gudang, array $header, array $lines): array
    {
        $sjId = (int) ($receipt?->shipment_id ?? ($header['shipment_id'] ?? 0));

        $sj = Shipment::query()->withoutGlobalScopes()->with('lines.pickTaskLine.item')->find($sjId);

        if ($sj === null) {
            throw ReceiptRuleException::field('BR-GRN-05', 'shipment_id', 'Surat jalan transfer wajib dipilih.');
        }

        $this->pastikanSjBisaDiterima($sj, $gudang, $receipt);

        // SJ balik RET diterima lewat GRN retur ke bin Retur, bukan transfer (A-112).
        if ($sj->lines->contains(fn (ShipmentLine $l) => $l->pickTaskLine?->pickTask?->source_type === 'goods_return')) {
            throw ReceiptRuleException::field(
                'BR-RET-01',
                'shipment_id',
                'Surat jalan '.$sj->number.' adalah SJ balik retur; terima lewat GRN retur dari RET-nya.',
            );
        }

        $isian = [];

        foreach ($lines as $l) {
            $isian[(int) ($l['shipment_line_id'] ?? 0)] = $l;
        }

        $baris = [];

        foreach ($sj->lines as $sl) {
            /** @var ShipmentLine $sl */
            $baik = (float) $sl->qty_delivered;

            if ($baik <= 0) {
                continue;
            }

            $qty = $isian === []
                ? $baik
                : round((float) ($isian[$sl->id]['qty_received'] ?? 0), 4);

            if ($qty <= 0) {
                continue;
            }

            // BR-GRN-05: tidak boleh menerima lebih dari yang dikirim dan diterima baik.
            if ($qty - $baik > 0.00005) {
                throw ReceiptRuleException::field(
                    'BR-GRN-05',
                    'qty_received',
                    'Baris '.$sl->pickTaskLine?->item?->code.': diterima '.$qty.' melebihi jumlah baik '.$baik
                    .'. Kelebihan dicatat lewat penyesuaian stok, bukan GRN.',
                );
            }

            $asal = $sl->pickTaskLine;

            $baris[] = [
                'item_id' => $asal->item_id,
                'shipment_line_id' => $sl->id,
                'lot_id' => $asal->lot_id,
                'serial_id' => $asal->serial_id,
                'piece_id' => $asal->piece_id,
                'qty_received' => $qty,
            ];
        }

        if ($baris === []) {
            throw ReceiptRuleException::rule('BR-GRN-05', 'Tidak ada baris SJ berjumlah baik yang bisa diterima.');
        }

        return [['shipment_id' => $sj->id], $baris];
    }

    /**
     * GRN retur (Katalog §2.5 sumber RET, A-112).
     *
     * @param  array<string, mixed>  $header
     * @param  array<int, array<string, mixed>>  $lines  goods_return_line_id, qty_received
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function dariRetur(?GoodsReceipt $receipt, Warehouse $gudang, array $header, array $lines): array
    {
        $retId = (int) ($receipt?->goods_return_id ?? ($header['goods_return_id'] ?? 0));
        $ret = GoodsReturn::query()->withoutGlobalScopes()->find($retId);

        if ($ret === null) {
            throw ReceiptRuleException::field('BR-RET-01', 'goods_return_id', 'Retur (RET) wajib dipilih.');
        }

        if ($ret->status !== GoodsReturnStatus::InProgress) {
            throw ReceiptRuleException::field('BR-RET-01', 'goods_return_id', 'RET '.$ret->number.' berstatus '.$ret->status->label().'; GRN retur hanya untuk RET yang sedang diproses.');
        }

        if ((int) $ret->to_warehouse_id !== (int) $gudang->id) {
            throw ReceiptRuleException::field('BR-RET-01', 'goods_return_id', 'RET '.$ret->number.' tidak ditujukan ke gudang '.$gudang->code.'.');
        }

        $lain = GoodsReceipt::query()->withoutGlobalScopes()
            ->where('goods_return_id', $ret->id)
            ->where('status', '!=', GoodsReceiptStatus::Cancelled->value)
            ->when($receipt !== null, fn ($q) => $q->where('id', '!=', $receipt->id))
            ->value('number');

        if ($lain !== null) {
            throw ReceiptRuleException::field('BR-GRN-05', 'goods_return_id', 'RET '.$ret->number.' sudah diterima lewat '.$lain.'.');
        }

        $sj = null;

        if ($ret->return_shipment_id !== null) {
            $sj = Shipment::query()->withoutGlobalScopes()->with('lines.pickTaskLine.pickTask')->find($ret->return_shipment_id);

            // A-82 untuk SJ balik: bukti terima dulu, baru GRN.
            if ($sj === null || ! in_array($sj->status, [ShipmentStatus::Delivered, ShipmentStatus::PartiallyDelivered], true)) {
                throw ReceiptRuleException::field('BR-SJ-04', 'goods_return_id', 'SJ balik RET '.$ret->number.' belum punya bukti terima; isi bukti terima lebih dulu.');
            }
        }

        $isian = [];

        foreach ($lines as $l) {
            $isian[(int) ($l['goods_return_line_id'] ?? 0)] = $l;
        }

        $baris = [];

        foreach ($ret->requestedLines()->with('item')->orderBy('id')->get() as $rl) {
            /** @var GoodsReturnLine $rl */
            $sjLine = $sj?->lines->first(fn (ShipmentLine $x) => $x->pickTaskLine?->pickTask?->source_type === 'goods_return'
                && (int) $x->pickTaskLine->source_line_id === (int) $rl->id);
            $maks = $sj !== null ? (float) ($sjLine?->qty_delivered ?? 0) : (float) $rl->qty_base;

            if ($maks <= 0) {
                continue;
            }

            $qty = $isian === [] ? $maks : round((float) ($isian[$rl->id]['qty_received'] ?? 0), 4);

            if ($qty <= 0) {
                continue;
            }

            // BR-GRN-05: tidak boleh menerima lebih dari yang dikirim.
            if ($qty - $maks > 0.00005) {
                throw ReceiptRuleException::field(
                    'BR-GRN-05',
                    'qty_received',
                    'Baris '.$rl->item->code.': diterima '.$qty.' melebihi yang dikirim ('.$maks.'). Kelebihan dicatat lewat penyesuaian stok, bukan GRN.',
                );
            }

            if (($rl->serial_id !== null || $rl->piece_id !== null) && abs($qty - $maks) > 0.00005) {
                throw ReceiptRuleException::field('BR-LED-03', 'qty_received', 'Baris '.$rl->item->code.': serial dan potongan diterima utuh.');
            }

            $baris[] = [
                'item_id' => $rl->item_id,
                'goods_return_line_id' => $rl->id,
                'shipment_line_id' => $sjLine?->id,
                'lot_id' => $rl->lot_id,
                'serial_id' => $rl->serial_id,
                'piece_id' => $rl->piece_id,
                'qty_received' => $qty,
            ];
        }

        if ($baris === []) {
            throw ReceiptRuleException::rule('BR-GRN-05', 'Tidak ada baris retur yang bisa diterima.');
        }

        return [['goods_return_id' => $ret->id, 'shipment_id' => $sj?->id], $baris];
    }

    /** Katalog §2.5: sumber SJ masuk — tujuannya gudang ini dan bukti terimanya sudah ada. */
    private function pastikanSjBisaDiterima(Shipment $sj, Warehouse $gudang, ?GoodsReceipt $receipt): void
    {
        if (! $sj->destination_type->staysInTransitUntilReceipt()
            || (int) $sj->destination_warehouse_id !== (int) $gudang->id) {
            throw ReceiptRuleException::field(
                'BR-SJ-04',
                'shipment_id',
                'Surat jalan '.$sj->number.' tidak ditujukan ke gudang '.$gudang->code.'.',
            );
        }

        if (! in_array($sj->status, [ShipmentStatus::Delivered, ShipmentStatus::PartiallyDelivered], true)) {
            throw ReceiptRuleException::field(
                'BR-SJ-04',
                'shipment_id',
                'Surat jalan '.$sj->number.' belum punya bukti terima; isi bukti terima lebih dulu.',
            );
        }

        // Relasi SJ : GRN = 1 : 1 (BR §1).
        $lain = GoodsReceipt::query()->withoutGlobalScopes()
            ->where('shipment_id', $sj->id)
            ->where('status', '!=', GoodsReceiptStatus::Cancelled->value)
            ->when($receipt !== null, fn ($q) => $q->where('id', '!=', $receipt->id))
            ->value('number');

        if ($lain !== null) {
            throw ReceiptRuleException::field(
                'BR-GRN-05',
                'shipment_id',
                'Surat jalan '.$sj->number.' sudah diterima lewat '.$lain.'.',
            );
        }
    }

    private function teks(mixed $nilai): ?string
    {
        $isi = is_scalar($nilai) ? trim((string) $nilai) : '';

        return $isi === '' ? null : $isi;
    }
}

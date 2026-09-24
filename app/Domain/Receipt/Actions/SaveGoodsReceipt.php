<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Vendor;
use App\Domain\Receipt\Enums\GoodsReceiptStatus;
use App\Domain\Receipt\Enums\ReceiptType;
use App\Domain\Receipt\Enums\VendorReturnStatus;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\GoodsReceiptLine;
use App\Domain\Receipt\Models\VendorReturn;
use App\Domain\Receipt\Support\TrackingRecords;
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
 *   diisi item, jumlah, dan isian pelacakan sesuai mode item.
 * - **transfer** — SJ yang tujuannya gudang ini dan sudah punya bukti terima.
 *   Barisnya diturunkan dari baris SJ; jumlahnya tidak boleh melebihi jumlah
 *   **baik** yang diterima (BR-GRN-05), karena yang rusak dan kurang masih
 *   menunggu DSC (BR-SJ-10).
 *
 * Sumber `return` adalah titik sambung modul Retur dan ditolak sampai modul itu
 * ada (BR-GEN-10).
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

        if ($jenis === ReceiptType::Return) {
            throw ReceiptRuleException::rule(
                'BR-GEN-10',
                'Penerimaan retur dari proyek menunggu modul Retur; belum bisa dibuat.',
            );
        }

        $gudang = $this->gudang($receipt, $header);

        [$kolom, $baris] = $jenis === ReceiptType::Vendor
            ? $this->dariVendor($header, $lines)
            : $this->dariTransfer($receipt, $gudang, $header, $lines);

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
    private function dariVendor(array $header, array $lines): array
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

        foreach (array_values($lines) as $i => $l) {
            $item = $items->get((int) ($l['item_id'] ?? 0));

            if ($item === null) {
                throw ReceiptRuleException::field('BR-GRN-01', 'item_id', 'Baris '.($i + 1).': item wajib dipilih.');
            }

            $b = $this->pelacakan->normalize($item, $l, $i);

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

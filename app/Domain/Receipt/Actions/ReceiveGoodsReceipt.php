<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Adjustment\Actions\CreateStockAdjustment;
use App\Domain\Label\Support\PackageLabelLedger;
use App\Domain\PurchaseRequest\Support\PurchaseReceipts;
use App\Domain\Receipt\Enums\GoodsReceiptStatus;
use App\Domain\Receipt\Enums\ReceiptType;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\GoodsReceiptLine;
use App\Domain\Receipt\Support\QcRequirement;
use App\Domain\Receipt\Support\ReceiptBins;
use App\Domain\Receipt\Support\TrackingRecords;
use App\Domain\Return\Support\ReturnProgress;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Models\ShipmentLine;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Transfer\Support\TransferProgress;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `receipt.receive` — `draft` → `received` (Katalog Status §2.5,
 * BR-GRN-01).
 *
 * Di sinilah stok lahir:
 *
 * - **vendor** — masuk dari luar ke bin Penerimaan (kondisi Tersedia), atau
 *   ke bin Karantina QC (kondisi Karantina) bila item wajib QC; kejadian
 *   `goods_received`.
 * - **transfer** — pindah dari bin Dalam Perjalanan gudang asal ke bin
 *   Penerimaan gudang ini; kejadian `stock_transferred` (matriks §14). Sampai
 *   saat ini barangnya masih milik gudang asal (BR-STK-13). Baris TRF dicatat
 *   diterima (A-107).
 * - **return** — masuk bin Retur gudang ini dari Dalam Perjalanan Gudang Site
 *   (SJ balik), langsung dari bin Gudang Site/On-site (tanpa SJ), atau dari luar
 *   (barang jual-putus dan rusak ditinggal ekspedisi). Tanpa QC dan tanpa
 *   kejadian: kejadian retur terbit saat dipilah (matriks §14, A-112).
 *
 * Semua baris satu transaksi: satu baris gagal, seluruh GRN tetap Draf.
 */
class ReceiveGoodsReceipt
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly ReceiptBins $bins,
        private readonly QcRequirement $qc,
        private readonly TrackingRecords $pelacakan,
        private readonly TransferProgress $transfer,
        private readonly ReturnProgress $retur,
    ) {}

    public function handle(GoodsReceipt $receipt, ?User $actor = null): GoodsReceipt
    {
        if ($receipt->status !== GoodsReceiptStatus::Draft) {
            throw ReceiptRuleException::rule('BR-GEN-01', 'Hanya GRN berstatus Draf yang bisa diterima.');
        }

        $lines = $receipt->lines()->with('item.baseUom', 'shipmentLine', 'returnLine.goodsReturn', 'returnLine.fromBin')->orderBy('id')->get();

        if ($lines->isEmpty()) {
            throw ReceiptRuleException::rule('BR-GRN-01', 'GRN tanpa baris tidak bisa diterima.');
        }

        $receipt->loadMissing('warehouse', 'vendor');

        return DB::transaction(function () use ($receipt, $lines, $actor) {
            $sj = $receipt->shipment_id !== null
                ? Shipment::query()->withoutGlobalScopes()->with('warehouse')->findOrFail($receipt->shipment_id)
                : null;

            foreach ($lines as $line) {
                match ($receipt->receipt_type) {
                    ReceiptType::Transfer => $this->terimaTransfer($receipt, $sj, $line, $actor),
                    ReceiptType::Return => $this->terimaRetur($receipt, $sj, $line, $actor),
                    ReceiptType::Vendor => $this->terimaVendor($receipt, $line, $actor),
                };
            }

            $receipt->forceFill([
                'status' => GoodsReceiptStatus::Received,
                'received_at' => now(),
                'received_by' => $actor?->id,
            ])->save();

            // A-107: jejak TRF; Katalog §2.8: RET `received` saat GRN retur diterima.
            match ($receipt->receipt_type) {
                ReceiptType::Transfer => $this->transfer->received($receipt),
                ReceiptType::Return => $this->retur->received($receipt, $actor),
                // Katalog §2.15: PRQ sebagian terpenuhi / dipenuhi dari GRN yang merujuknya.
                ReceiptType::Vendor => app(PurchaseReceipts::class)->received($receipt, $actor),
            };

            // BR-GRN-05 (A-245): kelebihan terima transfer/retur memicu ADJ `over_receipt`.
            app(CreateStockAdjustment::class)->forOverReceipt($receipt, $actor);

            // BR-GRN-04: GRN pengganti menutup rantai RTV-nya.
            $rtv = $receipt->replacedReturn();

            if ($rtv !== null && $rtv->replacement_receipt_id === null) {
                $rtv->forceFill(['replacement_receipt_id' => $receipt->id])->save();
            }

            activity('receipt')
                ->performedOn($receipt)
                ->causedBy($actor)
                ->withProperties(['baris' => $lines->count(), 'peringatan' => $this->ledger->warnings()])
                ->log('GRN diterima');

            return $receipt->refresh();
        });
    }

    /**
     * A-287: bagian **Baik** masuk bin Penerimaan (atau Karantina bila QC lama
     * wajib, A-79); bagian **Rusak** masuk bin Karantina gudang berkondisi
     * Rusak tanpa QC dan menunggu RTV. Keduanya kejadian `goods_received`
     * dengan kondisi di payload; bagian Kurang tidak menyentuh stok.
     */
    private function terimaVendor(GoodsReceipt $receipt, GoodsReceiptLine $line, ?User $actor): void
    {
        $turunan = $this->pelacakan->materialize($receipt, $line);
        $line->forceFill($turunan)->save();

        $payload = [
            'grn_number' => $receipt->number,
            'receipt_type' => ReceiptType::Vendor->value,
            'vendor_id' => $receipt->vendor_id,
            'vendor_name' => $receipt->vendor?->name,
            'vendor_doc_no' => $receipt->vendor_doc_no,
            'po_ref' => $receipt->po_ref,
        ] + ($line->is_bonus ? ['is_bonus' => true] : [])
            + ((float) $line->qty_short > 0 ? ['qty_vendor' => (float) $line->qty_vendor, 'qty_short' => (float) $line->qty_short] : [])
            + app(PurchaseReceipts::class)->eventPayload($line);

        if ((float) $line->qty_received > 0) {
            $wajibQc = $this->qc->required($line->item, ReceiptType::Vendor);
            $bin = $wajibQc ? $this->bins->quarantine($receipt->warehouse) : $this->bins->receiving($receipt->warehouse);
            $kondisi = $wajibQc ? StockStatus::Quarantine : StockStatus::Available;

            $line->forceFill(['receiving_bin_id' => $bin->id])->save();

            $this->posting($line, $this->gerakanVendor($receipt, $line, $turunan, $actor, (float) $line->qty_received, (int) $bin->id, $kondisi, null,
                $payload + ['qc_required' => $wajibQc, 'stock_status' => $kondisi->value]));
        }

        if ((float) $line->qty_damaged > 0) {
            $karantina = $this->bins->quarantine($receipt->warehouse);

            $line->forceFill(['damaged_bin_id' => $karantina->id])->save();

            $this->posting($line, $this->gerakanVendor($receipt, $line, $turunan, $actor, (float) $line->qty_damaged, (int) $karantina->id, StockStatus::Damaged,
                $line->damage_reason_id === null ? null : (int) $line->damage_reason_id,
                $payload + ['qc_required' => false, 'stock_status' => StockStatus::Damaged->value, 'damage_reason' => $line->damageReason?->code]));
        }
    }

    /**
     * @param  array{lot_id: ?int, serial_id: ?int, piece_id: ?int}  $turunan
     * @param  array<string, mixed>  $payload
     */
    private function gerakanVendor(GoodsReceipt $receipt, GoodsReceiptLine $line, array $turunan, ?User $actor, float $qty, int $binId, StockStatus $kondisi, ?int $alasan, array $payload): MovementRequest
    {
        return new MovementRequest(
            item: $line->item,
            qtyBase: $qty,
            toBinId: $binId,
            stockStatus: $kondisi,
            lotId: $turunan['lot_id'],
            serialId: $turunan['serial_id'],
            pieceId: $turunan['piece_id'],
            documentType: 'goods_receipt',
            documentId: (int) $receipt->id,
            documentLineId: (int) $line->id,
            documentNumber: $receipt->number,
            reasonCodeId: $alasan,
            performedBy: $actor,
            eventType: StockEventType::GoodsReceived,
            eventPayload: $payload,
        );
    }

    private function terimaTransfer(GoodsReceipt $receipt, Shipment $sj, GoodsReceiptLine $line, ?User $actor): void
    {
        // BR-GRN-05 diperiksa ulang: bukti terima bisa saja berubah sejak draf.
        $baik = (float) ($line->shipmentLine?->qty_delivered ?? 0);

        if ((float) $line->qty_received - $baik > 0.00005) {
            throw ReceiptRuleException::rule(
                'BR-GRN-05',
                'Baris '.$line->item->code.' melebihi jumlah baik di bukti terima ('.$baik.').',
            );
        }

        $transit = $this->bins->inTransit($sj->warehouse);
        $bin = $this->bins->receiving($receipt->warehouse);

        $line->forceFill(['receiving_bin_id' => $bin->id])->save();

        $this->posting($line, new MovementRequest(
            item: $line->item,
            qtyBase: (float) $line->qty_received,
            fromBinId: (int) $transit->id,
            toBinId: (int) $bin->id,
            lotId: $line->lot_id,
            serialId: $line->serial_id,
            pieceId: $line->piece_id,
            projectId: $sj->destination_project_id,
            documentType: 'goods_receipt',
            documentId: (int) $receipt->id,
            documentLineId: (int) $line->id,
            documentNumber: $receipt->number,
            performedBy: $actor,
            eventType: StockEventType::StockTransferred,
            eventPayload: [
                // Pembeda dari kejadian tanpa pergerakan saat bukti terima (A-81).
                'phase' => 'goods_receipt',
                'grn_number' => $receipt->number,
                'shipment_number' => $sj->number,
                'from_warehouse_id' => $sj->warehouse_id,
                'to_warehouse_id' => $receipt->warehouse_id,
                'from_project_id' => $sj->warehouse?->project_id,
                'to_project_id' => $receipt->warehouse?->project_id,
            ],
        ));

        $this->bukaLabel($receipt, $line, (int) $bin->id, $line->shipmentLine?->pick_task_line_id, $actor);
    }

    /**
     * A-300: label yang keluar utuh lewat baris PCK $pick kembali Di gudang
     * penerima (bin penerimaan/retur) sejumlah yang diterima; label yang
     * diambil sebagian tetap di gudang asal.
     */
    private function bukaLabel(GoodsReceipt $receipt, GoodsReceiptLine $line, int $binId, mixed $pick, ?User $actor): void
    {
        if ($pick === null) {
            return;
        }

        app(PackageLabelLedger::class)->reopenFromPickLine((int) $pick, (int) $receipt->warehouse_id, $binId, (float) $line->qty_received,
            ['type' => 'goods_receipt', 'id' => (int) $receipt->id, 'line_id' => (int) $line->id, 'number' => $receipt->number], $actor);
    }

    /**
     * A-112: barang retur masuk bin Retur dengan kondisi asalnya (jual-putus
     * Tersedia, rusak ditinggal ekspedisi Rusak). Tidak ada kejadian di sini.
     */
    private function terimaRetur(GoodsReceipt $receipt, ?Shipment $sj, GoodsReceiptLine $line, ?User $actor): void
    {
        $rl = $line->returnLine;

        if ($rl === null) {
            throw ReceiptRuleException::rule('BR-RET-01', 'Baris '.$line->item->code.' tidak merujuk baris RET.');
        }

        // BR-GRN-05 diperiksa ulang untuk SJ balik: bukti terima bisa berubah sejak draf.
        if ($sj !== null) {
            $baik = (float) ($line->shipmentLine?->qty_delivered ?? 0);

            if ((float) $line->qty_received - $baik > 0.00005) {
                throw ReceiptRuleException::rule('BR-GRN-05', 'Baris '.$line->item->code.' melebihi jumlah yang tiba menurut bukti terima SJ balik ('.$baik.').');
            }
        }

        // SJ jemput (A-248) tidak memindahkan stok: barang masih di asalnya
        // (bin On-site untuk aset, di luar kartu stok untuk barang klien).
        $asal = match (true) {
            $sj !== null && ! $sj->isReturnPickup() => (int) $this->bins->inTransit($sj->warehouse)->id,
            $rl->from_bin_id !== null => (int) $rl->from_bin_id,
            default => null,
        };

        $bin = $this->bins->returnBin($receipt->warehouse);

        $line->forceFill(['receiving_bin_id' => $bin->id])->save();

        $this->posting($line, new MovementRequest(
            item: $line->item,
            qtyBase: (float) $line->qty_received,
            fromBinId: $asal,
            toBinId: (int) $bin->id,
            stockStatus: $rl->stock_status,
            lotId: $line->lot_id,
            serialId: $line->serial_id,
            pieceId: $line->piece_id,
            projectId: (int) $rl->goodsReturn?->project_id,
            documentType: 'goods_receipt',
            documentId: (int) $receipt->id,
            documentLineId: (int) $line->id,
            documentNumber: $receipt->number,
            performedBy: $actor,
            notes: 'Retur '.$rl->goodsReturn?->number.' · '.$rl->source()->label(),
        ));

        // A-300: lewat SJ balik (PCK di Gudang Site) atau retur penjualan yang
        // merujuk SJ asal — label yang dulu keluar utuh kembali Di gudang.
        $this->bukaLabel($receipt, $line, (int) $bin->id, match (true) {
            $sj !== null && ! $sj->isReturnPickup() => $line->shipmentLine?->pick_task_line_id,
            $rl->origin_shipment_line_id !== null => ShipmentLine::query()->whereKey($rl->origin_shipment_line_id)->value('pick_task_line_id'),
            default => null,
        }, $actor);
    }

    private function posting(GoodsReceiptLine $line, MovementRequest $request): void
    {
        try {
            $this->ledger->post($request);
        } catch (LedgerException $e) {
            throw ReceiptRuleException::rule(
                $e->rule,
                'Baris '.$line->item?->code.' gagal diterima: '.$e->getMessage(),
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Return\Support;

use App\Domain\Access\Models\User;
use App\Domain\Receipt\Enums\GoodsReceiptStatus;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Return\Enums\GoodsReturnStatus;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Return\Models\GoodsReturnLine;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Stock\Actions\ManageReservation;

/**
 * Transisi otomatis RET dari dokumen pergerakan fisik (Katalog §2.8):
 *
 * - SJ balik disusun → `approved → in_progress` (A-111);
 * - GRN retur `received` → `in_progress → received`, jumlah diterima per baris;
 * - RET dipilah → GRN retur ikut `completed` (A-112).
 *
 * Dipanggil modul Shipment dan Receipt, seperti `RequestFulfillment` untuk REQ.
 */
class ReturnProgress
{
    public function __construct(private readonly ManageReservation $reservasi) {}

    public function returnShipmentPrepared(int $returnId, Shipment $shipment, ?User $actor = null): void
    {
        $ret = GoodsReturn::withoutGlobalScopes()->find($returnId);

        if ($ret === null) {
            return;
        }

        $dari = $ret->status;

        $ret->forceFill([
            'return_shipment_id' => $shipment->id,
            'status' => $ret->status === GoodsReturnStatus::Approved ? GoodsReturnStatus::InProgress : $ret->status,
        ])->save();

        activity('return')->performedOn($ret)->causedBy($actor)
            ->withProperties(['sj' => $shipment->number, 'dari' => $dari->value])
            ->log('SJ balik disusun');
    }

    /** GRN retur diterima: RET `received`, cadangan keras Gudang Site dilepas. */
    public function received(GoodsReceipt $receipt, ?User $actor = null): void
    {
        $ret = GoodsReturn::withoutGlobalScopes()->find($receipt->goods_return_id);

        if ($ret === null) {
            return;
        }

        foreach ($receipt->lines()->get() as $l) {
            $baris = GoodsReturnLine::query()->find($l->goods_return_line_id);

            $baris?->forceFill(['qty_received' => (float) $baris->qty_received + (float) $l->qty_received])->save();
        }

        // Barang sudah pindah dari bin Gudang Site; janjinya tidak diperlukan lagi.
        $this->reservasi->releaseForDocument('goods_return', (int) $ret->id, 'RETURN_RECEIVED', $actor);

        $ret->forceFill(['status' => GoodsReturnStatus::Received, 'received_at' => now()])->save();

        activity('return')->performedOn($ret)->causedBy($actor)
            ->withProperties(['grn' => $receipt->number])
            ->log('Retur diterima di bin Retur');
    }

    /** A-112: GRN retur selesai bersama pemilahan RET-nya. */
    public function sorted(GoodsReturn $ret, ?User $actor = null): void
    {
        $grn = $ret->activeReceipt();

        if ($grn === null || $grn->status !== GoodsReceiptStatus::Received) {
            return;
        }

        $grn->forceFill(['status' => GoodsReceiptStatus::Completed, 'completed_at' => now()])->save();

        activity('receipt')->performedOn($grn)->causedBy($actor)
            ->withProperties(['ret' => $ret->number])
            ->log('GRN retur selesai: barang dipilah');
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Transfer\Support;

use App\Domain\Access\Models\User;
use App\Domain\Receipt\Enums\GoodsReceiptStatus;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\GoodsReceiptLine;
use App\Domain\Shipment\Enums\PickTaskStatus;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Shipment\Models\PickTaskLine;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Models\ShipmentLine;
use App\Domain\Transfer\Enums\TransferStatus;
use App\Domain\Transfer\Models\Transfer;
use App\Domain\Transfer\Models\TransferLine;

/**
 * Jejak TRF dari dokumen pergerakan fisik (Katalog §2.7, A-107).
 *
 * Satu-satunya penulis `transfer_lines.qty_shipped/qty_received` dan transisi
 * `in_progress → completed`. Dipanggil modul Shipment (SJ berangkat) dan
 * Receipt (GRN transfer diterima dan selesai) — pola yang sama dengan
 * `RequestFulfillment` untuk REQ.
 */
class TransferProgress
{
    /** SJ berangkat: jumlah dikirim baris TRF bertambah. */
    public function shipped(ShipmentLine $line): void
    {
        $baris = $this->sumber($line->pickTaskLine);

        if ($baris === null) {
            return;
        }

        $baris->forceFill(['qty_shipped' => (float) $baris->qty_shipped + (float) $line->qty_shipped])->save();
    }

    /** SJ antar site TRF aset berangkat (A-249): baris SJ merujuk baris TRF langsung. */
    public function shippedWithoutPicking(ShipmentLine $line): void
    {
        $baris = TransferLine::query()->find($line->source_line_id);

        $baris?->forceFill(['qty_shipped' => (float) $baris->qty_shipped + (float) $line->qty_shipped])->save();
    }

    /**
     * SJ antar site dibatalkan sebelum berangkat → TRF aset kembali `approved`
     * menunggu SJ baru (A-249).
     */
    public function siteShipmentCancelled(Shipment $shipment, ?User $actor = null): void
    {
        $trf = Transfer::withoutGlobalScopes()->find($shipment->source_id);

        if ($trf === null || $trf->status !== TransferStatus::InProgress) {
            return;
        }

        $trf->forceFill(['status' => TransferStatus::Approved])->save();

        activity('transfer')->performedOn($trf)->causedBy($actor)
            ->withProperties(['sj' => $shipment->number])
            ->log('SJ antar site dibatalkan; TRF menunggu SJ baru');
    }

    /**
     * SJ antar site diterima proyek tujuan (A-249): jumlah diterima baris TRF
     * dan TRF `completed` — aset yang tidak terbawa tetap di proyek asal dan
     * ditindaklanjuti manual (retur atau tandai hilang).
     *
     * @param  array<int, float>  $diterimaPerBaris  transfer_line_id => jumlah tiba
     */
    public function siteShipmentReceived(Shipment $shipment, array $diterimaPerBaris, ?User $actor = null): void
    {
        $trf = Transfer::withoutGlobalScopes()->find($shipment->source_id);

        if ($trf === null) {
            return;
        }

        foreach ($diterimaPerBaris as $id => $qty) {
            $baris = TransferLine::query()->find($id);
            $baris?->forceFill(['qty_received' => (float) $baris->qty_received + $qty])->save();
        }

        $kurang = $trf->lines()->get()->contains(fn (TransferLine $l) => (float) $l->qty_received + 0.00005 < (float) $l->qty_base);

        $trf->forceFill(['status' => TransferStatus::Completed, 'completed_at' => now()])->save();

        activity('transfer')->performedOn($trf)->causedBy($actor)
            ->withProperties(['sj' => $shipment->number, 'ada_yang_tidak_tiba' => $kurang])
            ->log($kurang ? 'TRF aset selesai; sebagian aset tidak tiba dan tetap tercatat di proyek asal' : 'TRF aset selesai: aset diterima proyek tujuan');
    }

    /** GRN transfer `received`: jumlah diterima gudang tujuan. */
    public function received(GoodsReceipt $receipt): void
    {
        foreach ($this->barisGrn($receipt) as [$grnLine, $baris]) {
            /** @var GoodsReceiptLine $grnLine */
            $baris->forceFill(['qty_received' => (float) $baris->qty_received + (float) $grnLine->qty_received])->save();
        }
    }

    /**
     * GRN transfer `completed` → TRF `completed` bila seluruh fisiknya tuntas:
     * semua PCK final (minimal satu selesai), setiap barang yang dipetik sudah
     * dimuat SJ, dan setiap SJ hidupnya sudah punya GRN selesai.
     */
    public function receiptCompleted(GoodsReceipt $receipt, ?User $actor = null): void
    {
        $trfIds = collect($this->barisGrn($receipt))
            ->map(fn (array $p) => (int) $p[1]->transfer_id)
            ->unique();

        foreach ($trfIds as $id) {
            $trf = Transfer::withoutGlobalScopes()->find($id);

            if ($trf !== null && $trf->status === TransferStatus::InProgress && $this->tuntas($trf)) {
                $trf->forceFill(['status' => TransferStatus::Completed, 'completed_at' => now()])->save();

                activity('transfer')->performedOn($trf)->causedBy($actor)
                    ->withProperties(['grn' => $receipt->number])
                    ->log('TRF selesai: diterima gudang tujuan');
            }
        }
    }

    /** Baris TRF yang dipenuhi satu baris PCK (null bila PCK bukan milik TRF). */
    public function sumber(?PickTaskLine $pickLine): ?TransferLine
    {
        if ($pickLine === null || $pickLine->pickTask?->source_type !== 'transfer') {
            return null;
        }

        return TransferLine::query()->find($pickLine->source_line_id);
    }

    private function tuntas(Transfer $trf): bool
    {
        $pck = $trf->pickTasks()->with('lines.shipmentLines.shipment')->get();

        if ($pck->contains(fn ($t) => ! $t->status->isFinal())
            || ! $pck->contains(fn ($t) => $t->status === PickTaskStatus::Completed)) {
            return false;
        }

        foreach ($pck->where('status', PickTaskStatus::Completed) as $t) {
            foreach ($t->lines as $l) {
                $dimuat = $l->shipmentLines->filter(fn (ShipmentLine $s) => $s->shipment?->status !== ShipmentStatus::Cancelled);

                if ((float) $l->qty_picked - (float) $dimuat->sum('qty_shipped') > 0.00005) {
                    return false;
                }

                foreach ($dimuat as $s) {
                    $selesai = GoodsReceipt::query()->withoutGlobalScopes()
                        ->where('shipment_id', $s->shipment_id)
                        ->where('status', GoodsReceiptStatus::Completed->value)
                        ->exists();

                    if (! $selesai) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /** @return array<int, array{0: GoodsReceiptLine, 1: TransferLine}> */
    private function barisGrn(GoodsReceipt $receipt): array
    {
        $hasil = [];

        foreach ($receipt->lines()->with('shipmentLine.pickTaskLine.pickTask')->get() as $l) {
            $baris = $this->sumber($l->shipmentLine?->pickTaskLine);

            if ($baris !== null) {
                $hasil[] = [$l, $baris];
            }
        }

        return $hasil;
    }
}

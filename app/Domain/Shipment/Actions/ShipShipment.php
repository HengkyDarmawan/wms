<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Request\Support\RequestFulfillment;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Models\ShipmentLine;
use App\Domain\Shipment\Support\WarehouseBins;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `shipment.ship` dan `shipment.cancel` — memberangkatkan dan
 * membatalkan surat jalan (Katalog Status §2.3).
 *
 * Saat berangkat, barang pindah dari Loading Area ke bin **Dalam Perjalanan**
 * milik gudang asal. Ia tetap tercatat sebagai stok gudang itu: barang yang
 * ada di truk belum menjadi milik siapa pun selain yang mengirimnya.
 *
 * Setelah berangkat, SJ tidak bisa dibatalkan. Koreksinya lewat DSC atau retur,
 * karena barangnya sudah di jalan dan pembatalan tidak akan mengembalikannya.
 */
class ShipShipment
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly WarehouseBins $bins,
        private readonly RequestFulfillment $pemenuhan,
    ) {}

    public function handle(Shipment $shipment, ?string $notes = null, ?User $actor = null): Shipment
    {
        if ($shipment->status !== ShipmentStatus::Prepared) {
            throw ShipmentRuleException::rule(
                'BR-SJ-07',
                'Hanya surat jalan berstatus Disiapkan yang bisa diberangkatkan.',
            );
        }

        $lines = $shipment->lines()->with('pickTaskLine.item')->get();

        if ($lines->isEmpty()) {
            throw ShipmentRuleException::rule('BR-SJ-09', 'Surat jalan tanpa baris tidak bisa diberangkatkan.');
        }

        $loading = $this->bins->loadingArea($shipment->warehouse);
        $transit = $this->bins->inTransit($shipment->warehouse);

        return DB::transaction(function () use ($shipment, $lines, $loading, $transit, $notes, $actor) {
            foreach ($lines as $l) {
                $this->berangkatkanBaris($shipment, $l, (int) $loading->id, (int) $transit->id, $actor);
                // Jejak di baris REQ: dasar penjaga pembatalan (Katalog §2.1).
                $this->pemenuhan->shipped($l);
            }

            $shipment->forceFill([
                'status' => ShipmentStatus::Shipped,
                'loaded_at' => $shipment->loaded_at ?? now(),
                'shipped_at' => now(),
                'notes' => $notes ?? $shipment->notes,
            ])->save();

            activity('shipment')
                ->performedOn($shipment)
                ->causedBy($actor)
                ->withProperties(['pembawa' => $shipment->carrierLabel(), 'catatan' => $notes])
                ->log('Surat jalan diberangkatkan');

            return $shipment->refresh();
        });
    }

    /** `prepared` → `cancelled`. Barang tetap di Loading Area; PCK tetap selesai. */
    public function cancel(Shipment $shipment, ?int $reasonCodeId, ?User $actor = null): Shipment
    {
        if (! $shipment->status->isCancellable()) {
            throw ShipmentRuleException::rule(
                'BR-SJ-06',
                'Surat jalan berstatus '.$shipment->status->label().' tidak bisa dibatalkan; '
                .'koreksi lewat selisih pengiriman atau retur.',
            );
        }

        if ($reasonCodeId === null) {
            throw ShipmentRuleException::field('BR-GEN-11', 'reasonCode', 'Alasan pembatalan wajib dipilih.');
        }

        $shipment->forceFill([
            'status' => ShipmentStatus::Cancelled,
            'cancel_reason_id' => $reasonCodeId,
        ])->save();

        activity('shipment')
            ->performedOn($shipment)
            ->causedBy($actor)
            ->withProperties(['reason_code_id' => $reasonCodeId])
            ->log('Surat jalan dibatalkan');

        return $shipment->refresh();
    }

    private function berangkatkanBaris(Shipment $shipment, ShipmentLine $line, int $loadingId, int $transitId, ?User $actor): void
    {
        $asal = $line->pickTaskLine;

        try {
            $this->ledger->post(new MovementRequest(
                item: $asal->item,
                qtyBase: (float) $line->qty_shipped,
                fromBinId: $loadingId,
                toBinId: $transitId,
                lotId: $asal->lot_id,
                serialId: $asal->serial_id,
                pieceId: $asal->piece_id,
                projectId: $shipment->destination_project_id,
                documentType: 'shipment',
                documentId: (int) $shipment->id,
                documentLineId: (int) $line->id,
                documentNumber: $shipment->number,
                performedBy: $actor,
                eventType: StockEventType::GoodsShipped,
                eventPayload: [
                    'shipment_number' => $shipment->number,
                    'destination_type' => $shipment->destination_type->value,
                    'shipment_method' => $shipment->shipment_method->value,
                    'carrier' => $shipment->carrier?->name,
                    'tracking_no' => $shipment->tracking_no,
                    'item_code' => $asal->item?->code,
                    'qty_base' => (float) $line->qty_shipped,
                ],
            ));
        } catch (LedgerException $e) {
            throw ShipmentRuleException::rule(
                $e->rule,
                'Baris '.$asal->item?->code.' gagal diberangkatkan: '.$e->getMessage(),
            );
        }
    }
}

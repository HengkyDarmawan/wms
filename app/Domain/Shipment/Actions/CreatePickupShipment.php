<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Return\Enums\GoodsReturnStatus;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Return\Models\GoodsReturnLine;
use App\Domain\Return\Support\ReturnProgress;
use App\Domain\Shipment\Enums\DestinationType;
use App\Domain\Shipment\Enums\OwnershipEffect;
use App\Domain\Shipment\Enums\ShipmentMethod;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Models\ShipmentLine;
use App\Domain\Stock\Support\DocumentNumber;
use App\Domain\Transfer\Enums\TransferStatus;
use App\Domain\Transfer\Models\Transfer;
use App\Domain\Transfer\Models\TransferLine;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `shipment.create` — SJ tanpa PCK (A-247): surat jalan yang
 * berangkat dari proyek, bukan dari bin gudang.
 *
 * **SJ jemput retur** (A-248): barang di tangan klien, aset On-site, atau
 * barang ditinggal ekspedisi yang dijemput driver ke gudang tujuan RET. Satu
 * RET = satu SJ jemput (satu perjalanan); barisnya baris RET. Stok tidak
 * bergerak di SJ ini — barangnya belum/tidak di bin gudang — dan baru masuk
 * kartu stok saat GRN retur (A-112).
 *
 * Siapa yang menjemput wajib tertulis: sopir (user driver atau nama bebas) dan
 * plat (kendaraan master atau plat bebas).
 */
class CreatePickupShipment
{
    public function __construct(private readonly DocumentNumber $nomor) {}

    /** @param  array<string, mixed>  $data  shipment_method, vehicle_id|vehicle_plate, driver_id|carried_by_name, carrier_id, tracking_no, notes */
    public function forGoodsReturn(GoodsReturn $ret, array $data, ?User $actor = null): Shipment
    {
        if ($ret->status !== GoodsReturnStatus::Approved || ! $ret->isPickup()) {
            throw ShipmentRuleException::rule('BR-RET-03', 'SJ jemput hanya untuk RET disetujui yang dijemput driver (bukan stok Gudang Site, bukan diantar sendiri).');
        }

        if ($ret->return_shipment_id !== null) {
            throw ShipmentRuleException::rule('BR-SJ-09', 'RET '.$ret->number.' sudah punya SJ jemput; satu RET satu perjalanan.');
        }

        $pembawa = $this->pembawa($data);
        $gudang = Warehouse::query()->withoutGlobalScopes()->findOrFail($ret->to_warehouse_id);

        if ($actor !== null && ! $actor->canAccessWarehouse((int) $gudang->id)) {
            throw ShipmentRuleException::rule('BR-ACC-05', 'SJ jemput disusun oleh gudang tujuan retur ('.$gudang->code.').');
        }

        $baris = $ret->requestedLines()->orderBy('id')->get();

        return DB::transaction(function () use ($ret, $gudang, $baris, $pembawa, $data, $actor) {
            $sj = Shipment::create($pembawa + [
                'number' => $this->nomor->next('SJ', (string) $gudang->code),
                'warehouse_id' => $gudang->id,
                'destination_type' => DestinationType::Warehouse,
                'destination_warehouse_id' => $gudang->id,
                'source_type' => 'goods_return',
                'source_id' => $ret->id,
                'origin_project_id' => $ret->project_id,
                'status' => ShipmentStatus::Prepared,
                'notes' => $this->teks($data, 'notes'),
            ]);

            foreach ($baris as $l) {
                /** @var GoodsReturnLine $l */
                ShipmentLine::create([
                    'shipment_id' => $sj->id,
                    'source_line_id' => $l->id,
                    'item_id' => $l->item_id,
                    'lot_id' => $l->lot_id,
                    'serial_id' => $l->serial_id,
                    'piece_id' => $l->piece_id,
                    'qty_shipped' => (float) $l->qty_base,
                    // Kepemilikan tidak berubah di perjalanan jemput; retur penjualan
                    // dicatat di baris RET (`ownership`), bukan di SJ.
                    'ownership_effect' => OwnershipEffect::Transfer,
                ]);
            }

            activity('shipment')->performedOn($sj)->causedBy($actor)
                ->withProperties(['ret' => $ret->number, 'baris' => $baris->count(), 'pembawa' => $sj->carrierLabel()])
                ->log('SJ jemput disusun');

            // Katalog §2.8: SJ balik disusun → RET `in_progress`.
            app(ReturnProgress::class)->returnShipmentPrepared((int) $ret->id, $sj, $actor);

            return $sj->refresh();
        });
    }

    /**
     * **SJ antar site** TRF aset (A-249): driver menjemput aset di proyek asal
     * dan mengantarnya ke proyek tujuan. Satu SJ per TRF; penerima di proyek
     * tujuan mengonfirmasi (login atau tautan bertoken), dan saat itulah aset
     * pindah bin On-site.
     *
     * @param  array<string, mixed>  $data
     */
    public function forAssetTransfer(Transfer $trf, array $data, ?User $actor = null): Shipment
    {
        if (! $trf->asset_onsite || $trf->status !== TransferStatus::Approved) {
            throw ShipmentRuleException::rule('BR-RET-02', 'SJ antar site hanya untuk TRF aset antar proyek yang sudah disetujui.');
        }

        if ($trf->liveAssetShipment() !== null) {
            throw ShipmentRuleException::rule('BR-SJ-09', 'TRF '.$trf->number.' sudah punya SJ antar site.');
        }

        $pembawa = $this->pembawa($data);
        $gudang = Warehouse::query()->withoutGlobalScopes()->findOrFail($trf->from_warehouse_id);

        if ($actor !== null && ! $actor->canAccessWarehouse((int) $gudang->id)) {
            throw ShipmentRuleException::rule('BR-ACC-05', 'SJ antar site disusun oleh gudang proyek asal ('.$gudang->code.').');
        }

        $baris = $trf->lines()->orderBy('id')->get();

        return DB::transaction(function () use ($trf, $gudang, $baris, $pembawa, $data, $actor) {
            $sj = Shipment::create($pembawa + [
                'number' => $this->nomor->next('SJ', (string) $gudang->code),
                'warehouse_id' => $gudang->id,
                'destination_type' => DestinationType::ProjectClient,
                'destination_project_id' => $trf->to_project_id,
                'source_type' => 'transfer',
                'source_id' => $trf->id,
                'origin_project_id' => $trf->from_project_id,
                'status' => ShipmentStatus::Prepared,
                'notes' => $this->teks($data, 'notes'),
            ]);

            foreach ($baris as $l) {
                /** @var TransferLine $l */
                ShipmentLine::create([
                    'shipment_id' => $sj->id,
                    'source_line_id' => $l->id,
                    'item_id' => $l->item_id,
                    'serial_id' => $l->serial_id,
                    'qty_shipped' => (float) $l->qty_base,
                    'ownership_effect' => OwnershipEffect::Loan,
                ]);
            }

            $trf->forceFill(['status' => TransferStatus::InProgress])->save();

            activity('shipment')->performedOn($sj)->causedBy($actor)
                ->withProperties(['trf' => $trf->number, 'baris' => $baris->count(), 'pembawa' => $sj->carrierLabel()])
                ->log('SJ antar site disusun');

            activity('transfer')->performedOn($trf)->causedBy($actor)
                ->withProperties(['sj' => $sj->number])
                ->log('SJ antar site disusun; TRF diproses');

            return $sj->refresh();
        });
    }

    /**
     * A-247: sopir dan plat wajib tertulis — dari master atau diketik bebas
     * (truk sewa, sopir ekspedisi).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function pembawa(array $data): array
    {
        $cara = ShipmentMethod::tryFrom((string) ($data['shipment_method'] ?? ''));

        if ($cara === null || $cara === ShipmentMethod::SelfDelivered) {
            throw ShipmentRuleException::field('BR-SJ-07', 'shipment_method', 'Pilih kendaraan sendiri atau ekspedisi untuk menjemput.');
        }

        $kendaraan = $this->id($data, 'vehicle_id');
        $plat = $this->teks($data, 'vehicle_plate');
        $driver = $this->id($data, 'driver_id');
        $sopir = $this->teks($data, 'carried_by_name');
        $ekspedisi = $this->id($data, 'carrier_id');

        if ($cara === ShipmentMethod::Carrier && $ekspedisi === null) {
            throw ShipmentRuleException::field('BR-SJ-07', 'carrier_id', 'Ekspedisi wajib dipilih.');
        }

        if ($kendaraan === null && $plat === null) {
            throw ShipmentRuleException::field('BR-SJ-07', 'vehicle_plate', 'Plat kendaraan penjemput wajib diisi.');
        }

        if ($driver === null && $sopir === null) {
            throw ShipmentRuleException::field('BR-SJ-07', 'carried_by_name', 'Nama sopir penjemput wajib diisi.');
        }

        return [
            'shipment_method' => $cara,
            'vehicle_id' => $kendaraan,
            'vehicle_plate' => $kendaraan === null && $plat !== null ? mb_strtoupper(mb_substr($plat, 0, 20)) : null,
            'driver_id' => $driver,
            'carried_by_name' => $driver === null && $sopir !== null ? mb_substr($sopir, 0, 100) : null,
            'carrier_id' => $cara === ShipmentMethod::Carrier ? $ekspedisi : null,
            'tracking_no' => $this->teks($data, 'tracking_no'),
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function id(array $data, string $key): ?int
    {
        $nilai = $data[$key] ?? null;

        return $nilai === null || $nilai === '' || (int) $nilai === 0 ? null : (int) $nilai;
    }

    /** @param  array<string, mixed>  $data */
    private function teks(array $data, string $key): ?string
    {
        $nilai = is_string($data[$key] ?? null) ? trim($data[$key]) : '';

        return $nilai === '' ? null : $nilai;
    }
}

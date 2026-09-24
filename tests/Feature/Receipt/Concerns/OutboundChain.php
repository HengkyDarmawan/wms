<?php

declare(strict_types=1);

namespace Tests\Feature\Receipt\Concerns;

use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Vehicle;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Shipment\Actions\ConfirmDelivery;
use App\Domain\Shipment\Actions\CreatePickTask;
use App\Domain\Shipment\Actions\CreateShipment;
use App\Domain\Shipment\Actions\ProcessPickTask;
use App\Domain\Shipment\Actions\ShipShipment;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Warehouse\Models\Warehouse;

/**
 * Rantai keluar REQ → PCK → SJ memakai aksi sungguhan, seperti
 * tests/Feature/Request/RequestFulfillmentTest.
 */
trait OutboundChain
{
    protected function reqDisetujui(Project $proyek, Warehouse $sumber, Item $item, float $qty): MaterialRequest
    {
        $pemohon = $this->makeUser('internal_requester');

        $req = app(SaveRequest::class)->handle(
            null,
            ['project_id' => $proyek->id, 'required_date' => now()->addDays(3)->toDateString()],
            [['item_id' => $item->id, 'qty_base' => $qty]],
            $pemohon,
        );

        $req->openLines()->first()->forceFill([
            'source_warehouse_id' => $sumber->id,
            'fulfillment_source' => 'stock',
        ])->save();

        // Tanpa aturan approval, REQ disetujui otomatis saat diajukan (A-08).
        return app(SubmitRequest::class)->handle($req->refresh(), $pemohon);
    }

    protected function pckSelesai(MaterialRequest $req): PickTask
    {
        $staf = $this->makeUser('warehouse_staff');
        $pck = app(CreatePickTask::class)->handle($req, $this->makeUser('warehouse_head'))[0];
        $pck = app(ProcessPickTask::class)->start($pck, $staf);

        foreach ($pck->lines as $baris) {
            app(ProcessPickTask::class)->recordLine($baris, (float) $baris->qty_allocated, null, null, null, $staf);
        }

        return app(ProcessPickTask::class)->complete($pck->refresh(), $staf);
    }

    /** @param  array<string, mixed>  $tujuan */
    protected function sjBerangkat(PickTask $pck, array $tujuan): Shipment
    {
        $sj = app(CreateShipment::class)->handle([$pck->id], $tujuan + [
            'shipment_method' => 'own_fleet',
            'vehicle_id' => Vehicle::create(['plate_no' => 'B'.random_int(1000, 9999).'RC'])->id,
            'driver_id' => $this->makeUser('driver')->id,
        ], $this->makeUser('warehouse_staff'));

        return app(ShipShipment::class)->handle($sj, null, $this->makeUser('driver'));
    }

    protected function buktiTerima(Shipment $sj, float $baik, float $kurang = 0): Shipment
    {
        app(ConfirmDelivery::class)->handle(
            $sj,
            ['received_by_name' => 'Penerima Uji'],
            [[
                'shipment_line_id' => $sj->lines()->first()->id,
                'qty_good' => $baik,
                'qty_damaged' => 0,
                'qty_missing' => $kurang,
            ]],
            $this->makeUser('driver'),
        );

        return $sj->refresh();
    }
}

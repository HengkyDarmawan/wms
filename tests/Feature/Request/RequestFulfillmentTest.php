<?php

declare(strict_types=1);

namespace Tests\Feature\Request;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Models\Vehicle;
use App\Domain\Request\Actions\CancelRequest;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Enums\RequestLineStatus;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Shipment\Actions\ConfirmDelivery;
use App\Domain\Shipment\Actions\CreatePickTask;
use App\Domain\Shipment\Actions\CreateShipment;
use App\Domain\Shipment\Actions\ProcessPickTask;
use App\Domain\Shipment\Actions\ResolveDiscrepancy;
use App\Domain\Shipment\Actions\ShipShipment;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-REQ-27 s.d. TC-REQ-29 — jejak pemenuhan REQ dari dokumen keluar (Katalog §2.1).
 *
 * Uji alur penuh REQ → PCK → SJ → bukti terima tanpa mengisi kolom REQ secara
 * manual: dulu `qty_shipped` hanya diisi uji, sehingga REQ yang barangnya sudah
 * di site tetap bisa dibatalkan.
 */
class RequestFulfillmentTest extends TenantTestCase
{
    private Project $proyek;

    private Warehouse $gudang;

    private Item $item;

    private MaterialRequest $req;

    protected function setUp(): void
    {
        parent::setUp();

        $this->proyek = $this->makeProject();

        $this->gudang = app(SaveWarehouse::class)->handle(null, [
            'code' => 'CKG',
            'name' => 'Gudang Utama Cakung',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);

        $bin = Bin::create([
            'warehouse_id' => $this->gudang->id,
            'code' => 'CKG-A-R01-L1-B01',
            'bin_type' => BinType::Storage,
        ]);

        $this->item = Item::create([
            'code' => 'BAUT-M12',
            'name' => 'Baut M12',
            'status' => ItemStatus::Active,
            'tracking_mode' => TrackingMode::None,
            'base_uom_id' => Uom::query()->where('code', 'PCS')->value('id'),
        ]);

        app(StockLedger::class)->post(new MovementRequest(item: $this->item, qtyBase: 100, toBinId: $bin->id));
    }

    /** REQ 50 disetujui, dipetik, dan diberangkatkan. */
    private function sjBerangkat(): Shipment
    {
        $pemohon = $this->makeUser('internal_requester');

        $req = app(SaveRequest::class)->handle(
            null,
            ['project_id' => $this->proyek->id, 'required_date' => now()->addDays(3)->toDateString()],
            [['item_id' => $this->item->id, 'qty_base' => 50]],
            $pemohon,
        );

        $req->openLines()->first()->forceFill([
            'source_warehouse_id' => $this->gudang->id,
            'fulfillment_source' => 'stock',
        ])->save();

        // Tanpa aturan approval, REQ disetujui otomatis saat diajukan (A-08).
        $this->req = app(SubmitRequest::class)->handle($req->refresh(), $pemohon);

        $staf = $this->makeUser('warehouse_staff');
        $pck = app(CreatePickTask::class)->handle($this->req, $this->makeUser('warehouse_head'))[0];
        $pck = app(ProcessPickTask::class)->start($pck, $staf);

        foreach ($pck->lines as $baris) {
            app(ProcessPickTask::class)->recordLine($baris, (float) $baris->qty_allocated, null, null, null, $staf);
        }

        $pck = app(ProcessPickTask::class)->complete($pck->refresh(), $staf);

        $sj = app(CreateShipment::class)->handle([$pck->id], [
            'destination_type' => 'project_client',
            'destination_project_id' => $this->proyek->id,
            'shipment_method' => 'own_fleet',
            'vehicle_id' => Vehicle::create(['plate_no' => 'B9001XX'])->id,
            'driver_id' => $this->makeUser('driver')->id,
        ], $staf);

        return app(ShipShipment::class)->handle($sj, null, $this->makeUser('driver'));
    }

    private function terima(Shipment $sj, float $baik, float $kurang): void
    {
        app(ConfirmDelivery::class)->handle(
            $sj,
            ['received_by_name' => 'Indra'],
            [[
                'shipment_line_id' => $sj->lines()->first()->id,
                'qty_good' => $baik,
                'qty_damaged' => 0,
                'qty_missing' => $kurang,
            ]],
            $this->makeUser('driver'),
        );
    }

    #[Test]
    public function tc_req_27_sj_berangkat_mencatat_terkirim_dan_menolak_pembatalan(): void
    {
        $this->sjBerangkat();

        $baris = $this->req->refresh()->lines()->first();
        $this->assertSame(50.0, (float) $baris->qty_shipped);
        $this->assertSame(0.0, (float) $baris->qty_reserved);

        try {
            app(CancelRequest::class)->handle(
                $this->req->refresh(),
                (int) ReasonCode::query()->value('id'),
                null,
                $this->makeUser('warehouse_head'),
            );
            $this->fail('REQ yang barangnya sudah dikirim seharusnya tidak bisa dibatalkan.');
        } catch (RequestRuleException $e) {
            $this->assertSame('BR-REQ-09', $e->rule);
        }

        $this->assertSame(MaterialRequestStatus::InProgress, $this->req->refresh()->status);
    }

    #[Test]
    public function tc_req_28_terima_sebagian_lalu_klien_tidak_perlu_menyelesaikan_req(): void
    {
        $sj = $this->sjBerangkat();
        $this->terima($sj, 48, 2);

        $req = $this->req->refresh();
        $baris = $req->lines()->first();
        $this->assertSame(48.0, (float) $baris->qty_received);
        $this->assertSame(RequestLineStatus::Open, $baris->status);
        $this->assertSame(MaterialRequestStatus::PartiallyFulfilled, $req->status);

        $dsc = $sj->refresh()->discrepancies()->with('lines')->first();
        app(ResolveDiscrepancy::class)->handle(
            $dsc,
            $dsc->lines->map(fn ($l) => [
                'line_id' => $l->id,
                'disposition' => 'adjusted',
                'client_decision' => 'not_needed',
                'reason_code_id' => (int) ReasonCode::query()->value('id'),
            ])->all(),
            null,
            $this->makeUser('warehouse_head'),
        );

        $this->assertSame(MaterialRequestStatus::Completed, $this->req->refresh()->status);
    }

    #[Test]
    public function tc_req_29_terima_penuh_menyelesaikan_req(): void
    {
        $this->terima($this->sjBerangkat(), 50, 0);

        $req = $this->req->refresh();
        $this->assertSame(50.0, (float) $req->lines()->first()->qty_received);
        $this->assertSame(RequestLineStatus::Closed, $req->lines()->first()->status);
        $this->assertSame(MaterialRequestStatus::Completed, $req->status);
    }
}

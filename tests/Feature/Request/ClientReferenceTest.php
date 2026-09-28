<?php

declare(strict_types=1);

namespace Tests\Feature\Request;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Models\Vehicle;
use App\Domain\Request\Actions\AddRequestLines;
use App\Domain\Request\Actions\ReviewRequest;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Request\Enums\RequestOrigin;
use App\Domain\Request\Livewire\PortalRequestDetail;
use App\Domain\Request\Livewire\PortalRequestList;
use App\Domain\Request\Livewire\RequestForm;
use App\Domain\Request\Livewire\RequestList;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Shared\Reports\Definitions\ShipmentListReport;
use App\Domain\Shipment\Actions\ConfirmDelivery;
use App\Domain\Shipment\Actions\CreatePickTask;
use App\Domain\Shipment\Actions\CreateShipment;
use App\Domain\Shipment\Actions\ProcessPickTask;
use App\Domain\Shipment\Actions\ShipShipment;
use App\Domain\Shipment\Livewire\ShipmentList;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\TenantTestCase;

/**
 * TC-REQ-39, TC-REQ-40 — No. PO klien di REQ (A-313, A-318) dan pencarian
 * No. PO / No. GR klien di daftar REQ, daftar SJ, dan laporan daftar pengiriman.
 */
class ClientReferenceTest extends TenantTestCase
{
    private Project $proyek;

    private Warehouse $gudang;

    private Item $item;

    private User $klien;

    protected function setUp(): void
    {
        parent::setUp();

        $this->proyek = $this->makeProject();
        $this->gudang = app(SaveWarehouse::class)->handle(null, [
            'code' => 'CKG',
            'name' => 'Gudang Utama Cakung',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);
        $this->item = Item::create([
            'code' => 'BAUT-M12',
            'name' => 'Baut M12',
            'status' => ItemStatus::Active,
            'tracking_mode' => TrackingMode::None,
            'base_uom_id' => Uom::query()->where('code', 'PCS')->value('id'),
        ]);
        $bin = Bin::create(['warehouse_id' => $this->gudang->id, 'code' => 'CKG-A-R01-L1-B01', 'bin_type' => BinType::Storage]);
        app(StockLedger::class)->post(new MovementRequest(item: $this->item, qtyBase: 100, toBinId: $bin->id));

        $this->klien = $this->makeUser('client_user', ScopeType::Project, $this->proyek->id, ['client_id' => $this->proyek->client_id]);
        $this->klien->forgetPermissionCache();
    }

    private function reqKlien(): MaterialRequest
    {
        $req = app(SaveRequest::class)->handle(null, [
            'project_id' => $this->proyek->id,
            'required_date' => now()->addDays(3)->toDateString(),
        ], [['item_id' => $this->item->id, 'qty_base' => 10]], $this->klien);

        return app(SubmitRequest::class)->handle($req, $this->klien);
    }

    private function disetujui(MaterialRequest $req): MaterialRequest
    {
        $staf = $this->makeUser('warehouse_staff');
        app(ReviewRequest::class)->setSource($req->openLines()->first(), (int) $this->gudang->id, 'stock', now()->addDays(5)->toDateString(), $staf);

        return app(ReviewRequest::class)->submitToApproval($req->refresh(), $staf);
    }

    #[Test]
    public function tc_req_39_no_po_klien_di_form_internal_portal_dan_req_tambahan(): void
    {
        // Form internal.
        $pemohon = $this->makeUser('internal_requester');
        Livewire::actingAs($pemohon)->test(RequestForm::class)
            ->set('form.project_id', (string) $this->proyek->id)
            ->set('form.required_date', now()->addDays(4)->toDateString())
            ->set('form.client_po_number', '  PO-INT-0001 ')
            ->set('lines.0.item_id', (string) $this->item->id)
            ->set('lines.0.qty_base', '5')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame('PO-INT-0001', MaterialRequest::query()->withoutGlobalScopes()->latest('id')->value('client_po_number'));

        // Portal: klien mengisi di detail REQ; perubahan tercatat di riwayat.
        $req = $this->reqKlien();
        $this->assertTrue($this->klien->can('setClientPo', $req));

        Livewire::actingAs($this->klien)->test(PortalRequestDetail::class, ['request' => $req])
            ->assertSee(__('No. PO klien'))
            ->call('mintaUbahPo')
            ->set('poKlien', str_repeat('X', 61))
            ->call('simpanPoKlien')
            ->assertHasErrors(['po.client_po_number'])
            ->set('poKlien', 'PO-KL1-001')
            ->call('simpanPoKlien')
            ->assertHasNoErrors()
            ->assertSet('ubahPo', false)
            ->assertSee('PO-KL1-001');

        $this->assertSame('PO-KL1-001', $req->refresh()->client_po_number);
        $this->assertTrue(Activity::query()->where('subject_id', $req->id)->where('description', 'No. PO klien diubah')->exists());

        // Klien proyek lain tidak boleh mengubahnya.
        $lain = $this->makeProject();
        $klienLain = $this->makeUser('client_user', ScopeType::Project, $lain->id, ['client_id' => $lain->client_id]);
        $this->assertFalse($klienLain->can('setClientPo', $req));

        // REQ Tambahan dari REQ disetujui mewarisi No. PO induk (A-318).
        $induk = $this->disetujui($req->refresh());
        $tambahan = app(AddRequestLines::class)->handle($induk, [['item_id' => $this->item->id, 'qty_base' => 2]], $this->klien);
        $this->assertSame(RequestOrigin::Supplement, $tambahan->origin);
        $this->assertSame('PO-KL1-001', $tambahan->client_po_number);
    }

    #[Test]
    public function tc_req_40_no_po_dan_gr_klien_tercari_di_daftar_dan_laporan(): void
    {
        $req = $this->reqKlien();
        $req->forceFill(['client_po_number' => 'PO-KL1-555'])->save();

        Livewire::actingAs($this->makeUser('warehouse_staff'))->test(RequestList::class)
            ->set('search', 'KL1-555')->assertSee($req->number);
        Livewire::actingAs($this->klien)->test(PortalRequestList::class)
            ->set('search', 'KL1-555')->assertSee($req->number);

        // SJ dari REQ itu, lalu bukti terima dengan No. GR klien.
        $staf = $this->makeUser('warehouse_staff');
        $req = $this->disetujui($req);
        $pck = app(CreatePickTask::class)->handle($req, $this->makeUser('warehouse_head'))[0];
        $pck = app(ProcessPickTask::class)->start($pck, $staf);
        foreach ($pck->lines as $baris) {
            app(ProcessPickTask::class)->recordLine($baris, (float) $baris->qty_allocated, null, null, null, $staf);
        }
        $pck = app(ProcessPickTask::class)->complete($pck->refresh(), $staf);

        $sj = app(CreateShipment::class)->handle([$pck->id], [
            'destination_type' => 'project_client',
            'destination_project_id' => $this->proyek->id,
            'shipment_method' => 'own_fleet',
            'vehicle_id' => Vehicle::create(['plate_no' => 'B 7001 PO'])->id,
            'driver_name' => 'Gani',
            'driver_phone' => '081200000008',
        ], $staf);
        $sj = app(ShipShipment::class)->handle($sj, null, $staf);
        app(ConfirmDelivery::class)->handle($sj, [
            'received_by_name' => 'Lina',
            'channel' => 'client_portal',
            'signed_document_path' => 'pod/sj-'.$sj->id.'/sj.jpg',
            'client_gr_number' => 'GR-KL1-9001',
        ], [['shipment_line_id' => $sj->lines()->first()->id, 'qty_good' => 10]], $this->klien);

        $admin = $this->makeUser('company_admin');
        Livewire::actingAs($admin)->test(ShipmentList::class)->set('search', 'KL1-555')->assertSee($sj->number);
        Livewire::actingAs($admin)->test(ShipmentList::class)->set('search', 'GR-KL1-9001')->assertSee($sj->number);
        Livewire::actingAs($admin)->test(ShipmentList::class)->set('search', 'TIDAK-ADA')->assertDontSee($sj->number);

        $this->actingAs($admin);
        $baris = app(ShipmentListReport::class)->rows(['cari' => 'KL1-555', 'date_from' => now()->subDay()->toDateString(), 'date_to' => now()->addDay()->toDateString()]);
        $this->assertCount(1, $baris);
        $this->assertSame('PO-KL1-555', $baris->first()['po_klien']);
        $this->assertSame('GR-KL1-9001', $baris->first()['gr_klien']);
        $this->assertSame('Gani · 6281200000008', $baris->first()['driver']);
        $this->assertCount(0, app(ShipmentListReport::class)->rows(['cari' => 'TIDAK-ADA']));
        $this->assertInstanceOf(Shipment::class, Shipment::query()->withoutGlobalScopes()->find($sj->id));
    }
}

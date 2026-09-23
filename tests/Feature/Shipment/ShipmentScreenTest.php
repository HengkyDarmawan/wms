<?php

declare(strict_types=1);

namespace Tests\Feature\Shipment;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Models\Vehicle;
use App\Domain\Request\Actions\ApproveRequest;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Shipment\Actions\CreatePickTask;
use App\Domain\Shipment\Actions\CreateShipment;
use App\Domain\Shipment\Actions\ProcessPickTask;
use App\Domain\Shipment\Actions\ShipShipment;
use App\Domain\Shipment\Livewire\DiscrepancyList;
use App\Domain\Shipment\Livewire\PickDetail;
use App\Domain\Shipment\Livewire\PickList;
use App\Domain\Shipment\Livewire\ShipmentDetail;
use App\Domain\Shipment\Livewire\ShipmentForm;
use App\Domain\Shipment\Livewire\ShipmentList;
use App\Domain\Shipment\Models\PickTask;
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
use Tests\TenantTestCase;

/**
 * TC-SJ-13 dan TC-SJ-14 — izin layar (BR-GEN-09) dan cakupan (BR-ACC-05),
 * ditambah pemeriksaan keenam layar §6.
 */
class ShipmentScreenTest extends TenantTestCase
{
    private Project $proyek;

    private Warehouse $gudang;

    private Bin $bin;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->proyek = $this->makeProject();

        $this->gudang = app(SaveWarehouse::class)->handle(null, [
            'code' => 'CKG',
            'name' => 'Gudang Utama Cakung',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);

        $this->bin = Bin::create([
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

        app(StockLedger::class)->post(new MovementRequest(
            item: $this->item,
            qtyBase: 100,
            toBinId: $this->bin->id,
        ));
    }

    private function reqDisetujui(float $qty = 20)
    {
        $pemohon = $this->makeUser('internal_requester');

        $req = app(SaveRequest::class)->handle(
            null,
            ['project_id' => $this->proyek->id, 'required_date' => now()->addDays(3)->toDateString()],
            [['item_id' => $this->item->id, 'qty_base' => $qty]],
            $pemohon,
        );

        $req->openLines()->first()->forceFill([
            'source_warehouse_id' => $this->gudang->id,
            'fulfillment_source' => 'stock',
        ])->save();

        return app(ApproveRequest::class)->handle(
            app(SubmitRequest::class)->handle($req->refresh(), $pemohon),
            $this->makeUser('company_admin'),
        );
    }

    private function pckSelesai(float $qty = 20): PickTask
    {
        $staf = $this->makeUser('warehouse_staff');
        $pck = app(CreatePickTask::class)->handle($this->reqDisetujui($qty), $this->makeUser('warehouse_head'))[0];
        $pck = app(ProcessPickTask::class)->start($pck, $staf);

        foreach ($pck->lines as $baris) {
            app(ProcessPickTask::class)->recordLine($baris, (float) $baris->qty_allocated, null, null, null, $staf);
        }

        return app(ProcessPickTask::class)->complete($pck->refresh(), $staf);
    }

    private function sjSiap(): Shipment
    {
        return app(CreateShipment::class)->handle([$this->pckSelesai()->id], [
            'destination_type' => 'project_client',
            'destination_project_id' => $this->proyek->id,
            'shipment_method' => 'own_fleet',
            'vehicle_id' => Vehicle::create(['plate_no' => 'B'.random_int(1000, 9999).'AA'])->id,
            'driver_id' => $this->makeUser('driver')->id,
        ], $this->makeUser('warehouse_staff'));
    }

    #[Test]
    public function tc_sj_13_izin_layar_pengiriman(): void
    {
        $sj = $this->sjSiap();

        $staf = $this->makeUser('warehouse_staff');
        $staf->forgetPermissionCache();

        $this->assertTrue($staf->hasPermission('shipment.view'));
        $this->assertTrue($staf->hasPermission('shipment.create'));
        $this->assertFalse($staf->hasPermission('discrepancy.view'));
        $this->assertFalse($staf->hasPermission('pick.cancel'));

        $this->actingAs($staf)->get($this->tenantUrl('shipments'))->assertOk();
        $this->actingAs($staf)->get($this->tenantUrl('shipments/'.$sj->id))->assertOk();
        $this->actingAs($staf)->get($this->tenantUrl('shipments/create'))->assertOk();
        $this->actingAs($staf)->get($this->tenantUrl('picks'))->assertOk();
        $this->actingAs($staf)->get($this->tenantUrl('discrepancies'))->assertForbidden();

        $kepala = $this->makeUser('warehouse_head');
        $kepala->forgetPermissionCache();

        $this->assertTrue($kepala->hasPermission('discrepancy.resolve'));
        $this->assertFalse(
            $kepala->hasPermission('shipment.confirm_delivery'),
            'Bukti terima diisi driver atau penerima, bukan Kepala Gudang.',
        );

        $this->actingAs($kepala)->get($this->tenantUrl('discrepancies'))->assertOk();
    }

    #[Test]
    public function tc_sj_13b_pemohon_internal_tidak_punya_izin_pengiriman(): void
    {
        $pemohon = $this->makeUser('internal_requester');
        $pemohon->forgetPermissionCache();

        $this->assertFalse($pemohon->hasPermission('shipment.view'));
        $this->assertFalse($pemohon->hasPermission('pick.view'));

        $this->actingAs($pemohon)->get($this->tenantUrl('shipments'))->assertForbidden();
        $this->actingAs($pemohon)->get($this->tenantUrl('picks'))->assertForbidden();
    }

    #[Test]
    public function tc_sj_14_klien_hanya_melihat_sj_proyeknya(): void
    {
        $sj = $this->sjSiap();

        $lain = $this->makeProject();
        $klien = $this->makeUser('client_user', ScopeType::Project, $lain->id, [
            'client_id' => $lain->client_id,
        ]);

        $klien->forgetPermissionCache();

        $this->assertTrue($klien->hasPermission('shipment.view'));
        $this->assertFalse($klien->can('view', $sj), 'Klien proyek lain tidak boleh membuka SJ ini.');

        // Akun klien tidak pernah sampai ke route internal: middleware area
        // memulangkannya ke portal sebelum policy sempat dipanggil. Yang
        // dijaga policy adalah komponen dan aksinya, diperiksa di atas.
        $this->actingAs($klien)
            ->get($this->tenantUrl('shipments/'.$sj->id))
            ->assertRedirect();
    }

    #[Test]
    public function tc_sj_14b_pck_di_luar_cakupan_gudang_tidak_ditemukan(): void
    {
        $pck = $this->pckSelesai();

        $bks = app(SaveWarehouse::class)->handle(null, [
            'code' => 'BKS',
            'name' => 'Gudang Cabang Bekasi',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);

        $kepala = $this->makeUser('warehouse_head', ScopeType::Warehouse, $bks->id);
        $kepala->forgetPermissionCache();

        // Global scope memakai kolom gudang: PCK gudang lain tidak ditemukan
        // sama sekali, jadi keberadaannya pun tidak terkonfirmasi.
        $this->actingAs($kepala)
            ->get($this->tenantUrl('picks/'.$pck->id))
            ->assertNotFound();
    }

    #[Test]
    public function tc_pck_09_layar_picking_mencatat_dan_menyelesaikan(): void
    {
        $req = $this->reqDisetujui();
        $kepala = $this->makeUser('warehouse_head');
        $kepala->forgetPermissionCache();

        // PCK dibuat dari daftar, lewat REQ yang menunggu.
        Livewire::actingAs($kepala)
            ->test(PickList::class)
            ->assertOk()
            ->assertSee($req->number)
            ->call('buatDariReq', $req->id)
            ->assertSet('ruleError', '');

        $pck = PickTask::query()->latest('id')->firstOrFail();

        $staf = $this->makeUser('warehouse_staff');
        $staf->forgetPermissionCache();

        $baris = $pck->lines()->first();

        Livewire::actingAs($staf)
            ->test(PickDetail::class, ['pickTask' => $pck])
            ->assertOk()
            ->call('mulai')
            ->assertSet('ruleError', '')
            ->set('isian.'.$baris->id.'.qty_picked', '20')
            ->call('catat', $baris->id)
            ->assertSet('ruleError', '')
            ->call('selesaikan')
            ->assertSet('ruleError', '');

        $this->assertSame('completed', $pck->refresh()->status->value);
    }

    #[Test]
    public function tc_pck_09b_short_pick_tanpa_alasan_ditolak_layar(): void
    {
        $pck = app(CreatePickTask::class)->handle($this->reqDisetujui(), $this->makeUser('warehouse_head'))[0];

        $staf = $this->makeUser('warehouse_staff');
        $staf->forgetPermissionCache();

        $baris = $pck->lines()->first();

        Livewire::actingAs($staf)
            ->test(PickDetail::class, ['pickTask' => $pck])
            ->call('mulai')
            ->set('isian.'.$baris->id.'.qty_picked', '12')
            ->call('catat', $baris->id)
            ->call('selesaikan')
            ->assertSet('ruleCode', 'BR-SJ-02');

        $this->assertSame('in_progress', $pck->refresh()->status->value);
    }

    #[Test]
    public function tc_sj_15_layar_susun_sj_menolak_cara_kirim_tidak_lengkap(): void
    {
        $pck = $this->pckSelesai();

        $staf = $this->makeUser('warehouse_staff');
        $staf->forgetPermissionCache();

        Livewire::actingAs($staf)
            ->test(ShipmentForm::class)
            ->assertOk()
            ->set('form.warehouse_id', (string) $this->gudang->id)
            ->set('pickTaskIds', [$pck->id])
            ->set('form.destination_type', 'project_client')
            ->set('form.destination_project_id', (string) $this->proyek->id)
            ->set('form.shipment_method', 'own_fleet')
            ->call('simpan')
            ->assertHasErrors(['form.vehicle_id'])
            ->assertSet('ruleCode', 'BR-SJ-07');
    }

    #[Test]
    public function tc_sj_16_layar_detail_memberangkatkan_dan_mengisi_bukti_terima(): void
    {
        $sj = $this->sjSiap();

        $driver = $this->makeUser('driver');
        $driver->forgetPermissionCache();

        Livewire::actingAs($driver)
            ->test(ShipmentDetail::class, ['shipment' => $sj])
            ->assertOk()
            ->call('berangkatkan')
            ->assertSet('ruleError', '');

        $this->assertSame('shipped', $sj->refresh()->status->value);

        $baris = $sj->lines()->first();

        Livewire::actingAs($driver)
            ->test(ShipmentDetail::class, ['shipment' => $sj])
            ->call('mintaDialog', 'terima')
            ->assertSet('terima.'.$baris->id.'.qty_good', '20')
            ->set('form.received_by_name', 'Pak Budi')
            ->call('simpanBuktiTerima')
            ->assertSet('ruleError', '');

        $this->assertSame('delivered', $sj->refresh()->status->value);
    }

    #[Test]
    public function tc_sj_16b_bukti_terima_tidak_genap_ditolak_layar(): void
    {
        $sj = app(ShipShipment::class)->handle($this->sjSiap(), null, $this->makeUser('driver'));

        $driver = $this->makeUser('driver');
        $driver->forgetPermissionCache();

        $baris = $sj->lines()->first();

        Livewire::actingAs($driver)
            ->test(ShipmentDetail::class, ['shipment' => $sj])
            ->call('mintaDialog', 'terima')
            ->set('form.received_by_name', 'Pak Budi')
            ->set('terima.'.$baris->id.'.qty_good', '18')
            ->call('simpanBuktiTerima')
            ->assertSet('ruleCode', 'BR-SJ-05');

        $this->assertSame('shipped', $sj->refresh()->status->value);
    }

    #[Test]
    public function tc_dsc_06_layar_selisih_menuntut_disposisi_lengkap(): void
    {
        $sj = app(ShipShipment::class)->handle($this->sjSiap(), null, $this->makeUser('driver'));
        $baris = $sj->lines()->first();

        app(\App\Domain\Shipment\Actions\ConfirmDelivery::class)->handle(
            $sj,
            ['received_by_name' => 'Pak Budi'],
            [[
                'shipment_line_id' => $baris->id,
                'qty_good' => 17,
                'qty_missing' => 3,
            ]],
            $this->makeUser('driver'),
        );

        $dsc = $sj->refresh()->discrepancies()->with('lines')->first();
        $barisDsc = $dsc->lines->first();

        $kepala = $this->makeUser('warehouse_head');
        $kepala->forgetPermissionCache();

        Livewire::actingAs($kepala)
            ->test(DiscrepancyList::class)
            ->assertOk()
            ->assertSee($dsc->number)
            ->call('mintaSelesaikan', $dsc->id)
            // Disposisi belum dipilih: ditolak dengan kode aturannya.
            ->call('selesaikan')
            ->assertSet('ruleCode', 'BR-SJ-10')
            ->set('keputusan.'.$barisDsc->id.'.disposition', 'reship')
            ->call('selesaikan')
            ->assertSet('ruleError', '');

        $this->assertSame('resolved', $dsc->refresh()->status->value);
    }

    #[Test]
    public function tc_sj_17_daftar_sj_menandai_yang_masih_berselisih(): void
    {
        $sj = app(ShipShipment::class)->handle($this->sjSiap(), null, $this->makeUser('driver'));

        app(\App\Domain\Shipment\Actions\ConfirmDelivery::class)->handle(
            $sj,
            ['received_by_name' => 'Pak Budi'],
            [['shipment_line_id' => $sj->lines()->first()->id, 'qty_good' => 18, 'qty_missing' => 2]],
            $this->makeUser('driver'),
        );

        $admin = $this->makeUser('company_admin');

        Livewire::actingAs($admin)
            ->test(ShipmentList::class)
            ->assertOk()
            ->assertSee($sj->number)
            ->assertSee('1 selisih terbuka')
            ->set('hanyaBerselisih', true)
            ->assertSee($sj->number);
    }
}

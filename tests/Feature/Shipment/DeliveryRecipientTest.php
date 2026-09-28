<?php

declare(strict_types=1);

namespace Tests\Feature\Shipment;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Models\Vehicle;
use App\Domain\Notification\Models\Notification;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Shipment\Actions\ConfirmDelivery;
use App\Domain\Shipment\Actions\CreatePickTask;
use App\Domain\Shipment\Actions\CreateShipment;
use App\Domain\Shipment\Actions\ProcessPickTask;
use App\Domain\Shipment\Actions\ShipShipment;
use App\Domain\Shipment\Enums\ProofChannel;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Shipment\Livewire\PortalDeliveryProof;
use App\Domain\Shipment\Livewire\ShipmentDetail;
use App\Domain\Shipment\Livewire\ShipmentForm;
use App\Domain\Shipment\Livewire\ShipmentList;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Support\DeliveryRecipients;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Support\DocumentPrinter;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use App\Http\Middleware\InitializeTenancyBySubdomain;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\FileUploadController;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-SJ-22–29 — driver tanpa akun (A-311, A-315) dan bukti terima oleh pihak
 * penerima (A-312, A-316, A-317), No. PO/GR klien di cetakan (A-313),
 * notifikasi SJ berangkat (A-319), dan data driver lama (A-314).
 */
class DeliveryRecipientTest extends TenantTestCase
{
    private Project $proyek;

    private Warehouse $gudang;

    private Item $item;

    private ?User $pemohon = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->proyek = $this->makeProject();

        $this->gudang = app(SaveWarehouse::class)->handle(null, [
            'code' => 'CKG',
            'name' => 'Gudang Utama Cakung',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);

        $bin = Bin::create(['warehouse_id' => $this->gudang->id, 'code' => 'CKG-A-R01-L1-B01', 'bin_type' => BinType::Storage]);

        $this->item = Item::create([
            'code' => 'BAUT-M12',
            'name' => 'Baut M12',
            'status' => ItemStatus::Active,
            'tracking_mode' => TrackingMode::None,
            'base_uom_id' => Uom::query()->where('code', 'PCS')->value('id'),
        ]);

        app(StockLedger::class)->post(new MovementRequest(item: $this->item, qtyBase: 200, toBinId: $bin->id));
    }

    // ---------------------------------------------------------------- bantuan

    private function pckSelesai(float $qty = 20, ?string $poKlien = null): int
    {
        $this->pemohon = $this->makeUser('internal_requester');
        $req = app(SaveRequest::class)->handle(null, [
            'project_id' => $this->proyek->id,
            'required_date' => now()->addDays(3)->toDateString(),
            'client_po_number' => $poKlien,
        ], [['item_id' => $this->item->id, 'qty_base' => $qty]], $this->pemohon);
        $req->openLines()->first()->forceFill(['source_warehouse_id' => $this->gudang->id, 'fulfillment_source' => 'stock'])->save();
        $req = app(SubmitRequest::class)->handle($req->refresh(), $this->pemohon);

        $staf = $this->makeUser('warehouse_staff');
        $pck = app(CreatePickTask::class)->handle($req, $this->makeUser('warehouse_head'))[0];
        $pck = app(ProcessPickTask::class)->start($pck, $staf);

        foreach ($pck->lines as $baris) {
            app(ProcessPickTask::class)->recordLine($baris, (float) $baris->qty_allocated, null, null, null, $staf);
        }

        return (int) app(ProcessPickTask::class)->complete($pck->refresh(), $staf)->id;
    }

    /** @param  array<string, mixed>  $tujuan */
    private function sjBerangkat(array $tujuan = [], ?string $poKlien = null): Shipment
    {
        $sj = app(CreateShipment::class)->handle([$this->pckSelesai(20, $poKlien)], $tujuan + [
            'destination_type' => 'project_client',
            'destination_project_id' => $this->proyek->id,
            'shipment_method' => 'own_fleet',
            'vehicle_id' => Vehicle::create(['plate_no' => 'B'.random_int(1000, 9999).'DR'])->id,
            'driver_name' => 'Gani',
            'driver_phone' => '0812-0000-0008',
        ], $this->makeUser('warehouse_staff'));

        return app(ShipShipment::class)->handle($sj, null, $this->makeUser('warehouse_staff'));
    }

    private function klien(?Project $proyek = null): User
    {
        $proyek ??= $this->proyek;
        $u = $this->makeUser('client_user', ScopeType::Project, $proyek->id, ['client_id' => $proyek->client_id]);
        $u->forgetPermissionCache();

        return $u;
    }

    private function aturan(callable $aksi): ShipmentRuleException
    {
        try {
            $aksi();
        } catch (ShipmentRuleException $e) {
            return $e;
        }

        $this->fail('Aksi seharusnya ditolak aturan.');
    }

    // ------------------------------------------------------------------- uji

    #[Test]
    public function tc_sj_22_sj_kendaraan_sendiri_tanpa_akun_driver(): void
    {
        $data = [
            'destination_type' => 'project_client',
            'destination_project_id' => $this->proyek->id,
            'shipment_method' => 'own_fleet',
            'vehicle_id' => Vehicle::create(['plate_no' => 'B 9001 XX', 'default_driver_name' => 'Hadi', 'default_driver_phone' => '6281200000009'])->id,
        ];
        $pck = $this->pckSelesai();
        $staf = $this->makeUser('warehouse_staff');

        $e = $this->aturan(fn () => app(CreateShipment::class)->handle([$pck], $data, $staf));
        $this->assertSame('BR-SJ-07', $e->rule);
        $this->assertArrayHasKey('driver_name', $e->fieldErrors);

        $e = $this->aturan(fn () => app(CreateShipment::class)->handle([$pck], $data + ['driver_name' => 'Hadi'], $staf));
        $this->assertArrayHasKey('driver_phone', $e->fieldErrors);

        $e = $this->aturan(fn () => app(CreateShipment::class)->handle([$pck], $data + ['driver_name' => 'Hadi', 'driver_phone' => '12ab'], $staf));
        $this->assertArrayHasKey('driver_phone', $e->fieldErrors);

        $sj = app(CreateShipment::class)->handle([$pck], $data + ['driver_name' => 'Hadi', 'driver_phone' => '0812-0000-0009'], $staf);
        $this->assertNull($sj->driver_id);
        $this->assertSame('Hadi', $sj->driver_name);
        $this->assertSame('6281200000009', $sj->driver_phone);
        $this->assertStringContainsString('Hadi · 6281200000009', $sj->carrierLabel());

        // Form SJ: memilih kendaraan mengisi driver bawaan (nama & HP teks).
        $staf->forgetPermissionCache();
        Livewire::actingAs($staf)->test(ShipmentForm::class)
            ->set('form.shipment_method', 'own_fleet')
            ->set('form.vehicle_id', (string) $data['vehicle_id'])
            ->assertSet('form.driver_name', 'Hadi')
            ->assertSet('form.driver_phone', '6281200000009')
            ->assertSee(__('No. HP driver'));
    }

    #[Test]
    public function tc_sj_23_klien_mengisi_bukti_terima_di_portal_dan_req_terkonfirmasi_otomatis(): void
    {
        $sj = $this->sjBerangkat(poKlien: 'PO-KL1-001');
        $klien = $this->klien();

        $this->assertSame(ProofChannel::ClientPortal, app(DeliveryRecipients::class)->channelFor($klien, $sj));

        $this->actingAs($klien)->get($this->tenantUrl('portal/shipments/'.$sj->id.'/proof'))
            ->assertOk()->assertSee($sj->number)->assertSee('PO-KL1-001')->assertSee(__('Foto SJ bertanda tangan & cap'));

        $baris = $sj->lines()->first();

        $layar = Livewire::actingAs($klien)->test(PortalDeliveryProof::class, ['shipment' => $sj])
            ->assertSet('form.received_by_name', $klien->name)
            ->set('terima.'.$baris->id.'.qty_good', '18')
            ->set('terima.'.$baris->id.'.qty_missing', '2')
            ->set('form.client_gr_number', 'GR-KL1-7788')
            ->call('simpanBuktiTerima')
            ->assertHasErrors(['fotoSj']);

        $this->assertSame('shipped', $sj->refresh()->status->value);

        $layar->set('fotoSj', UploadedFile::fake()->image('sj-ttd-cap.jpg', 300, 400))
            ->call('simpanBuktiTerima')
            ->assertHasNoErrors()
            ->assertSet('ruleError', '')
            ->assertRedirect();

        $sj->refresh();
        $bukti = $sj->proof;
        $this->assertSame('partially_delivered', $sj->status->value);
        $this->assertSame(ProofChannel::ClientPortal, $bukti->channel);
        $this->assertSame('GR-KL1-7788', $bukti->client_gr_number);
        $this->assertNotNull($bukti->signed_document_path);
        $this->assertSame((int) $klien->id, (int) $bukti->received_by_user_id);
        // A-317: diisi admin site klien — konfirmasi pemohon langsung tercatat, DSC tetap terbuka.
        $this->assertSame('confirmed', $bukti->confirmation->value);
        $this->assertNotNull($bukti->requester_confirmed_at);
        $this->assertNull($bukti->confirm_deadline_at);
        $this->assertTrue($sj->discrepancies()->where('status', 'open')->exists());

        // Unggahan Livewire di browser membaca sesi company: rute unggah memakai tenancy (tanpa ini 419).
        $mw = array_map(fn ($m) => $m->middleware, FileUploadController::middleware());
        $this->assertSame(InitializeTenancyBySubdomain::class, $mw[0]);
    }

    #[Test]
    public function tc_sj_24_klien_lain_dan_user_asal_tanpa_cadangan_ditolak(): void
    {
        $sj = $this->sjBerangkat();

        $lain = $this->makeProject();
        $klienLain = $this->klien($lain);
        $this->assertFalse($klienLain->can('confirmDelivery', $sj));
        $this->actingAs($klienLain)->get($this->tenantUrl('portal/shipments/'.$sj->id.'/proof'))->assertForbidden();

        // Staf gudang asal bukan penerima dan tidak punya jalur cadangan.
        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->gudang->id);
        $staf->forgetPermissionCache();
        $this->assertFalse($staf->can('confirmDelivery', $sj));

        // Kepala Gudang di gudang lain tidak bisa memakai cadangan untuk SJ CKG.
        $bks = app(SaveWarehouse::class)->handle(null, [
            'code' => 'BKS', 'name' => 'Gudang Bekasi',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);
        $kepalaLain = $this->makeUser('warehouse_head', ScopeType::Warehouse, $bks->id);
        $kepalaLain->forgetPermissionCache();
        $this->assertFalse($kepalaLain->can('confirmDelivery', $sj));

        // Setelah terisi, klien sendiri pun tidak bisa mengisi ulang.
        $klien = $this->klien();
        $this->assertTrue($klien->can('confirmDelivery', $sj));
        $sj->forceFill(['status' => 'delivered'])->save();
        $this->actingAs($klien)->get($this->tenantUrl('portal/shipments/'.$sj->id.'/proof'))->assertForbidden();
    }

    #[Test]
    public function tc_sj_25_sj_ke_gudang_site_diisi_user_bercakupan_tujuan(): void
    {
        $site = app(SaveWarehouse::class)->handle(null, [
            'code' => 'SITE1',
            'name' => 'Gudang Site 1',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::SITE)->value('id'),
            'project_id' => $this->proyek->id,
        ]);
        $sj = $this->sjBerangkat(['destination_type' => 'site_warehouse', 'destination_warehouse_id' => $site->id]);

        $stafSite = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $site->id);
        $stafSite->forgetPermissionCache();
        $stafAsal = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->gudang->id);
        $stafAsal->forgetPermissionCache();

        $this->assertFalse($stafAsal->can('confirmDelivery', $sj));
        $this->assertTrue($stafSite->can('confirmDelivery', $sj));
        $this->assertFalse($stafSite->can('ship', $sj), 'Penerima tidak memegang aksi gudang asal.');

        // Penerima menemukan SJ di daftar walau gudang asalnya di luar cakupan.
        Livewire::actingAs($stafSite)->test(ShipmentList::class)->assertSee($sj->number);
        $this->actingAs($stafSite)->get($this->tenantUrl('shipments/'.$sj->id))->assertOk()->assertSee(__('Isi bukti terima'));

        Livewire::actingAs($stafSite)->test(ShipmentDetail::class, ['shipment' => $sj])
            ->call('mintaDialog', 'terima')
            ->assertSet('form.received_by_name', $stafSite->name)
            ->call('simpanBuktiTerima')
            ->assertHasNoErrors()
            ->assertSet('ruleError', '');

        $bukti = $sj->refresh()->proof;
        $this->assertSame(ProofChannel::RecipientAccount, $bukti->channel);
        $this->assertNull($bukti->signed_document_path, 'Foto SJ opsional bagi penerima internal (A-316).');
    }

    #[Test]
    public function tc_sj_26_cadangan_kepala_gudang_asal_wajib_foto_sj(): void
    {
        $sj = $this->sjBerangkat();
        $kepala = $this->makeUser('warehouse_head', ScopeType::Warehouse, $this->gudang->id);
        $kepala->forgetPermissionCache();

        $this->assertSame(ProofChannel::SignedDocument, app(DeliveryRecipients::class)->channelFor($kepala, $sj));

        // Aksi menolak tanpa foto SJ walau dipanggil langsung (bukan hanya layar).
        $e = $this->aturan(fn () => app(ConfirmDelivery::class)->handle(
            $sj, ['received_by_name' => 'Pak Site', 'channel' => 'signed_document'],
            [['shipment_line_id' => $sj->lines()->first()->id, 'qty_good' => 20]], $kepala,
        ));
        $this->assertSame('BR-SJ-05', $e->rule);
        $this->assertArrayHasKey('signed_document_path', $e->fieldErrors);

        Livewire::actingAs($kepala)->test(ShipmentDetail::class, ['shipment' => $sj])
            ->call('mintaDialog', 'terima')
            ->assertSee(__('Cadangan: Anda mengisi dari SJ yang sudah ditandatangani & dicap penerima.'))
            ->set('form.received_by_name', 'Pak Site')
            ->set('fotoSj', UploadedFile::fake()->image('sj.jpg', 300, 400))
            ->call('simpanBuktiTerima')
            ->assertHasNoErrors();

        $bukti = $sj->refresh()->proof;
        $this->assertSame(ProofChannel::SignedDocument, $bukti->channel);
        $this->assertNull($bukti->confirmation, 'Cadangan gudang tidak mengonfirmasi atas nama pemohon.');
    }

    #[Test]
    public function tc_sj_27_sj_berangkat_memberi_tahu_admin_site_klien(): void
    {
        $klien = $this->klien();
        $admin = $this->makeUser('company_admin');
        $sj = $this->sjBerangkat();

        $notif = Notification::query()->where('user_id', $klien->id)->where('type', 'shipment.shipped')->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString($sj->number, $notif->title);
        $this->assertSame('/portal/shipments/'.$sj->id.'/proof', $notif->url);

        // Pemohon REQ (penerima internal) juga diberi tahu; admin bercakupan semua tidak.
        $this->assertTrue(Notification::query()->where('user_id', $this->pemohon->id)->where('type', 'shipment.shipped')->exists());
        $this->assertFalse(Notification::query()->where('user_id', $admin->id)->where('type', 'shipment.shipped')->exists());
    }

    #[Test]
    public function tc_sj_28_cetak_sj_dan_bukti_terima_memuat_driver_po_dan_gr_klien(): void
    {
        $sj = $this->sjBerangkat(poKlien: 'PO-KL1-001');
        $staf = $this->makeUser('warehouse_staff');

        $this->actingAs($staf)->get($this->tenantUrl('print/shipment/'.$sj->id))->assertOk();
        $html = app(DocumentPrinter::class)->view(DocumentTemplateType::Shipment, $sj->refresh())->render();
        $this->assertStringContainsString('Gani', $html);
        $this->assertStringContainsString('6281200000008', $html);
        $this->assertStringContainsString('PO-KL1-001', $html);
        $this->assertStringContainsString('Penerima (tanda tangan &amp; cap)', $html);

        $klien = $this->klien();
        Livewire::actingAs($klien)->test(PortalDeliveryProof::class, ['shipment' => $sj])
            ->set('fotoSj', UploadedFile::fake()->image('sj.jpg', 300, 400))
            ->set('form.client_gr_number', 'GR-KL1-7788')
            ->call('simpanBuktiTerima')
            ->assertHasNoErrors();

        $this->actingAs($staf)->get($this->tenantUrl('print/proof-of-delivery/'.$sj->id))->assertOk();
        $html = app(DocumentPrinter::class)->view(DocumentTemplateType::ProofOfDelivery, $sj->refresh())->render();
        $this->assertStringContainsString('GR-KL1-7788', $html);
        $this->assertStringContainsString('Portal klien', $html);
    }

    #[Test]
    public function tc_sj_29_data_driver_lama_tetap_terbaca(): void
    {
        $sj = $this->sjBerangkat();
        $driverLama = $this->makeUser('driver', attributes: ['name' => 'Gani Driver']);

        // SJ sebelum A-311: hanya `driver_id`, belum diisi balik.
        $sj->forceFill(['driver_id' => $driverLama->id, 'driver_name' => null, 'driver_phone' => null])->save();
        $sj = Shipment::query()->withoutGlobalScopes()->find($sj->id);

        $this->assertSame('Gani Driver', $sj->driverName());
        $this->assertStringContainsString('Gani Driver', $sj->carrierLabel());
        $this->assertSame('Aplikasi driver (lama)', ProofChannel::DriverPwa->label());

        // User driver lama tetap bisa masuk, tetapi tidak lagi memberangkatkan / mengisi bukti terima.
        $driverLama->forgetPermissionCache();
        $this->assertTrue($driverLama->canSignIn());
        $this->assertFalse($driverLama->hasPermission('shipment.ship'));
        $this->assertFalse($driverLama->can('confirmDelivery', $sj));
    }
}

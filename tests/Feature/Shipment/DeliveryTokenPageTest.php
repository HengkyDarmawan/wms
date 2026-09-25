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
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Shared\Files\StoreUpload;
use App\Domain\Shipment\Actions\CreatePickTask;
use App\Domain\Shipment\Actions\CreateShipment;
use App\Domain\Shipment\Actions\IssueDeliveryToken;
use App\Domain\Shipment\Actions\ProcessPickTask;
use App\Domain\Shipment\Actions\ShipShipment;
use App\Domain\Shipment\Enums\ProofChannel;
use App\Domain\Shipment\Livewire\ShipmentDetail;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-SJ-05d — halaman penerima bertoken (A-41, A-231, BR-SJ-05): OTP salah
 * ditolak dan dihitung, OTP benar membuka formulir, bukti terima dari halaman
 * publik mengubah SJ (kanal `token_link`), berkas tersimpan, token habis pakai;
 * tautan kedaluwarsa/asing → 410.
 * TC-SJ-05e — driver mengunggah foto serah terima, tanda tangan kanvas, dan
 * foto kerusakan lewat layar; berkasnya dilayani controller berotorisasi.
 */
class DeliveryTokenPageTest extends TenantTestCase
{
    private Project $proyek;

    private Warehouse $gudang;

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
        $bin = Bin::create(['warehouse_id' => $this->gudang->id, 'code' => 'CKG-A-R01-L1-B01', 'bin_type' => BinType::Storage]);
        $this->item = Item::create([
            'code' => 'BAUT-M12', 'name' => 'Baut M12', 'status' => ItemStatus::Active,
            'tracking_mode' => TrackingMode::None, 'base_uom_id' => Uom::query()->where('code', 'PCS')->value('id'),
        ]);
        app(StockLedger::class)->post(new MovementRequest(item: $this->item, qtyBase: 100, toBinId: $bin->id));
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('pod');
        parent::tearDown();
    }

    private function sjDikirim(): Shipment
    {
        $pemohon = $this->makeUser('internal_requester');
        $req = app(SaveRequest::class)->handle(null, ['project_id' => $this->proyek->id, 'required_date' => now()->addDays(3)->toDateString()], [['item_id' => $this->item->id, 'qty_base' => 20]], $pemohon);
        $req->openLines()->first()->forceFill(['source_warehouse_id' => $this->gudang->id, 'fulfillment_source' => 'stock'])->save();
        $req = app(SubmitRequest::class)->handle($req->refresh(), $pemohon);

        $staf = $this->makeUser('warehouse_staff');
        $pck = app(CreatePickTask::class)->handle($req, $this->makeUser('warehouse_head'))[0];
        $pck = app(ProcessPickTask::class)->start($pck, $staf);
        foreach ($pck->lines as $baris) {
            app(ProcessPickTask::class)->recordLine($baris, (float) $baris->qty_allocated, null, null, null, $staf);
        }
        $pck = app(ProcessPickTask::class)->complete($pck->refresh(), $staf);

        $sj = app(CreateShipment::class)->handle([$pck->id], [
            'destination_type' => 'project_client', 'destination_project_id' => $this->proyek->id,
            'shipment_method' => 'own_fleet', 'vehicle_id' => Vehicle::create(['plate_no' => 'B'.random_int(1000, 9999).'TK'])->id,
            'driver_id' => $this->makeUser('driver')->id,
        ], $staf);

        return app(ShipShipment::class)->handle($sj, null, $this->makeUser('driver'));
    }

    private function ttd(): string
    {
        // PNG 1×1 sah, seperti hasil `canvas.toDataURL('image/png')`.
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    }

    #[Test]
    public function tc_sj_05d_halaman_penerima_bertoken_otp_lalu_bukti_terima(): void
    {
        $sj = $this->sjDikirim();
        $hasil = app(IssueDeliveryToken::class)->handle($sj, '08123456789', $this->makeUser('warehouse_staff'));
        $token = $hasil['token']->token;
        $url = $this->tenantUrl('terima/'.$token);
        $baris = $sj->lines()->first();

        // Tautan asing → 410; tautan sah → form OTP; form bukti belum boleh (belum OTP).
        $this->get($this->tenantUrl('terima/'.str_repeat('x', 64)))->assertStatus(410);
        $this->get($url)->assertOk()->assertSee(__('Kode OTP'))->assertDontSee('received_by_name');
        $this->post($url, ['received_by_name' => 'Curang', 'lines' => []])->assertForbidden();

        // OTP salah: ditolak & percobaan dihitung (NFR-04).
        $this->from($url)->post($this->tenantUrl('terima/'.$token.'/otp'), ['otp' => '000000'])->assertRedirect($url)->assertSessionHasErrors('otp');
        $this->assertSame(1, $hasil['token']->refresh()->attempts);

        // OTP benar membuka formulir.
        $this->post($this->tenantUrl('terima/'.$token.'/otp'), ['otp' => $hasil['otp']])->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('received_by_name')->assertSee($this->item->code);

        // Bukti terima dari halaman publik: 18 baik, 2 rusak berfoto, tanda tangan kanvas.
        $this->post($url, [
            'received_by_name' => 'Ibu Sari (site)',
            'lines' => [$baris->id => ['qty_good' => '18', 'qty_damaged' => '2', 'qty_missing' => '0']],
            'photos' => [$baris->id => UploadedFile::fake()->image('rusak.jpg', 320, 240)],
            'photo' => UploadedFile::fake()->image('serah.jpg', 320, 240),
            'signature' => $this->ttd(),
        ])->assertRedirect($url);

        $sj->refresh();
        $bukti = $sj->proof()->with('lines')->first();
        $this->assertSame('partially_delivered', $sj->status->value);
        $this->assertSame(ProofChannel::TokenLink, $bukti->channel);
        $this->assertSame('Ibu Sari (site)', $bukti->received_by_name);
        $this->assertNull($bukti->received_by_user_id);
        $upload = app(StoreUpload::class);
        $this->assertTrue($upload->exists($bukti->photo_path), 'Foto serah terima tersimpan.');
        $this->assertTrue($upload->exists($bukti->signature_path), 'Tanda tangan tersimpan.');
        $this->assertStringEndsWith('.png', (string) $bukti->signature_path);
        $this->assertTrue($upload->exists($bukti->lines->first()->damage_photo_path), 'Foto kerusakan tersimpan.');
        $this->assertNotNull($hasil['token']->refresh()->used_at, 'Token habis pakai.');

        // Setelah dipakai: halaman ringkasan, bukan formulir; POST ulang ditolak.
        $this->get($url)->assertOk()->assertSee(__('Bukti terima tersimpan'))->assertDontSee('received_by_name');
        $this->post($url, ['received_by_name' => 'Lagi', 'lines' => []])->assertStatus(410);

        // Staf melihat berkasnya lewat controller berotorisasi; klien luar cakupan tidak.
        $staf = $this->makeUser('warehouse_staff');
        $this->actingAs($staf)->get($this->tenantUrl('shipments/'.$sj->id.'/proof/ttd'))->assertOk();
        $this->actingAs($staf)->get($this->tenantUrl('shipments/'.$sj->id.'/proof/baris-'.$bukti->lines->first()->id))->assertOk();
        $this->actingAs($staf)->get($this->tenantUrl('shipments/'.$sj->id))->assertOk()->assertSee(route('shipments.proof.file', [$sj, 'ttd']));
        $this->actingAs($this->makeUser('client_user', ScopeType::Project, $this->proyek->id, ['client_id' => $this->makeClient()->id]))->get($this->tenantUrl('shipments/'.$sj->id.'/proof/foto'))->assertRedirect();
    }

    #[Test]
    public function tc_sj_05d2_tautan_kedaluwarsa_ditolak_dan_tautan_tampil_sekali_di_layar(): void
    {
        $sj = $this->sjDikirim();
        $staf = $this->makeUser('warehouse_staff');
        $staf->forgetPermissionCache();

        $layar = Livewire::actingAs($staf)->test(ShipmentDetail::class, ['shipment' => $sj])
            ->call('mintaDialog', 'tautan')
            ->set('form.phone', '0812')
            ->call('terbitkanTautan')
            ->assertSet('ruleError', '');

        $tautan = (string) $layar->get('tautanSekali');
        $this->assertStringContainsString('/terima/', $tautan);
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $layar->get('otpSekali'));

        $token = $sj->tokens()->latest('id')->first();
        $token->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->get($this->tenantUrl('terima/'.$token->token))->assertStatus(410);
        $this->post($this->tenantUrl('terima/'.$token->token.'/otp'), ['otp' => '123456'])->assertSessionHasErrors('otp');
    }

    #[Test]
    public function tc_sj_05e_driver_mengunggah_foto_dan_tanda_tangan_dari_layar(): void
    {
        $sj = $this->sjDikirim();
        $driver = $this->makeUser('driver');
        $driver->forgetPermissionCache();
        $baris = $sj->lines()->first();

        Livewire::actingAs($driver)->test(ShipmentDetail::class, ['shipment' => $sj])
            ->call('mintaDialog', 'terima')
            ->set('form.received_by_name', 'Pak Budi')
            ->set('terima.'.$baris->id.'.qty_good', '19')
            ->set('terima.'.$baris->id.'.qty_damaged', '1')
            ->set('fotoRusak.'.$baris->id, UploadedFile::fake()->image('rusak.png', 200, 200))
            ->set('foto', UploadedFile::fake()->image('serah.jpg', 200, 200))
            ->set('tandaTangan', $this->ttd())
            ->call('simpanBuktiTerima')
            ->assertSet('ruleError', '')
            ->assertHasNoErrors();

        $bukti = $sj->refresh()->proof()->with('lines')->first();
        $upload = app(StoreUpload::class);
        $this->assertSame(ProofChannel::DriverPwa, $bukti->channel);
        $this->assertTrue($upload->exists($bukti->photo_path));
        $this->assertTrue($upload->exists($bukti->signature_path));
        $this->assertTrue($upload->exists($bukti->lines->first()->damage_photo_path));
        $this->assertStringStartsWith('pod/sj-'.$sj->id.'/', (string) $bukti->photo_path);

        // Tanda tangan yang bukan gambar ditolak sebelum aksi berjalan.
        $sj2 = $this->sjDikirim();
        Livewire::actingAs($driver)->test(ShipmentDetail::class, ['shipment' => $sj2])
            ->call('mintaDialog', 'terima')
            ->set('form.received_by_name', 'Pak Budi')
            ->set('tandaTangan', 'data:image/png;base64,bukan-gambar')
            ->call('simpanBuktiTerima')
            ->assertSet('ruleCode', 'NFR-14');
        $this->assertSame('shipped', $sj2->refresh()->status->value);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Domain\Master\Models\Vehicle;
use App\Domain\Receipt\Actions\CompleteGoodsReceipt;
use App\Domain\Receipt\Enums\GoodsReceiptStatus;
use App\Domain\Shared\Models\SyncItem;
use App\Domain\Shared\Support\OfflineSync;
use App\Domain\Shipment\Actions\CreatePickTask;
use App\Domain\Shipment\Actions\CreateShipment;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Receipt\Concerns\OutboundChain;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-PWA-21 s.d. TC-PWA-25 — tugas gudang offline (32-pwa-offline, A-439):
 * penerimaan, put-away, picking, konfirmasi muat, bukti terima berfoto.
 */
class OfflineTasksTest extends TenantTestCase
{
    use OutboundChain;
    use ReceiptFixtures;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();
        app(StockLedger::class)->post(new MovementRequest(item: $this->baut, qtyBase: 50, toBinId: $this->binA->id));
    }

    /** @param  array<string, mixed>  $payload */
    private function kirim(string $jenis, array $payload, ?object $user = null, array $berkas = [], ?string $uuid = null): array
    {
        return app(OfflineSync::class)->handle($user ?? $this->makeUser('warehouse_head'), [[
            'uuid' => $uuid ?? (string) Str::uuid(), 'kind' => $jenis, 'recorded_at' => now()->toIso8601String(), 'payload' => $payload, 'berkas' => $berkas,
        ]])[0];
    }

    private function pckMenunggu(float $qty = 20): PickTask
    {
        $req = $this->reqDisetujui($this->makeProject(), $this->gudang, $this->baut, $qty);

        return app(CreatePickTask::class)->handle($req, $this->makeUser('warehouse_head'))[0];
    }

    private function sjDisiapkan(): Shipment
    {
        $req = $this->reqDisetujui($this->makeProject(), $this->gudang, $this->baut, 20);
        $bks = Warehouse::query()->where('code', 'BKS')->first() ?? $this->buatGudang('BKS', 'Gudang Bekasi');

        return app(CreateShipment::class)->handle([$this->pckSelesai($req)->id], [
            'destination_type' => 'warehouse', 'destination_warehouse_id' => $bks->id,
            'shipment_method' => 'own_fleet', 'vehicle_id' => Vehicle::create(['plate_no' => 'B'.random_int(1000, 9999).'OF'])->id,
            'driver_name' => 'Gani', 'driver_phone' => '081200000008',
        ], $this->makeUser('warehouse_staff'));
    }

    #[Test]
    public function tc_pwa_21_penerimaan_dan_putaway_diposting_sekali(): void
    {
        $grn = $this->grnDraf([['item_id' => $this->baut->id, 'qty_received' => 10]]);
        $this->assertSame('posted', $this->kirim('receipt_receive', ['receipt_id' => $grn->id])['status']);
        $this->assertSame(GoodsReceiptStatus::Received, $grn->refresh()->status);

        $tugas = app(CompleteGoodsReceipt::class)->handle($grn, $this->makeUser())->putawayTasks()->with('lines')->sole();
        $uuid = (string) Str::uuid();
        $payload = ['task_id' => $tugas->id, 'line_id' => $tugas->lines->first()->id, 'bin_code' => $this->binB->code, 'override_reason' => 'Rak saran penuh'];
        $awal = $this->saldo($this->binB, $this->baut);

        $this->assertSame('posted', $this->kirim('putaway_place', $payload, uuid: $uuid)['status']);
        $this->assertSame('posted', $this->kirim('putaway_place', $payload, uuid: $uuid)['status'], 'Kirim ulang: status lama.');
        $this->assertSame($awal + 10, $this->saldo($this->binB, $this->baut), 'Stok hanya bergerak sekali.');
    }

    #[Test]
    public function tc_pwa_22_picking_selesai_lalu_short_tanpa_alasan_ke_tinjauan_tanpa_jejak(): void
    {
        $pck = $this->pckMenunggu();
        $baris = $pck->lines->first();

        $h = $this->kirim('pick_record', ['task_id' => $pck->id, 'finish' => true, 'lines' => [['line_id' => $baris->id, 'qty_picked' => 5]]]);
        $this->assertSame('review', $h['status']);
        $this->assertStringContainsString('alasan', (string) $h['message']);
        $this->assertSame('pending', $pck->refresh()->status->value, 'Gagal di tengah: tugas kembali seperti semula.');
        $this->assertNull($baris->refresh()->scanned_at);

        $h = $this->kirim('pick_record', ['task_id' => $pck->id, 'finish' => true, 'lines' => [['line_id' => $baris->id, 'qty_picked' => (float) $baris->qty_allocated]]]);
        $this->assertSame('posted', $h['status'], (string) $h['message']);
        $this->assertSame('completed', $pck->refresh()->status->value);
    }

    #[Test]
    public function tc_pwa_23_konfirmasi_muat_dan_bukti_terima_berfoto(): void
    {
        Storage::fake('local');
        $sj = $this->sjDisiapkan();
        $h = $this->kirim('shipment_ship', ['shipment_id' => $sj->id]);
        $this->assertSame('posted', $h['status'], (string) $h['message']);
        $this->assertSame('shipped', $sj->refresh()->status->value);

        $baris = $sj->lines()->first();
        $isi = ['shipment_id' => $sj->id, 'received_by_name' => 'Pak Penerima', 'lines' => [[
            'shipment_line_id' => $baris->id, 'qty_good' => (float) $baris->qty_shipped - 1, 'qty_damaged' => 1, 'qty_missing' => 0,
        ]]];

        $tanpaFoto = $this->kirim('delivery_confirm', $isi);
        $this->assertSame('review', $tanpaFoto['status'], 'Baris rusak wajib berfoto.');

        $h = $this->kirim('delivery_confirm', $isi, berkas: ['photo' => self::PNG, 'lines' => [$baris->id => self::PNG]]);
        $this->assertSame('posted', $h['status'], (string) $h['message']);
        $this->assertNotNull($sj->refresh()->proof);

        $simpan = SyncItem::query()->where('kind', 'delivery_confirm')->where('status', 'posted')->sole();
        $this->assertStringNotContainsString('base64', json_encode($simpan->payload), 'Foto tidak masuk sync_queue.');
        Storage::disk('local')->assertExists($simpan->payload['path']['photo']);
    }

    #[Test]
    public function tc_pwa_24_tanpa_izin_tidak_diposting_dan_id_rusak_ditolak(): void
    {
        $grn = $this->grnDraf([['item_id' => $this->baut->id, 'qty_received' => 10]]);
        $h = $this->kirim('receipt_receive', ['receipt_id' => $grn->id], $this->makeUser('internal_requester'));

        $this->assertSame('review', $h['status']);
        $this->assertSame(GoodsReceiptStatus::Draft, $grn->refresh()->status);
        $this->assertSame('rejected', $this->kirim('putaway_place', ['task_id' => 999999])['status']);
    }

    #[Test]
    public function tc_pwa_25_halaman_dan_paket_data_mengikuti_izin(): void
    {
        $pck = $this->pckMenunggu();
        $grn = $this->grnDraf([['item_id' => $this->baut->id, 'qty_received' => 10]]);
        $kepala = $this->makeUser('warehouse_head');

        $this->actingAs($kepala)->get($this->tenantUrl('offline/tugas'))->assertOk()->assertSee('data-offline-tasks', false);
        $data = $this->actingAs($kepala)->getJson($this->tenantUrl('offline/tugas/data'))->assertOk()->json();
        $this->assertSame($pck->id, $data['picking'][0]['id']);
        $this->assertSame($grn->id, $data['penerimaan'][0]['id']);
        $this->assertNotEmpty($data['csrf']);

        $data = $this->actingAs($this->makeUser('internal_requester'))->getJson($this->tenantUrl('offline/tugas/data'))->assertOk()->json();
        $this->assertSame([], $data['picking']);
        $this->assertSame([], $data['penerimaan']);
    }
}

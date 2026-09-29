<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Client;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Uom;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Shared\Reports\ReportRegistry;
use App\Domain\Shipment\Actions\ConfirmDelivery;
use App\Domain\Shipment\Actions\CreatePickTask;
use App\Domain\Shipment\Actions\CreateShipment;
use App\Domain\Shipment\Actions\ProcessPickTask;
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
 * TC-RPT-13 — laporan *Rekap pengiriman per klien* ([A-329]): klien wajib,
 * dua jalur tujuan, kolom bukti terima, baris TOTAL per item, dan tanpa satu
 * pun kolom nilai uang ([D-07]).
 */
class ClientRecapReportTest extends TenantTestCase
{
    private Client $klien;

    private Project $proyek;

    private Warehouse $gudang;

    private Warehouse $site;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->klien = $this->makeClient(['name' => 'PT Klien Rekap']);
        $this->proyek = $this->makeProject(['client_id' => $this->klien->id]);

        $this->gudang = app(SaveWarehouse::class)->handle(null, [
            'code' => 'RKP',
            'name' => 'Gudang Rekap',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);

        $this->site = app(SaveWarehouse::class)->handle(null, [
            'code' => 'RKP-SITE',
            'name' => 'Gudang Site Rekap',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::SITE)->value('id'),
            'project_id' => $this->proyek->id,
        ]);

        $bin = Bin::create(['warehouse_id' => $this->gudang->id, 'code' => 'RKP-A-R01-L1-B01', 'bin_type' => BinType::Storage]);

        $this->item = Item::create([
            'code' => 'BAUT-RKP',
            'name' => 'Baut Rekap',
            'status' => ItemStatus::Active,
            'tracking_mode' => TrackingMode::None,
            'base_uom_id' => Uom::query()->where('code', 'PCS')->value('id'),
        ]);

        app(StockLedger::class)->post(new MovementRequest(item: $this->item, qtyBase: 500, toBinId: $bin->id));
    }

    #[Test]
    public function tc_rpt_13_rekap_per_klien_hanya_proyek_klien_itu_dan_tanpa_harga(): void
    {
        $laporan = app(ReportRegistry::class)->find('rekap-pengiriman-klien');

        // Klien wajib: tanpa dipilih, tidak ada baris sama sekali.
        $this->assertTrue($laporan->filters()['client_id']['required']);
        $this->assertTrue($laporan->rows([])->isEmpty(), 'Tanpa klien, laporan kosong.');

        $keProyek = $this->sjBerangkat(['destination_type' => 'project_client', 'destination_project_id' => $this->proyek->id], 50, 'PO-KL-88');
        $this->terima($keProyek, 48, 2, 0, 'GR-0012');

        $keSite = $this->sjBerangkat(['destination_type' => 'site_warehouse', 'destination_warehouse_id' => $this->site->id], 30);

        $baris = $laporan->rows(['client_id' => (string) $this->klien->id]);

        $data = $baris->reject(fn (array $b) => $b['tgl_kirim'] === 'TOTAL');
        $total = $baris->filter(fn (array $b) => $b['tgl_kirim'] === 'TOTAL');

        $this->assertCount(2, $data, 'Dua SJ: satu ke proyek, satu ke Gudang Site.');

        $keProyekBaris = $data->firstWhere('sj', $keProyek->number);
        $this->assertSame(50.0, $keProyekBaris['dikirim']);
        $this->assertSame(48.0, $keProyekBaris['baik']);
        $this->assertSame(2.0, $keProyekBaris['rusak']);
        $this->assertSame(0.0, $keProyekBaris['kurang']);
        $this->assertSame('PO-KL-88', $keProyekBaris['po_klien']);
        $this->assertSame('GR-0012', $keProyekBaris['gr_klien']);
        $this->assertSame('BAUT-RKP', $keProyekBaris['kode_item']);
        $this->assertSame('PCS', $keProyekBaris['satuan']);

        // Belum ada bukti terima → kolom hasilnya kosong, bukan nol palsu.
        $this->assertSame('—', $data->firstWhere('sj', $keSite->number)['baik']);

        // Baris TOTAL per item.
        $this->assertCount(1, $total);
        $this->assertSame(80.0, $total->first()['dikirim']);
        $this->assertSame(48.0, $total->first()['baik']);

        // D-07: tidak ada kolom nilai uang.
        $kolom = implode(' ', array_keys($laporan->columns())).' '.implode(' ', $laporan->columns());
        foreach (['harga', 'nilai', 'rp', 'total_harga', 'subtotal'] as $terlarang) {
            $this->assertStringNotContainsStringIgnoringCase($terlarang, $kolom);
        }

        // Klien lain tidak melihat apa pun dari klien ini.
        $klienLain = $this->makeClient();
        $this->assertTrue($laporan->rows(['client_id' => (string) $klienLain->id])->isEmpty());
    }

    #[Test]
    public function tc_rpt_13b_saringan_tujuan_dan_proyek_memisahkan_dua_jalur(): void
    {
        $laporan = app(ReportRegistry::class)->find('rekap-pengiriman-klien');

        $keProyek = $this->sjBerangkat(['destination_type' => 'project_client', 'destination_project_id' => $this->proyek->id], 10);
        $keSite = $this->sjBerangkat(['destination_type' => 'site_warehouse', 'destination_warehouse_id' => $this->site->id], 20);

        $hanyaProyek = $laporan->rows(['client_id' => (string) $this->klien->id, 'destination_type' => 'project_client'])
            ->reject(fn (array $b) => $b['tgl_kirim'] === 'TOTAL');

        $this->assertSame([$keProyek->number], $hanyaProyek->pluck('sj')->all());

        $hanyaSite = $laporan->rows(['client_id' => (string) $this->klien->id, 'destination_type' => 'site_warehouse'])
            ->reject(fn (array $b) => $b['tgl_kirim'] === 'TOTAL');

        $this->assertSame([$keSite->number], $hanyaSite->pluck('sj')->all());

        // Proyek milik klien lain tidak bisa dipakai untuk mengintip.
        $proyekLain = $this->makeProject();
        $this->assertTrue($laporan->rows([
            'client_id' => (string) $this->klien->id,
            'project_id' => (string) $proyekLain->id,
        ])->isEmpty());

        // Klien yang proyeknya belum punya Gudang Site: saringan Gudang Site
        // tidak boleh berubah menjadi "tanpa batas" dan membawa SJ klien lain.
        $klienPolos = $this->makeClient();
        $this->makeProject(['client_id' => $klienPolos->id]);

        $this->assertTrue($laporan->rows([
            'client_id' => (string) $klienPolos->id,
            'destination_type' => 'site_warehouse',
        ])->isEmpty(), 'Tanpa Gudang Site, saringan itu menghasilkan nol baris — bukan semua SJ.');
    }

    #[Test]
    public function tc_rpt_13c_layar_dan_ekspor_bisa_dibuka(): void
    {
        $admin = $this->makeUser('company_admin');
        $this->sjBerangkat(['destination_type' => 'project_client', 'destination_project_id' => $this->proyek->id], 5);

        $filter = ['filters' => ['client_id' => (string) $this->klien->id]];

        $this->actingAs($admin)->get($this->tenantUrl('reports/rekap-pengiriman-klien').'?'.http_build_query($filter))
            ->assertOk()->assertSee('Rekap pengiriman per klien');

        $this->actingAs($admin)->get($this->tenantUrl('reports/rekap-pengiriman-klien/export').'?'.http_build_query($filter))
            ->assertOk();

        $this->actingAs($admin)->get($this->tenantUrl('reports/rekap-pengiriman-klien/pdf').'?'.http_build_query($filter))
            ->assertOk();

        // Tanpa klien, layar mengajak memilih dulu.
        $this->actingAs($admin)->get($this->tenantUrl('reports/rekap-pengiriman-klien'))
            ->assertOk()->assertSee('Pilih Klien dulu');

        // Tanpa `shipment.view` → 403.
        $purchasing = $this->makeUser('pr_follow_up');
        $purchasing->forgetPermissionCache();
        $this->actingAs($purchasing)->get($this->tenantUrl('reports/rekap-pengiriman-klien'))->assertForbidden();
    }

    #[Test]
    public function tc_rpt_13d_sj_belum_berangkat_tidak_ikut_dan_cakupan_proyek_pembaca_dihormati(): void
    {
        $laporan = app(ReportRegistry::class)->find('rekap-pengiriman-klien');

        $berangkat = $this->sjBerangkat(['destination_type' => 'project_client', 'destination_project_id' => $this->proyek->id], 10);

        // SJ disusun tetapi belum berangkat: bukan "barang yang sudah dikirim".
        $disusun = app(CreateShipment::class)->handle([$this->pckSelesai(7, null)], [
            'destination_type' => 'project_client',
            'destination_project_id' => $this->proyek->id,
            'shipment_method' => 'self_delivered',
            'carried_by_name' => 'Pak Site',
        ], $this->makeUser('warehouse_staff'));

        $sj = $laporan->rows(['client_id' => (string) $this->klien->id])
            ->reject(fn (array $b) => $b['tgl_kirim'] === 'TOTAL')->pluck('sj')->all();

        $this->assertSame([$berangkat->number], $sj);
        $this->assertNotContains($disusun->number, $sj);

        // BR-ACC-05: pembaca bercakupan proyek lain tidak melihat SJ proyek ini.
        $proyekLain = $this->makeProject(['client_id' => $this->klien->id]);
        $this->actingAs($this->makeUser('internal_requester', ScopeType::Project, (int) $proyekLain->id));

        $this->assertTrue($laporan->rows(['client_id' => (string) $this->klien->id])->isEmpty());
        // A-396: penyaring proyek dicari ke server — daftar sama dengan dulu.
        $this->assertSame([(int) $proyekLain->id], array_map('intval', array_column($laporan->pilihanPenyaring('project_id')->semua(), 'value')));
    }

    // ---------------------------------------------------------------- bantuan

    /** @param  array<string, mixed>  $tujuan */
    private function sjBerangkat(array $tujuan, float $qty, ?string $poKlien = null): Shipment
    {
        $sj = app(CreateShipment::class)->handle([$this->pckSelesai($qty, $poKlien)], $tujuan + [
            'shipment_method' => 'self_delivered',
            'carried_by_name' => 'Pak Site',
        ], $this->makeUser('warehouse_staff'));

        return app(ShipShipment::class)->handle($sj, null, $this->makeUser('warehouse_staff'));
    }

    private function terima(Shipment $sj, float $baik, float $rusak, float $kurang, ?string $gr): void
    {
        app(ConfirmDelivery::class)->handle($sj->refresh(), [
            'received_by_name' => 'Pak Site',
            'channel' => 'internal_user',
            'client_gr_number' => $gr,
        ], [[
            'shipment_line_id' => $sj->lines()->first()->id,
            'qty_good' => $baik,
            'qty_damaged' => $rusak,
            'qty_missing' => $kurang,
            // A-64: baris rusak wajib berfoto.
            'damage_photo_path' => $rusak > 0 ? 'bukti/rusak.jpg' : null,
        ]], $this->makeUser('company_admin'));
    }

    private function pckSelesai(float $qty, ?string $poKlien): int
    {
        $pemohon = $this->makeUser('internal_requester');

        $req = app(SaveRequest::class)->handle(null, [
            'project_id' => $this->proyek->id,
            'required_date' => now()->addDays(3)->toDateString(),
            'client_po_number' => $poKlien,
        ], [['item_id' => $this->item->id, 'qty_base' => $qty]], $pemohon);

        $req->openLines()->first()->forceFill([
            'source_warehouse_id' => $this->gudang->id,
            'fulfillment_source' => 'stock',
        ])->save();

        $req = app(SubmitRequest::class)->handle($req->refresh(), $pemohon);

        $staf = $this->makeUser('warehouse_staff');
        $pck = app(CreatePickTask::class)->handle($req, $this->makeUser('warehouse_head'))[0];
        $pck = app(ProcessPickTask::class)->start($pck, $staf);

        foreach ($pck->lines as $baris) {
            app(ProcessPickTask::class)->recordLine($baris, (float) $baris->qty_allocated, null, null, null, $staf);
        }

        return (int) app(ProcessPickTask::class)->complete($pck->refresh(), $staf)->id;
    }
}

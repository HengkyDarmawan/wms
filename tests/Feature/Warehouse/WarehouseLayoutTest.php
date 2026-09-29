<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Domain\Master\Models\Lot;
use App\Domain\Receipt\Support\PutawaySuggester;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Template\Support\LabelPayload;
use App\Domain\Warehouse\Actions\MarkBinsOccupied;
use App\Domain\Warehouse\Actions\SaveBin;
use App\Domain\Warehouse\Actions\SaveLocation;
use App\Domain\Warehouse\Actions\SaveWarehouseLayout;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Livewire\BinList;
use App\Domain\Warehouse\Livewire\WarehouseLayout;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Rack;
use App\Domain\Warehouse\Models\Zone;
use App\Domain\Warehouse\Support\WarehouseLayoutData;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-WH-21 s.d. TC-WH-25 — denah gudang 2D (A-254), rak area & bin ikut
 * terpakai untuk barang besar (A-255), tanggal masuk & FIFO (A-256);
 * TC-WH-27 — zona, rak, level, dan bin ditambah langsung dari denah (A-271);
 * TC-WH-29 — rak digambar berisi petak bin per level, label level di luar (A-281).
 */
class WarehouseLayoutTest extends TenantTestCase
{
    use ReceiptFixtures;

    private Zone $zona;

    private Rack $rak;

    /** @var array<int, Bin> */
    private array $bin = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();

        $lokasi = app(SaveLocation::class);
        $this->zona = $lokasi->saveZone($this->gudang, null, ['code' => 'D', 'name' => 'Denah']);
        $this->rak = $lokasi->saveRack($this->zona, null, ['code' => 'R01']);
        $level = $lokasi->saveLevel($this->rak, null, ['code' => 'L1']);

        foreach (['B01', 'B02', 'B03'] as $kode) {
            $this->bin[] = app(SaveBin::class)->handle($this->gudang, null, ['rack_level_id' => $level->id, 'code' => $kode]);
        }
    }

    private function masuk(Bin $bin, $item, float $qty, array $x = []): void
    {
        app(StockLedger::class)->post(new MovementRequest(item: $item, qtyBase: $qty, toBinId: $bin->id, lotId: $x['lot_id'] ?? null, occurredAt: $x['at'] ?? null));
    }

    private function gagal(callable $aksi, string $aturan): void
    {
        try {
            $aksi();
            $this->fail('Seharusnya ditolak '.$aturan.'.');
        } catch (WarehouseRuleException|LedgerException $e) {
            $this->assertSame($aturan, $e->rule, $e->getMessage());
        }
    }

    #[Test]
    public function tc_wh_21_rak_area_satu_bin_untuk_alat_berat(): void
    {
        $this->zona->forceFill(['length_m' => 12, 'width_m' => 6])->save();

        $area = app(SaveWarehouseLayout::class)->areaRack($this->zona, ['code' => 'AB1', 'name' => 'Parkir excavator', 'seluruh_zona' => '1']);
        $rak = $area->rackLevel->rack;

        $this->assertSame('CKG-D-AB1-L1-AREA', $area->code);
        $this->assertTrue($rak->is_area);
        $this->assertSame(12.0, (float) $rak->length_m, 'Seluruh zona: ukuran = zona.');
        $this->assertSame('block', $area->capacity_mode);
        $this->assertSame(1.0, (float) $area->capacity_qty);

        $this->masuk($area, $this->baut, 1);
        // Isi kedua (item lain) ditolak: kapasitas dihitung dari total isi bin.
        $this->gagal(fn () => $this->masuk($area, $this->kabel, 1), 'BR-WH-06');
        $this->assertNotSame((int) $area->id, (int) app(PutawaySuggester::class)->suggest($this->kabel, $this->gudang, 1)?->id);
    }

    #[Test]
    public function tc_wh_22_bin_ikut_terpakai_dan_lepas_otomatis(): void
    {
        [$utama, $b2, $b3] = $this->bin;
        $aksi = app(MarkBinsOccupied::class);

        $this->gagal(fn () => $aksi->handle($utama, [$b2->id], 'genset besar'), 'BR-WH-06'); // bin utama masih kosong
        $this->masuk($utama, $this->baut, 5);
        $this->masuk($b3, $this->kabel, 1);

        $this->gagal(fn () => $aksi->handle($utama, [$b2->id], ''), 'BR-GEN-11');
        $this->gagal(fn () => $aksi->handle($utama, [$b3->id], 'besar'), 'BR-WH-06'); // tetangga berisi

        $this->assertSame(1, $aksi->handle($utama, [$b2->id], 'Barang besar memakan 2 bin'));
        $this->assertSame((int) $utama->id, (int) $b2->refresh()->occupied_by_bin_id);

        $saran = app(PutawaySuggester::class)->storageBins($this->gudang)->pluck('id');
        $this->assertNotContains((int) $b2->id, $saran->all(), 'Bin ikut terpakai tidak disarankan.');

        // Barang keluar sampai bin utama kosong → penanda lepas otomatis.
        app(StockLedger::class)->post(new MovementRequest(item: $this->baut, qtyBase: 5, fromBinId: $utama->id));
        $this->assertNull($b2->refresh()->occupied_by_bin_id);
    }

    #[Test]
    public function tc_wh_23_ukuran_posisi_opsional_dan_geser_grid(): void
    {
        $data = app(WarehouseLayoutData::class)->build($this->gudang);
        $rak = collect($data['zones'])->firstWhere('code', 'D')['racks'][0];
        $this->assertTrue($rak['otomatis'], 'Tanpa posisi: ditata otomatis.');
        $this->assertSame(0.5, $rak['x']);

        $layout = app(SaveWarehouseLayout::class);
        $this->gagal(fn () => $layout->zone($this->zona, ['length_m' => '-3']), 'BR-GEN-11');
        $layout->zone($this->zona, ['length_m' => '10', 'width_m' => '5']);
        $layout->rack($this->rak, ['length_m' => '3', 'width_m' => '1', 'orientation' => 'h']);

        $rak = $layout->moveRack($this->rak->refresh(), 2.26, 1.74);
        $this->assertSame([2.5, 1.5], [(float) $rak->pos_x, (float) $rak->pos_y], 'Snap 0,5 m.');
        $rak = $layout->moveRack($rak, 50, 50);
        $this->assertSame([7.0, 4.0], [(float) $rak->pos_x, (float) $rak->pos_y], 'Tetap di dalam zona 10 × 5 m.');

        $this->assertFalse(collect(app(WarehouseLayoutData::class)->build($this->gudang)['zones'])->firstWhere('code', 'D')['racks'][0]['otomatis']);
    }

    #[Test]
    public function tc_wh_24_isi_bin_tanggal_masuk_tertua_dan_cari(): void
    {
        [$b1, $b2] = $this->bin;
        $this->masuk($b1, $this->baut, 10, ['at' => now()->subDays(40)]);
        $this->masuk($b2, $this->baut, 3, ['at' => now()->subDays(2)]);

        $data = app(WarehouseLayoutData::class)->build($this->gudang, 'baut-m12');
        $rak = collect($data['zones'])->firstWhere('code', 'D')['racks'][0];
        $bins = collect($rak['levels'][0]['bins'])->keyBy('code');

        $this->assertTrue($bins[$b1->code]['isi'][0]['tertua'], 'Masuk paling lama → ambil dulu (FIFO).');
        $this->assertFalse($bins[$b2->code]['isi'][0]['tertua']);
        $this->assertSame(40, $bins[$b1->code]['isi'][0]['umur']);
        $this->assertTrue($rak['cocok']);
        $this->assertCount(2, $data['hasil']);
        $this->assertSame('terisi', $rak['status']);

        // Label lot mencantumkan tanggal masuk (A-256).
        $lot = Lot::create(['item_id' => $this->baut->id, 'lot_no' => 'LOT-FIFO', 'received_at' => '2026-09-01']);
        $this->assertStringContainsString('Masuk 01/09/2026', LabelPayload::lot($lot)['detail']);
    }

    #[Test]
    public function tc_wh_25_layar_denah_dan_daftar_bin(): void
    {
        [$b1] = $this->bin;
        $this->masuk($b1, $this->baut, 4);

        $kepala = $this->makeUser('warehouse_head');
        $this->assertTrue($kepala->hasPermission('bin.manage'));

        $this->actingAs($kepala)->get($this->tenantUrl('warehouses/'.$this->gudang->id))->assertOk()->assertSee(route('warehouses.layout', $this->gudang));
        $this->get($this->tenantUrl('warehouses/'.$this->gudang->id.'/layout'))->assertOk()
            ->assertSee(__('Denah gudang'))->assertSee('R01')->assertSee(__('Atur denah'));

        // A-353: klik rak = isi rak saja (tanpa render ulang); simpan = satu kiriman.
        $layar = Livewire::test(WarehouseLayout::class, ['warehouse' => $this->gudang]);
        $isi = data_get($layar->call('isiRak', $this->rak->id)->effects, 'returns.0');
        $petak = collect($isi['levels'])->flatMap(fn ($l) => $l['bins'])->keyBy('code');
        $this->assertSame('BAUT-M12', $petak[$b1->code]['isi'][0]['item_code']);
        $this->assertTrue($petak[$b1->code]['isi'][0]['tertua']);

        $hasil = data_get($layar->call('simpanPerubahan', [
            ['op' => 'geser', 'jenis' => 'rak', 'id' => $this->rak->id, 'x' => 1.2, 'y' => 0.9],
            ['op' => 'area_baru', 'tmp' => 'b1', 'zona' => $this->zona->id, 'data' => ['code' => 'AX']],
        ])->effects, 'returns.0');
        $this->assertTrue($hasil['ok'], json_encode($hasil));
        $this->assertSame([1.0, 1.0], [(float) $this->rak->refresh()->pos_x, (float) $this->rak->pos_y]);
        $this->assertTrue(Rack::query()->where('code', 'AX')->sole()->is_area);

        // Tanpa bin.manage: boleh melihat, tidak boleh mengatur.
        $staf = $this->makeUser('warehouse_staff');
        $this->assertFalse($staf->hasPermission('bin.manage'));
        $this->actingAs($staf)->get($this->tenantUrl('warehouses/'.$this->gudang->id.'/layout'))->assertOk()->assertDontSee(__('Atur denah'));
        Livewire::test(WarehouseLayout::class, ['warehouse' => $this->gudang])
            ->call('simpanPerubahan', [['op' => 'geser', 'jenis' => 'rak', 'id' => $this->rak->id, 'x' => 3, 'y' => 3]])->assertForbidden();

        // Daftar bin: kolom zona · rak · level, saring rak, ubah kapasitas.
        $this->actingAs($kepala);
        Livewire::test(BinList::class)
            ->set('rackFilter', 'r01')->assertSee($b1->code)->assertSee('D · R01 · L1')
            ->call('mintaUbah', $b1->id)
            ->set('formBin.capacity_qty', '20')->set('formBin.capacity_mode', 'block')
            ->call('simpanBin')->assertHasNoErrors();
        $this->assertSame(20.0, (float) $b1->refresh()->capacity_qty);
        $this->assertSame('block', $b1->capacity_mode);
    }

    #[Test]
    public function tc_wh_27_tambah_zona_rak_level_bin_dari_denah(): void
    {
        $layout = app(SaveWarehouseLayout::class);

        // Aksi: rak + 3 level + 2 bin per level dalam satu transaksi.
        $rak = $layout->newRack($this->zona, ['code' => 'r05', 'name' => 'Rak besi', 'levels' => '3', 'bins_per_level' => '2', 'capacity_qty' => '40']);
        $this->assertSame('R05', $rak->code);
        $this->assertSame('Rak besi', $rak->name);
        $this->assertSame(['L1', 'L2', 'L3'], $rak->levels()->orderBy('code')->pluck('code')->all());
        $bins = Bin::query()->whereIn('rack_level_id', $rak->levels()->pluck('id'))->orderBy('code')->get();
        $this->assertSame(['CKG-D-R05-L1-B01', 'CKG-D-R05-L1-B02', 'CKG-D-R05-L2-B01', 'CKG-D-R05-L2-B02', 'CKG-D-R05-L3-B01', 'CKG-D-R05-L3-B02'], $bins->pluck('code')->all());
        $this->assertSame(40.0, (float) $bins->first()->capacity_qty);

        // Level berikutnya otomatis L4; kode rak ganda & jumlah di luar batas ditolak tanpa sisa.
        $this->assertSame('L4', $layout->newLevel($rak->refresh(), ['bins' => '1'])->code);
        $this->gagal(fn () => $layout->newRack($this->zona, ['code' => 'R05']), 'BR-WH-01');
        $this->gagal(fn () => $layout->newRack($this->zona, ['code' => 'R06', 'levels' => '0']), 'BR-GEN-11');
        $this->gagal(fn () => $layout->newRack($this->zona, ['code' => 'R07', 'levels' => '2', 'bins_per_level' => '51']), 'BR-GEN-11');
        $this->assertFalse(Rack::query()->whereIn('code', ['R06', 'R07'])->exists());

        // Rak area tidak boleh ditambah level (A-255).
        $area = app(SaveWarehouseLayout::class)->areaRack($this->zona, ['code' => 'AR']);
        $this->gagal(fn () => $layout->newLevel($area->rackLevel->rack, []), 'BR-WH-06');

        // Layar (A-353): Kepala Gudang membangun zona baru → rak → tingkat → bin di browser,
        // lalu satu kali simpan; benda baru dirujuk dengan id sementara.
        $this->actingAs($this->makeUser('warehouse_head'));
        $c = Livewire::test(WarehouseLayout::class, ['warehouse' => $this->gudang]);
        $hasil = data_get($c->call('simpanPerubahan', [
            ['op' => 'zona_baru', 'tmp' => 'b1', 'data' => ['code' => 'e', 'name' => 'Zona besi']],
            ['op' => 'rak_baru', 'tmp' => 'b2', 'zona' => 'b1', 'data' => ['code' => 'R01', 'levels' => '2', 'bins_per_level' => '3']],
            ['op' => 'level_baru', 'tmp' => 'b3', 'rak' => 'b2', 'data' => ['bins' => '1']],
            ['op' => 'bin_baru', 'level' => 'b2#L1', 'jumlah' => '2'],
            ['op' => 'bin_baru', 'level' => 'b3', 'jumlah' => '1'],
            ['op' => 'geser', 'jenis' => 'rak', 'id' => 'b2', 'x' => 1, 'y' => 1],
            ['op' => 'zona', 'id' => 'b1', 'data' => ['name' => 'Zona besi & pipa']],
        ])->effects, 'returns.0');
        $this->assertTrue($hasil['ok'], json_encode($hasil));
        $zonaE = Zone::query()->where('warehouse_id', $this->gudang->id)->where('code', 'E')->sole();
        $this->assertSame('Zona besi & pipa', $zonaE->name);
        $rakE = Rack::query()->where('zone_id', $zonaE->id)->sole();
        $kode = Bin::query()->whereIn('rack_level_id', $rakE->levels()->pluck('id'))->orderBy('code')->pluck('code')->all();
        $this->assertContains('CKG-E-R01-L2-B03', $kode);
        $this->assertContains('CKG-E-R01-L1-B05', $kode);
        $this->assertContains('CKG-E-R01-L3-B02', $kode);
        $this->assertSame([1.0, 1.0], [(float) $rakE->pos_x, (float) $rakE->pos_y]);

        // Galat per objek; satu gagal = tidak ada yang tersimpan.
        $gagal = data_get($c->call('simpanPerubahan', [
            ['op' => 'zona_baru', 'tmp' => 'b1', 'data' => ['code' => 'G', 'name' => 'Zona G']],
            ['op' => 'zona', 'id' => $zonaE->id, 'data' => ['name' => '']],
            ['op' => 'zona_baru', 'tmp' => 'b2', 'data' => ['code' => 'E', 'name' => 'Ganda']],
            ['op' => 'bin_baru', 'level' => $rakE->levels()->where('code', 'L1')->value('id'), 'jumlah' => '0'],
        ])->effects, 'returns.0');
        $this->assertFalse($gagal['ok']);
        $this->assertSame([1, 2, 3], array_column($gagal['galat'], 'i'));
        $this->assertSame('zona:'.$zonaE->id, $gagal['galat'][0]['objek']);
        $this->assertFalse(Zone::query()->where('code', 'G')->exists(), 'Zona G ikut dibatalkan.');
        $this->assertSame('Zona besi & pipa', $zonaE->refresh()->name);

        // Staf tanpa bin.manage tidak bisa menambah.
        $this->actingAs($this->makeUser('warehouse_staff'));
        Livewire::test(WarehouseLayout::class, ['warehouse' => $this->gudang])
            ->call('simpanPerubahan', [['op' => 'zona_baru', 'tmp' => 'b1', 'data' => ['code' => 'F', 'name' => 'X']]])->assertForbidden();
        $this->assertFalse(Zone::query()->where('code', 'F')->exists());
    }

    #[Test]
    public function tc_wh_29_rak_berisi_petak_bin_dan_tidak_bertumpuk(): void
    {
        $layout = app(SaveWarehouseLayout::class);
        $layout->newRack($this->zona, ['code' => 'R02', 'levels' => '4', 'bins_per_level' => '6']);
        $layout->newRack($this->zona, ['code' => 'R03', 'levels' => '2', 'bins_per_level' => '2']);
        $this->masuk($this->bin[0], $this->baut, 5);

        $racks = collect(collect(app(WarehouseLayoutData::class)->build($this->gudang)['zones'])->firstWhere('code', 'D')['racks'])->keyBy('code');

        // Ukuran gambar membesar agar 6 petak × 4 level terbaca; ukuran fisik tetap.
        $r02 = $racks['R02'];
        $this->assertSame(6, $r02['kolom']);
        $this->assertEqualsWithDelta(6 * WarehouseLayoutData::SEL_LEBAR + 2 * WarehouseLayoutData::BINGKAI, $r02['w'], 0.0001);
        $this->assertEqualsWithDelta(4 * WarehouseLayoutData::SEL_TINGGI + 2 * WarehouseLayoutData::BINGKAI, $r02['h'], 0.0001);
        $this->assertSame(2.0, $r02['len'], 'Ukuran fisik bawaan tidak berubah.');
        $this->assertSame(['L4', 'L3', 'L2', 'L1'], array_column($r02['levels'], 'code'), 'Level teratas digambar di atas.');
        $this->assertSame(['B01', 'B02', 'B03', 'B04', 'B05', 'B06'], array_column($r02['levels'][0]['bins'], 'short'));

        // Status per petak bin (warna), bukan hanya per rak.
        $petak = collect($racks['R01']['levels'][0]['bins'])->keyBy('short');
        $this->assertSame('terisi', $petak['B01']['status']);
        $this->assertSame('kosong', $petak['B02']['status']);

        // Tata otomatis memakai ukuran gambar: rak sebaris tidak bertumpuk.
        $urut = $racks->sortBy('x')->values();
        for ($i = 1; $i < $urut->count(); $i++) {
            if ($urut[$i]['y'] === $urut[$i - 1]['y']) {
                $this->assertGreaterThanOrEqual($urut[$i - 1]['x'] + $urut[$i - 1]['w'], $urut[$i]['x'], 'Rak '.$urut[$i]['code'].' bertumpuk.');
            }
        }

        // Layar (A-353): data denah dikirim sekali sebagai JSON; SVG digambar browser.
        $this->actingAs($this->makeUser('warehouse_head'));
        $this->get($this->tenantUrl('warehouses/'.$this->gudang->id.'/layout'))->assertOk()
            ->assertSee('"code":"R02"', false)->assertSee('"short":"B06"', false)->assertSee('"pendek":"R02 · L4 · 06"', false)
            ->assertSee('data-denah-gedung', false);
    }
}

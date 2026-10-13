<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Actions\SaveItemStorageLocations;
use App\Domain\Warehouse\Actions\SaveLocation;
use App\Domain\Warehouse\Actions\SaveWarehouseLayout;
use App\Domain\Warehouse\Livewire\WarehouseLocations;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\ItemStorageLocation;
use App\Domain\Warehouse\Models\Rack;
use App\Domain\Warehouse\Models\RackLevel;
use App\Domain\Warehouse\Models\Zone;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-WH-69 s.d. TC-WH-73 — tab Zona & rak (A-410): popup tambah yang isiannya
 * bertahan, urutan zona & rak, ubah nama/kode, hapus yang belum pernah dipakai
 * atau nonaktifkan, dan popup Lihat bin (gabung, pisah, hapus bin).
 */
class ZonaRakTabTest extends TenantTestCase
{
    use ReceiptFixtures;

    private Zone $zona;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();
        $this->zona = app(SaveLocation::class)->saveZone($this->gudang, null, ['code' => 'D', 'name' => 'Zona D']);
    }

    private function kepala()
    {
        $u = $this->makeUser('warehouse_head', ScopeType::Warehouse, $this->gudang->id);
        $u->forgetPermissionCache();

        return $u;
    }

    private function layar()
    {
        return Livewire::actingAs($this->kepala())->test(WarehouseLocations::class, ['warehouse' => $this->gudang]);
    }

    private function rak(string $kode, int $tingkat = 2, int $bin = 2): Rack
    {
        return app(SaveWarehouseLayout::class)->newRack($this->zona, ['code' => $kode, 'levels' => (string) $tingkat, 'bins_per_level' => (string) $bin]);
    }

    private function bin(string $kode): Bin
    {
        return Bin::query()->withoutGlobalScopes()->where('code', $kode)->firstOrFail();
    }

    #[Test]
    public function tc_wh_69_popup_tambah_dan_isian_bertahan_saat_ditutup(): void
    {
        $layar = $this->layar()
            ->call('bukaZona')->assertSet('popup', 'zona')->assertSeeHtml('data-popup="zona"')
            ->set('zonaForm.code', 'e')->set('zonaForm.name', 'Zona E')
            // Ditutup tanpa simpan → isian tetap ada saat dibuka lagi.
            ->call('tutup')->assertSet('popup', '')
            ->call('bukaZona')->assertSet('zonaForm.code', 'e')
            ->call('simpanZona')->assertSet('popup', '')->assertSet('zonaForm.code', '');
        $e = Zone::query()->where('warehouse_id', $this->gudang->id)->where('code', 'E')->firstOrFail();

        // Rak lengkap dengan tingkat & bin sekaligus, arah ke belakang.
        $layar->call('bukaRak', $e->id)
            ->set('rakForm.code', 'R01')->set('rakForm.orientation', 'v')->set('rakForm.levels', '3')->set('rakForm.bins_per_level', '4')
            ->assertSee('total 12 bin')
            ->call('simpanRak')->assertSet('popup', '');
        $rak = Rack::query()->where('zone_id', $e->id)->firstOrFail();
        $this->assertSame('v', $rak->orientation);
        $this->assertSame(12, Bin::query()->withoutGlobalScopes()->whereIn('rack_level_id', $rak->levels()->pluck('id'))->count());

        // Tingkat baru (kode otomatis L4) dan bin tambahan di L1 (nomor melanjutkan).
        $layar->call('bukaTingkat', $rak->id)->set('tingkatForm.bins', '2')->call('simpanTingkat');
        $this->assertTrue($rak->levels()->where('code', 'L4')->exists());
        $l1 = $rak->levels()->where('code', 'L1')->firstOrFail();
        $layar->call('mintaBuatBin', $l1->id)->assertSeeHtml('id="gen-kategori"')->set('generator.jumlah', 2)->call('buatBinMassal');
        $this->assertTrue(Bin::query()->withoutGlobalScopes()->where('code', 'CKG-E-R01-L1-B06')->exists());

        // Galat ditampilkan di popup; popup tetap terbuka.
        $layar->call('bukaZona')->set('zonaForm.code', 'E')->set('zonaForm.name', 'Kembar')->call('simpanZona')
            ->assertSet('popup', 'zona')->assertHasErrors('zonaForm.code');

        // Staf gudang hanya melihat.
        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->gudang->id);
        Livewire::actingAs($staf)->test(WarehouseLocations::class, ['warehouse' => $this->gudang])
            ->assertDontSeeHtml('data-tambah-zona')->call('bukaZona')->assertForbidden();
    }

    #[Test]
    public function tc_wh_70_urutan_zona_rak_manual_dan_tingkat_l1_paling_bawah(): void
    {
        $z2 = app(SaveLocation::class)->saveZone($this->gudang, null, ['code' => 'A', 'name' => 'Zona A']);
        $this->assertGreaterThan((int) $this->zona->refresh()->sort_order, (int) $z2->sort_order, 'Zona baru di urutan akhir walau kodenya A.');

        $this->layar()->call('geser', 'zona', $z2->id, -1);
        $this->assertSame(['A', 'D'], Zone::query()->where('warehouse_id', $this->gudang->id)->orderBy('sort_order')->pluck('code')->all());

        $r1 = $this->rak('R01');
        $r2 = $this->rak('R02');
        $this->layar()->call('geser', 'rak', $r2->id, -1)->call('geser', 'rak', $r2->id, -1);
        $this->assertSame(['R02', 'R01'], Rack::query()->where('zone_id', $this->zona->id)->orderBy('sort_order')->pluck('code')->all(), 'Paling atas tetap paling atas.');

        // Tingkat dibuat acak (L2 dulu, lalu L10, L1) → tampil L10, L2, L1 (atas ke bawah).
        $r3 = app(SaveLocation::class)->saveRack($this->zona, null, ['code' => 'R03']);
        foreach (['L2', 'L10', 'L1'] as $k) {
            app(SaveLocation::class)->saveLevel($r3, null, ['code' => $k]);
        }
        $html = $this->layar()->html();
        $r3html = substr($html, (int) strpos($html, 'data-rak="R03"'));
        $this->assertTrue(strpos($r3html, 'data-tingkat="L10"') < strpos($r3html, 'data-tingkat="L2"')
            && strpos($r3html, 'data-tingkat="L2"') < strpos($r3html, 'data-tingkat="L1"'));
        $this->assertTrue(strpos($html, 'data-rak="R02"') < strpos($html, 'data-rak="R01"'));
        $this->assertNotNull($r1);
    }

    #[Test]
    public function tc_wh_71_ubah_nama_kode_dan_kode_bin_ikut_berubah(): void
    {
        $rak = $this->rak('R01');

        $this->layar()->call('bukaUbah', 'rak', $rak->id)->assertSet('ubahForm.code', 'R01')
            ->set('ubahForm.code', 'r9')->set('ubahForm.name', 'Rak besi')->call('simpanUbah')
            ->assertSet('popup', '')->assertSet('pesanKode', fn ($p) => str_contains($p, '4 kode bin'));
        $this->assertSame(['R9', 'Rak besi'], [$rak->refresh()->code, $rak->name]);
        $this->assertTrue(Bin::query()->withoutGlobalScopes()->where('code', 'CKG-D-R9-L2-B02')->exists());

        $this->layar()->call('bukaUbah', 'zona', $this->zona->id)->set('ubahForm.code', 'K')->set('ubahForm.name', 'Zona K')->call('simpanUbah');
        $this->assertTrue(Bin::query()->withoutGlobalScopes()->where('code', 'CKG-K-R9-L1-B01')->exists());

        // Bin pernah dipakai → kode terkunci, nama tetap bisa diubah.
        app(StockLedger::class)->post(new MovementRequest(item: $this->baut, qtyBase: 5, toBinId: $this->bin('CKG-K-R9-L1-B01')->id));
        $l1 = $rak->levels()->where('code', 'L1')->firstOrFail();
        $this->layar()->call('bukaUbah', 'tingkat', $l1->id)->set('ubahForm.code', 'L7')->call('simpanUbah')
            ->assertHasErrors('ubahForm.code')->assertSet('popup', 'ubah');
        $this->layar()->call('bukaUbah', 'rak', $rak->id)->set('ubahForm.name', 'Rak besi beton')->call('simpanUbah')->assertHasNoErrors();
        $this->assertSame(['R9', 'Rak besi beton'], [$rak->refresh()->code, $rak->name]);
    }

    #[Test]
    public function tc_wh_72_hapus_yang_belum_dipakai_atau_nonaktifkan(): void
    {
        $rak = $this->rak('R01');
        $kosong = $this->rak('R02');
        app(SaveItemStorageLocations::class)->replace($this->semen, $this->gudang, [['tempat' => 'rak:'.$kosong->id]], $this->kepala());

        // Rak belum pernah dipakai → terhapus beserta tingkat, bin, dan tempat simpannya.
        $this->layar()->assertSeeHtml('data-rak="R02"')->call('hapus', 'rak', $kosong->id)->assertSet('ruleError', '');
        $this->assertNull(Rack::query()->find($kosong->id));
        $this->assertFalse(Bin::query()->withoutGlobalScopes()->where('code', 'like', 'CKG-D-R02-%')->exists());
        $this->assertSame(0, ItemStorageLocation::query()->where('item_id', $this->semen->id)->count());

        // Tingkat L2 belum dipakai → hapus; L1 dipakai → ditolak, lalu dinonaktifkan (bin kosong).
        $bin = $this->bin('CKG-D-R01-L1-B01');
        app(StockLedger::class)->post(new MovementRequest(item: $this->baut, qtyBase: 3, toBinId: $bin->id));
        app(StockLedger::class)->post(new MovementRequest(item: $this->baut, qtyBase: 3, fromBinId: $bin->id));
        $l1 = $rak->levels()->where('code', 'L1')->firstOrFail();
        $l2 = $rak->levels()->where('code', 'L2')->firstOrFail();

        $this->layar()->call('hapus', 'tingkat', $l2->id);
        $this->assertNull(RackLevel::query()->find($l2->id));
        $this->layar()->call('hapus', 'tingkat', $l1->id)->assertSet('ruleError', fn ($e) => str_contains($e, 'pernah dipakai'));
        $this->layar()->call('bukaNonaktif', 'tingkat', $l1->id)->assertSee(__('Pilih alasan…'))
            ->set('nonaktifAlasan', 'OTHER')->call('simpanNonaktif');
        $this->assertFalse($l1->refresh()->is_active);
        $this->assertNotNull(RackLevel::query()->find($l1->id), 'Yang pernah dipakai tidak dihapus (P-03).');

        // Zona berisi rak yang pernah dipakai → hapus ditolak.
        $this->layar()->call('hapus', 'zona', $this->zona->id)->assertSet('ruleError', fn ($e) => str_contains($e, 'pernah dipakai'));
        $this->assertNotNull(Zone::query()->find($this->zona->id));
    }

    #[Test]
    public function tc_wh_73_lihat_bin_gabung_pisah_dan_hapus(): void
    {
        $rak = $this->rak('R01', 2, 3);
        $b1 = $this->bin('CKG-D-R01-L1-B01');
        $b2 = $this->bin('CKG-D-R01-L1-B02');
        $b3 = $this->bin('CKG-D-R01-L1-B03');

        $layar = $this->layar()->call('lihatBin', $rak->id)->assertSet('popup', 'lihat')->assertSeeHtml('data-tampak-depan')
            ->call('pilihBin', $b1->id)->call('pilihBin', $b2->id)->assertSeeHtml('data-gabung')
            ->call('gabung')->assertHasErrors('gabungForm.alasan')
            ->set('gabungForm.alasan', 'Pipa panjang')->call('gabung')->assertHasNoErrors()->assertSet('binPilih', []);
        $this->assertSame((int) $b1->id, (int) $b2->refresh()->occupied_by_bin_id, 'Ke samping: bin utama = nomor terkecil.');

        // Satu bin gabungan terpilih → Pisah.
        $layar->call('pilihBin', $b2->id)->assertSeeHtml('data-pisah')->call('pisah', $b2->id);
        $this->assertNull($b2->refresh()->occupied_by_bin_id);

        // Tidak bersebelahan & beda nomor → ditolak dengan penjelasan.
        $layar->call('pilihBin', $b1->id)->call('pilihBin', $this->bin('CKG-D-R01-L2-B03')->id)
            ->set('gabungForm.alasan', 'x')->call('gabung')->assertSet('ruleError', fn ($e) => str_contains($e, 'bersebelahan'));

        // Hapus satu bin yang belum dipakai.
        $layar->set('binPilih', [])->call('pilihBin', $b3->id)->assertSeeHtml('data-hapus-bin-daftar')->call('hapusBin', $b3->id);
        $this->assertNull(Bin::query()->withoutGlobalScopes()->find($b3->id));

        // Bin rak lain tidak bisa dipilih lewat popup ini.
        $lain = $this->rak('R02');
        $layar->set('binPilih', [])->call('pilihBin', Bin::query()->withoutGlobalScopes()->whereIn('rack_level_id', $lain->levels()->pluck('id'))->value('id'))
            ->assertSet('binPilih', []);
    }
}

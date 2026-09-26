<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Warehouse\Actions\ImportWarehouseStructure;
use App\Domain\Warehouse\Actions\SaveBin;
use App\Domain\Warehouse\Actions\SaveLocation;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Rack;
use App\Domain\Warehouse\Models\RackLevel;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use App\Domain\Warehouse\Models\Zone;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\TenantTestCase;

/**
 * TC-WH-26 s.d. TC-WH-26d — impor struktur gudang dari Excel (A-258): zona,
 * rak, level dibuat otomatis dari baris bin; semua atau tidak (A-207); kode
 * yang sudah ada ditolak; izin `bin.manage` dan cakupan gudang (BR-ACC-05).
 */
class WarehouseStructureImportTest extends TenantTestCase
{
    private Warehouse $gudang;

    protected function setUp(): void
    {
        parent::setUp();

        $tipe = WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id');
        $this->gudang = app(SaveWarehouse::class)->handle(null, ['code' => 'CKG', 'name' => 'Gudang Utama Cakung', 'warehouse_type_id' => $tipe]);

        // Hierarki yang sudah ada: zona A, rak R01, level L1, bin B01.
        $lokasi = app(SaveLocation::class);
        $zona = $lokasi->saveZone($this->gudang, null, ['code' => 'A', 'name' => 'Material besi']);
        $rak = $lokasi->saveRack($zona, null, ['code' => 'R01']);
        $level = $lokasi->saveLevel($rak, null, ['code' => 'L1']);
        app(SaveBin::class)->handle($this->gudang, null, ['bin_type' => 'storage', 'rack_level_id' => $level->id, 'code' => 'B01', 'capacity_qty' => 50]);
    }

    /** @param  list<list<mixed>>  $baris */
    private function berkas(array $baris): UploadedFile
    {
        $buku = new Spreadsheet;
        $buku->getActiveSheet()->fromArray(array_merge([array_values(ImportWarehouseStructure::COLUMNS)], $baris));
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($buku))->save($path);

        return new UploadedFile($path, 'struktur.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /** @return array{zona: int, rak: int, level: int, bin: int} */
    private function hitung(): array
    {
        return [
            'zona' => Zone::query()->count(),
            'rak' => Rack::query()->count(),
            'level' => RackLevel::query()->count(),
            'bin' => Bin::withoutGlobalScopes()->count(),
        ];
    }

    #[Test]
    public function tc_wh_26_impor_membuat_hierarki_zona_rak_level_bin(): void
    {
        $admin = $this->makeUser('company_admin');

        $this->actingAs($admin)->get($this->tenantUrl('imports'))->assertOk()
            ->assertSee('impor-bins', false)->assertSee(__('Struktur gudang (zona, rak, level, bin)'));
        $this->actingAs($admin)->get($this->tenantUrl('imports/bins/template'))->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $sebelum = $this->hitung();

        $this->actingAs($admin)->post($this->tenantUrl('imports/bins'), ['file' => $this->berkas([
            ['ckg', 'c', 'Zona Pipa', 'R01', 'L1', 'B01', 'storage', 100],
            ['CKG', 'C', '', 'R01', 'L1', 'B02', 'Penyimpanan', '12,5'],
            ['CKG', 'C', '', 'R01', 'L2', 'b-01', '', ''],
            ['CKG', 'C', '', 'R02', 'L1', 'B01', '', ''],
            ['CKG', 'A', '', 'R01', 'L3', 'B01', '', ''],
        ])])->assertSessionHasNoErrors()->assertRedirect(route('bins.index'))->assertSessionHas('status');

        // 1 zona (C), 2 rak (C-R01, C-R02), 4 level (C-R01-L1, C-R01-L2, C-R02-L1, A-R01-L3), 5 bin.
        $this->assertSame(
            ['zona' => $sebelum['zona'] + 1, 'rak' => $sebelum['rak'] + 2, 'level' => $sebelum['level'] + 4, 'bin' => $sebelum['bin'] + 5],
            $this->hitung(),
        );

        $zonaC = Zone::query()->where('warehouse_id', $this->gudang->id)->where('code', 'C')->sole();
        $this->assertSame('Zona Pipa', $zonaC->name, 'Nama dari kolom nama zona baris pertama.');
        $this->assertSame(1, Zone::query()->where('warehouse_id', $this->gudang->id)->where('code', 'A')->count(), 'Zona A dipakai ulang.');

        $bin = Bin::withoutGlobalScopes()->where('code', 'CKG-C-R01-L1-B02')->sole();
        $this->assertSame(BinType::Storage, $bin->bin_type);
        $this->assertEquals(12.5, (float) $bin->capacity_qty);
        $this->assertEquals(100, (float) Bin::withoutGlobalScopes()->where('code', 'CKG-C-R01-L1-B01')->value('capacity_qty'));

        foreach (['CKG-C-R01-L2-B01', 'CKG-C-R02-L1-B01', 'CKG-A-R01-L3-B01'] as $kode) {
            $this->assertTrue(Bin::withoutGlobalScopes()->where('code', $kode)->where('warehouse_id', $this->gudang->id)->exists(), $kode);
        }

        $this->assertTrue(Activity::query()->where('log_name', 'warehouse')->where('description', 'Impor struktur gudang dari Excel: 5 bin')->exists());
        $this->assertTrue(Activity::query()->where('log_name', 'warehouse')->where('description', 'Zona dibuat')->exists(), 'Lokasi baru lewat SaveLocation (audit sama dengan layar).');
    }

    #[Test]
    public function tc_wh_26b_satu_baris_salah_tidak_ada_yang_tersimpan(): void
    {
        $admin = $this->makeUser('company_admin');
        $sebelum = $this->hitung();

        $this->actingAs($admin)->post($this->tenantUrl('imports/bins'), ['file' => $this->berkas([
            ['CKG', 'D', 'Zona baru', 'R01', 'L1', 'B01', '', ''],   // benar
            ['XYZ', 'D', '', 'R01', 'L1', 'B02', '', ''],            // gudang asing
            ['CKG', 'D', '', 'R01', 'L1', 'B03', 'receiving', ''],   // jenis bawaan (BR-WH-02)
            ['CKG', 'D', '', 'R01', 'L1', 'B04', '', '-5'],          // kapasitas negatif
            ['CKG', 'D', '', 'R01', '', 'B05', '', ''],              // level kosong
            ['CKG', 'D', '', 'R01', 'L1', 'B06', 'gudang', ''],      // jenis di luar Katalog
            ['CKG', 'D', '', 'R01', 'L1', 'B07', 'on_site', ''],     // On-site per proyek
        ])])
            ->assertSessionHasErrors('file')
            ->assertSessionHas('rowErrors', fn (string $t) => ! str_contains($t, 'Baris 2:')
                && str_contains($t, 'Baris 3:') && str_contains($t, 'XYZ')
                && str_contains($t, 'Baris 4:') && str_contains($t, 'Baris 5:') && str_contains($t, 'Baris 6:')
                && str_contains($t, 'Baris 7:') && str_contains($t, 'Baris 8:'));

        $this->assertSame($sebelum, $this->hitung(), 'Zona D dari baris benar ikut dibatalkan (A-207).');
        $this->assertFalse(Zone::query()->where('code', 'D')->exists());
    }

    #[Test]
    public function tc_wh_26c_kode_yang_sudah_ada_atau_ganda_ditolak(): void
    {
        $admin = $this->makeUser('company_admin');
        $sebelum = $this->hitung();

        // Bin CKG-A-R01-L1-B01 sudah ada: ditolak, tidak ditimpa.
        $this->actingAs($admin)->post($this->tenantUrl('imports/bins'), ['file' => $this->berkas([
            ['CKG', 'A', '', 'R01', 'L1', 'B01', '', 999],
            ['CKG', 'A', '', 'R01', 'L1', 'B02', '', ''],
        ])])
            ->assertSessionHasErrors('file')
            ->assertSessionHas('rowErrors', fn (string $t) => str_contains($t, 'Baris 2:') && str_contains($t, 'CKG-A-R01-L1-B01') && str_contains($t, 'sudah dipakai'));

        $this->assertEquals(50, (float) Bin::withoutGlobalScopes()->where('code', 'CKG-A-R01-L1-B01')->value('capacity_qty'), 'Bin lama tidak berubah.');

        // Kode sama dua kali di berkas.
        $this->actingAs($admin)->post($this->tenantUrl('imports/bins'), ['file' => $this->berkas([
            ['CKG', 'E', '', 'R01', 'L1', 'B01', '', ''],
            ['CKG', 'e', '', 'r01', 'l1', 'b01', '', ''],
        ])])
            ->assertSessionHas('rowErrors', fn (string $t) => str_contains($t, 'Baris 3:') && str_contains($t, 'ganda di berkas') && str_contains($t, 'baris 2'));

        $this->assertSame($sebelum, $this->hitung());
    }

    #[Test]
    public function tc_wh_26d_tanpa_izin_403_dan_cakupan_gudang_dijaga(): void
    {
        // Staf gudang hanya punya bin.view.
        $staf = $this->makeUser('warehouse_staff');
        $this->actingAs($staf)->get($this->tenantUrl('imports/bins/template'))->assertForbidden();
        $this->actingAs($staf)->post($this->tenantUrl('imports/bins'), ['file' => $this->berkas([['CKG', 'F', '', 'R01', 'L1', 'B01', '', '']])])
            ->assertForbidden();
        $this->assertFalse(Zone::query()->where('code', 'F')->exists());

        // Kepala Gudang (bin.manage) melihat kartu struktur gudang, tanpa kartu item.
        $kepala = $this->makeUser('warehouse_head', ScopeType::Warehouse, $this->gudang->id);
        $this->actingAs($kepala)->get($this->tenantUrl('imports'))->assertOk()
            ->assertSee('impor-bins', false)->assertDontSee('impor-items', false);

        // Kepala Gudang gudang lain: CKG di luar cakupan.
        $tipe = WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id');
        $bks = app(SaveWarehouse::class)->handle(null, ['code' => 'BKS', 'name' => 'Gudang Bekasi', 'warehouse_type_id' => $tipe]);
        $kepalaBks = $this->makeUser('warehouse_head', ScopeType::Warehouse, $bks->id);

        $this->actingAs($kepalaBks)->post($this->tenantUrl('imports/bins'), ['file' => $this->berkas([
            ['BKS', 'A', '', 'R01', 'L1', 'B01', '', ''],
            ['CKG', 'F', '', 'R01', 'L1', 'B01', '', ''],
        ])])
            ->assertSessionHas('rowErrors', fn (string $t) => str_contains($t, 'Baris 3:') && str_contains($t, 'di luar cakupan') && ! str_contains($t, 'Baris 2:'));

        $this->assertFalse(Zone::query()->where('warehouse_id', $bks->id)->exists(), 'Baris BKS yang benar ikut dibatalkan.');
    }
}

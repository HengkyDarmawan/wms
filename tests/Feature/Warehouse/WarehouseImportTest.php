<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Domain\Warehouse\Actions\ImportWarehouses;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\TenantTestCase;

/**
 * TC-WH-28 s.d. TC-WH-28c — impor gudang dari Excel (A-272, sisa O-12):
 * gudang lewat SaveWarehouse (bin bawaan otomatis, BR-WH-02/04/05), semua
 * atau tidak (A-207), kode yang ada ditolak, izin `warehouse.create`.
 */
class WarehouseImportTest extends TenantTestCase
{
    /** @param  list<list<mixed>>  $baris */
    private function berkas(array $baris): UploadedFile
    {
        $buku = new Spreadsheet;
        $buku->getActiveSheet()->fromArray(array_merge([array_values(ImportWarehouses::COLUMNS)], $baris));
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($buku))->save($path);

        return new UploadedFile($path, 'gudang.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function utama(string $kode): Warehouse
    {
        $tipe = WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id');

        return app(SaveWarehouse::class)->handle(null, ['code' => $kode, 'name' => 'Gudang '.$kode, 'warehouse_type_id' => $tipe]);
    }

    #[Test]
    public function tc_wh_28_impor_gudang_dengan_bin_bawaan_induk_dan_site(): void
    {
        $this->utama('CKG');
        $proyek = $this->makeProject(['code' => 'PRJ-201']);
        $admin = $this->makeUser('company_admin');
        $kepala = $this->makeUser('warehouse_head', attributes: ['email' => 'kepala.sby@test.wms.test']);

        $this->actingAs($admin)->get($this->tenantUrl('imports'))->assertOk()->assertSee('impor-warehouses', false)->assertSee(__('Gudang'));
        $this->actingAs($admin)->get($this->tenantUrl('imports/warehouses/template'))->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->actingAs($admin)->get($this->tenantUrl('warehouses'))->assertOk()->assertSee('#impor-warehouses', false);

        $this->actingAs($admin)->post($this->tenantUrl('imports/warehouses'), ['file' => $this->berkas([
            ['sby', 'Gudang Surabaya', 'BRANCH', 'CKG', '', 'KEPALA.SBY@test.wms.test', 'Jl. Rungkut 5'],
            ['SBY2', 'Gudang Surabaya Timur', 'gudang cabang', 'SBY', '', '', ''],   // tipe dari nama, induk dari baris sebelumnya
            ['PRJ201', 'Gudang Site PRJ-201', 'SITE', '', 'prj-201', '', ''],
        ])])->assertSessionHasNoErrors()->assertRedirect(route('warehouses.index'))->assertSessionHas('status');

        $sby = Warehouse::withoutGlobalScopes()->where('code', 'SBY')->sole();
        $this->assertSame('Gudang Surabaya', $sby->name);
        $this->assertSame((int) $kepala->id, (int) $sby->head_user_id);
        $this->assertSame((int) Warehouse::withoutGlobalScopes()->where('code', 'CKG')->value('id'), (int) $sby->parent_id);
        $this->assertSame((int) $sby->id, (int) Warehouse::withoutGlobalScopes()->where('code', 'SBY2')->value('parent_id'));
        $this->assertSame((int) $proyek->id, (int) Warehouse::withoutGlobalScopes()->where('code', 'PRJ201')->value('project_id'));

        // BR-WH-02: bin bawaan + in_transit ikut dibuat, sama dengan form.
        $this->assertTrue(Bin::withoutGlobalScopes()->where('warehouse_id', $sby->id)->where('bin_type', BinType::Receiving->value)->exists());
        $this->assertTrue(Bin::withoutGlobalScopes()->where('warehouse_id', $sby->id)->where('bin_type', BinType::InTransit->value)->exists());
        $this->assertTrue(Activity::query()->where('log_name', 'warehouse')->where('description', 'Impor gudang dari Excel: 3 gudang')->exists());
    }

    #[Test]
    public function tc_wh_28b_satu_baris_salah_tidak_ada_yang_tersimpan(): void
    {
        $this->utama('CKG');
        $this->makeProject(['code' => 'PRJ-301']);
        $admin = $this->makeUser('company_admin');
        $jumlah = Warehouse::withoutGlobalScopes()->count();

        $this->actingAs($admin)->post($this->tenantUrl('imports/warehouses'), ['file' => $this->berkas([
            ['JKT', 'Gudang Jakarta', 'MAIN', '', '', '', ''],        // benar
            ['CKG', 'Kode sudah ada', 'MAIN', '', '', '', ''],         // BR-MST-01
            ['STE', 'Site tanpa proyek', 'SITE', '', '', '', ''],      // BR-WH-04
            ['BRC', 'Cabang dengan proyek', 'BRANCH', '', 'PRJ-301', '', ''], // BR-WH-04
            ['XX1', 'Tipe asing', 'GUDANGKU', '', '', '', ''],
            ['XX2', 'Induk asing', 'MAIN', 'ZZZ', '', '', ''],
            ['XX3', 'Kepala asing', 'MAIN', '', '', 'bukan@user.test', ''],
            ['', 'Tanpa kode', 'MAIN', '', '', '', ''],
        ])])
            ->assertSessionHasErrors('file')
            ->assertSessionHas('rowErrors', fn (string $t) => ! str_contains($t, 'Baris 2:')
                && str_contains($t, 'Baris 3:') && str_contains($t, 'sudah dipakai')
                && str_contains($t, 'Baris 4:') && str_contains($t, 'Baris 5:') && str_contains($t, 'Baris 6:')
                && str_contains($t, 'Baris 7:') && str_contains($t, 'Baris 8:') && str_contains($t, 'Baris 9:'));

        $this->assertSame($jumlah, Warehouse::withoutGlobalScopes()->count(), 'Gudang JKT dari baris benar ikut dibatalkan (A-207).');
        $this->assertFalse(Bin::withoutGlobalScopes()->where('code', 'like', 'JKT-%')->exists(), 'Bin bawaannya juga batal.');
    }

    #[Test]
    public function tc_wh_28c_tanpa_warehouse_create_403(): void
    {
        // Kepala Gudang punya bin.manage tetapi bukan warehouse.create.
        $kepala = $this->makeUser('warehouse_head');
        $this->assertFalse($kepala->hasPermission('warehouse.create'));

        $this->actingAs($kepala)->get($this->tenantUrl('imports'))->assertOk()->assertDontSee('impor-warehouses', false);
        $this->actingAs($kepala)->get($this->tenantUrl('imports/warehouses/template'))->assertForbidden();
        $this->actingAs($kepala)->post($this->tenantUrl('imports/warehouses'), ['file' => $this->berkas([['JKT', 'Gudang Jakarta', 'MAIN', '', '', '', '']])])
            ->assertForbidden();
        $this->assertFalse(Warehouse::withoutGlobalScopes()->where('code', 'JKT')->exists());
    }
}

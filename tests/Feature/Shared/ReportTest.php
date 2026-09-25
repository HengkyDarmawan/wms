<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Shared\Reports\ReportRegistry;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use Database\Seeders\Tenant\MasterDemoSeeder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-RPT-01 — laporan lintas modul dan ekspor Excel.
 *
 * Menutup 10-access §9, 11-master §9, dan 12-warehouse §9 yang sebelumnya
 * hanya berupa tabel di dokumen tanpa satu pun layar.
 */
class ReportTest extends TenantTestCase
{
    #[Test]
    public function tc_rpt_01_daftar_laporan_mengikuti_izin(): void
    {
        $registry = app(ReportRegistry::class);

        $admin = $this->makeUser('company_admin');
        $admin->forgetPermissionCache();

        $this->assertCount(25, $registry->all(), 'Dua puluh lima laporan terdaftar (sebelas laporan §9 Stock/Request/Shipment sejak A-232; (Material per proyek sejak modul Issue, Aset dipinjamkan sejak modul Aset, lima laporan inti Blueprint §6.9a sejak Pendukung F1).');
        $this->assertCount(25, $registry->availableTo($admin), 'Admin Company membuka semuanya.');

        // Driver hanya punya item.view, project.view, warehouse.view, dan stock.view.
        $driver = $this->makeUser('driver');
        $driver->forgetPermissionCache();

        $kunci = $registry->availableTo($driver)->map(fn ($r) => $r->key())->all();

        $this->assertEqualsCanonicalizing(
            ['daftar-item', 'item-sementara', 'daftar-proyek', 'daftar-gudang', 'saldo-stok', 'mutasi-periode', 'kartu-stok', 'titik-pesan-ulang', 'daftar-pengiriman', 'short-pick', 'posisi-rusak-selisih', 'kinerja-pengiriman'],
            $kunci,
        );
    }

    #[Test]
    public function tc_rpt_01b_layar_laporan_menolak_tanpa_izin(): void
    {
        $driver = $this->makeUser('driver');

        $this->actingAs($driver)->get($this->tenantUrl('reports'))->assertOk();
        $this->actingAs($driver)->get($this->tenantUrl('reports/daftar-item'))->assertOk();

        // Log masuk menuntut `user.view` yang tidak dipunyai driver.
        $this->actingAs($driver)->get($this->tenantUrl('reports/log-login'))->assertForbidden();
        $this->actingAs($driver)->get($this->tenantUrl('reports/log-login/export'))->assertForbidden();

        // Kunci laporan yang tidak dikenal = halaman tidak ada, bukan galat server.
        $this->actingAs($this->makeUser('company_admin'))->get($this->tenantUrl('reports/tidak-ada'))->assertNotFound();
        $this->actingAs($this->makeUser('company_admin'))->get($this->tenantUrl('reports/tidak-ada/export'))->assertNotFound();
    }

    #[Test]
    public function tc_rpt_01c_isi_laporan_pengguna_menampilkan_cakupan_bernama(): void
    {
        $tipe = WarehouseType::query()->where('code', WarehouseType::MAIN)->firstOrFail();

        $gudang = app(SaveWarehouse::class)->handle(null, [
            'code' => 'CKG',
            'name' => 'Gudang Utama Cakung',
            'warehouse_type_id' => $tipe->id,
        ]);

        $kepala = $this->makeUser('warehouse_head', ScopeType::Warehouse, $gudang->id, ['name' => 'Andi Kepala']);

        $baris = app(ReportRegistry::class)->find('user-role-cakupan')->rows([]);

        $andi = $baris->firstWhere('user', 'Andi Kepala');

        $this->assertNotNull($andi);
        $this->assertSame('Kepala Gudang', $andi['role']);
        $this->assertSame('Gudang: Gudang Utama Cakung', $andi['cakupan'], 'Cakupan ditampilkan sebagai nama, bukan id.');
    }

    #[Test]
    public function tc_rpt_01d_laporan_item_menghormati_penyaring(): void
    {
        (new MasterDemoSeeder)->run();

        $laporan = app(ReportRegistry::class)->find('daftar-item');

        $semua = $laporan->rows([]);
        $perPotong = $laporan->rows(['tracking_mode' => 'piece']);

        $this->assertSame(4, $semua->count());
        $this->assertSame(1, $perPotong->count());
        $this->assertSame('PIPA-PVC-4', $perPotong->first()['kode']);
    }

    #[Test]
    public function tc_rpt_01e_ekspor_excel_menghasilkan_berkas(): void
    {
        (new MasterDemoSeeder)->run();

        $admin = $this->makeUser('company_admin');

        $response = $this->actingAs($admin)->get($this->tenantUrl('reports/daftar-item/export'));

        $response->assertOk();

        $this->assertStringContainsString(
            'spreadsheetml',
            (string) $response->headers->get('content-type'),
            'Ekspor harus berupa berkas Excel.',
        );

        $this->assertStringContainsString('daftar-item', (string) $response->headers->get('content-disposition'));
    }

    #[Test]
    public function tc_rpt_01f_laporan_gudang_mengikuti_cakupan(): void
    {
        $tipe = WarehouseType::query()->where('code', WarehouseType::MAIN)->firstOrFail();

        $ckg = app(SaveWarehouse::class)->handle(null, [
            'code' => 'CKG', 'name' => 'Gudang Utama Cakung', 'warehouse_type_id' => $tipe->id,
        ]);

        app(SaveWarehouse::class)->handle(null, [
            'code' => 'BKS', 'name' => 'Gudang Cabang Bekasi', 'warehouse_type_id' => $tipe->id,
        ]);

        $kepala = $this->makeUser('warehouse_head', ScopeType::Warehouse, $ckg->id);
        $kepala->forgetPermissionCache();

        $this->actingAs($kepala);

        $baris = app(ReportRegistry::class)->find('daftar-gudang')->rows([]);

        $this->assertSame(['CKG'], $baris->pluck('kode')->all(), 'BR-ACC-05 berlaku juga di laporan.');
    }
}

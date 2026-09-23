<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use Database\Seeders\Tenant\WarehouseDemoSeeder;
use Database\Seeders\Tenant\MasterDemoSeeder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-WH-17, TC-WH-18, TC-WH-20 — cakupan (BR-ACC-05), izin layar (BR-GEN-09),
 * dan seeder demo (docs/00-akun-uji.md §2).
 */
class WarehouseScreenTest extends TenantTestCase
{
    private function buatGudang(string $code, string $name): Warehouse
    {
        return app(SaveWarehouse::class)->handle(null, [
            'code' => $code,
            'name' => $name,
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);
    }

    #[Test]
    public function tc_wh_17_daftar_gudang_dibatasi_cakupan_penugasan(): void
    {
        $ckg = $this->buatGudang('CKG', 'Gudang Utama Cakung');
        $bks = $this->buatGudang('BKS', 'Gudang Cabang Bekasi');

        // Kepala gudang yang cakupannya hanya CKG.
        $kepala = $this->makeUser('warehouse_head', ScopeType::Warehouse, $ckg->id);

        $this->actingAs($kepala);
        $kepala->forgetPermissionCache();

        $terlihat = Warehouse::query()->pluck('code')->all();

        $this->assertSame(['CKG'], $terlihat, 'BR-ACC-05: hanya gudang dalam cakupan yang tampil.');

        // Bin ikut cakupan gudangnya.
        $binTerlihat = Bin::query()->pluck('warehouse_id')->unique()->values()->all();

        $this->assertSame([$ckg->id], $binTerlihat);

        // Admin dengan cakupan `all` melihat keduanya.
        $admin = $this->makeUser('company_admin');
        $this->actingAs($admin);
        $admin->forgetPermissionCache();

        $this->assertEqualsCanonicalizing(['CKG', 'BKS'], Warehouse::query()->pluck('code')->all());
        $this->assertGreaterThan(0, $bks->refresh()->bins()->count());
    }

    #[Test]
    public function tc_wh_17b_policy_menolak_gudang_di_luar_cakupan(): void
    {
        $ckg = $this->buatGudang('CKG', 'Gudang Utama Cakung');
        $bks = $this->buatGudang('BKS', 'Gudang Cabang Bekasi');

        $kepala = $this->makeUser('warehouse_head', ScopeType::Warehouse, $ckg->id);
        $kepala->forgetPermissionCache();

        $this->assertTrue($kepala->can('view', $ckg));
        $this->assertFalse($kepala->can('view', $bks), 'Gudang di luar cakupan tidak boleh dibuka.');

        // Route model binding memakai global scope yang sama, jadi gudang di luar
        // cakupan tidak ditemukan sama sekali. 404 lebih baik daripada 403 di
        // sini: keberadaan gudang milik cakupan lain pun tidak terkonfirmasi.
        $this->actingAs($kepala)
            ->get($this->tenantUrl('warehouses/'.$bks->id))
            ->assertNotFound();
    }

    #[Test]
    public function tc_wh_18_izin_layar_gudang(): void
    {
        $gudang = $this->buatGudang('CKG', 'Gudang Utama Cakung');

        // Staf gudang boleh melihat, tidak boleh mengelola tipe gudang.
        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $gudang->id);
        $staf->forgetPermissionCache();

        $this->assertTrue($staf->hasPermission('warehouse.view'));
        $this->assertTrue($staf->hasPermission('bin.view'));
        $this->assertFalse($staf->hasPermission('bin.manage'));
        $this->assertFalse($staf->hasPermission('warehouse.create'));

        $this->actingAs($staf)->get($this->tenantUrl('warehouses'))->assertOk();
        $this->actingAs($staf)->get($this->tenantUrl('bins'))->assertOk();
        $this->actingAs($staf)->get($this->tenantUrl('warehouse-types'))->assertForbidden();

        // Kepala gudang boleh mengelola bin, tetapi tidak membuat gudang baru.
        $kepala = $this->makeUser('warehouse_head', ScopeType::Warehouse, $gudang->id);
        $kepala->forgetPermissionCache();

        $this->assertTrue($kepala->hasPermission('bin.manage'));
        $this->assertTrue($kepala->hasPermission('warehouse.update'));
        $this->assertFalse($kepala->hasPermission('warehouse.create'));

        $this->actingAs($kepala)->get($this->tenantUrl('warehouse-types'))->assertOk();
    }

    #[Test]
    public function tc_wh_18b_admin_membuka_seluruh_layar_gudang(): void
    {
        $gudang = $this->buatGudang('CKG', 'Gudang Utama Cakung');
        $admin = $this->makeUser('company_admin');

        foreach (['warehouses', 'warehouses/'.$gudang->id, 'bins', 'warehouse-types'] as $path) {
            $this->actingAs($admin)
                ->get($this->tenantUrl($path))
                ->assertOk();
        }
    }

    #[Test]
    public function tc_wh_18c_klien_tidak_punya_izin_gudang(): void
    {
        $proyek = $this->makeProject();
        $klien = $this->makeUser('client_user', ScopeType::Project, $proyek->id, [
            'client_id' => $proyek->client_id,
        ]);

        $klien->forgetPermissionCache();

        $this->assertFalse($klien->hasPermission('warehouse.view'));
        $this->assertFalse($klien->hasPermission('bin.view'));
    }

    #[Test]
    public function tc_wh_20_seeder_demo_membuat_gudang_sesuai_akun_uji(): void
    {
        (new MasterDemoSeeder)->run();
        (new WarehouseDemoSeeder)->run();

        $gudang = Warehouse::withoutGlobalScopes()->get()->keyBy('code');

        $this->assertEqualsCanonicalizing(['CKG', 'BKS', 'KRW1', 'KRW2'], $gudang->keys()->all());

        // §2: BKS anak dari CKG.
        $this->assertSame($gudang['CKG']->id, $gudang['BKS']->parent_id);

        // §2: KRW1 dan KRW2 adalah Gudang Site milik PRJ-001.
        foreach (['KRW1', 'KRW2'] as $kode) {
            $this->assertTrue($gudang[$kode]->isSite(), $kode.' harus bertipe Gudang Site.');
            $this->assertSame('PRJ-001', $gudang[$kode]->project?->code);
        }

        // BR-WH-02: setiap gudang punya enam bin bawaan.
        foreach ($gudang as $kode => $w) {
            $bawaan = $w->bins()->whereIn('bin_type', [
                BinType::Receiving->value, BinType::Staging->value, BinType::Quarantine->value,
                BinType::Return->value, BinType::Waste->value, BinType::InTransit->value,
            ])->count();

            $this->assertSame(6, $bawaan, 'Gudang '.$kode.' harus punya enam bin bawaan.');
        }

        // BR-WH-03: satu bin On-site untuk PRJ-001, bukan dua meski ada dua Gudang Site.
        $this->assertSame(
            1,
            Bin::withoutGlobalScopes()->where('bin_type', BinType::OnSite->value)->count(),
            'Satu proyek hanya punya satu bin On-site meski punya beberapa Gudang Site (A-40).',
        );

        // Aman dijalankan dua kali.
        $jumlahBin = Bin::withoutGlobalScopes()->count();
        (new WarehouseDemoSeeder)->run();

        $this->assertSame($jumlahBin, Bin::withoutGlobalScopes()->count());
        $this->assertSame(4, Warehouse::withoutGlobalScopes()->count());
    }
}

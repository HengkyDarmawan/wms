<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Domain\Warehouse\Actions\DeactivateWarehouse;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Actions\SaveWarehouseType;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-WH-01 s.d. TC-WH-05, TC-WH-16, TC-WH-19 — gudang, tipe, dan hierarki
 * (BR-WH-02, BR-WH-04, BR-WH-05, BR-WH-07, BR-MST-01).
 */
class WarehouseTest extends TenantTestCase
{
    private function tipe(string $code): WarehouseType
    {
        return WarehouseType::query()->where('code', $code)->firstOrFail();
    }

    /** @param  array<string, mixed>  $override */
    private function buatGudang(array $override = []): Warehouse
    {
        return app(SaveWarehouse::class)->handle(null, array_merge([
            'code' => 'G'.strtoupper(substr(uniqid(), -5)),
            'name' => 'Gudang Uji',
            'warehouse_type_id' => $this->tipe(WarehouseType::MAIN)->id,
        ], $override));
    }

    #[Test]
    public function tc_wh_01_kode_gudang_disimpan_huruf_besar_dan_unik(): void
    {
        $gudang = app(SaveWarehouse::class)->handle(null, [
            'code' => 'ckg',
            'name' => 'Gudang Utama Cakung',
            'warehouse_type_id' => $this->tipe(WarehouseType::MAIN)->id,
        ]);

        $this->assertSame('CKG', $gudang->code);
        $this->assertTrue($gudang->is_active);

        try {
            $this->buatGudang(['code' => 'CKG']);
            $this->fail('Kode gudang kembar seharusnya ditolak.');
        } catch (WarehouseRuleException $e) {
            $this->assertSame('BR-MST-01', $e->rule);
        }

        // BR-MST-01: kode gudang dipakai nomor dokumen, jadi terkunci.
        try {
            app(SaveWarehouse::class)->handle($gudang, [
                'code' => 'LAIN',
                'name' => 'Gudang Utama Cakung',
                'warehouse_type_id' => $this->tipe(WarehouseType::MAIN)->id,
            ]);
            $this->fail('Kode gudang seharusnya terkunci.');
        } catch (\App\Domain\Master\Exceptions\MasterRuleException $e) {
            $this->assertSame('BR-MST-01', $e->rule);
        }
    }

    #[Test]
    public function tc_wh_02_gudang_baru_langsung_punya_bin_bawaan(): void
    {
        $gudang = $this->buatGudang();

        $jenis = $gudang->bins()->pluck('bin_type')->map(fn ($t) => $t->value)->all();

        $this->assertEqualsCanonicalizing(
            ['receiving', 'staging', 'quarantine', 'return', 'waste', 'in_transit'],
            $jenis,
            'BR-WH-02: lima bin bawaan dan satu bin Dalam Perjalanan.',
        );

        $transit = $gudang->inTransitBin();

        $this->assertNotNull($transit);
        $this->assertTrue($transit->is_virtual, 'BR-STK-13: bin Dalam Perjalanan adalah lokasi virtual.');
        $this->assertSame($gudang->code.'-TRANSIT', $transit->code);

        // Aman dijalankan ulang: menyimpan gudang lagi tidak menggandakan bin.
        app(SaveWarehouse::class)->handle($gudang, [
            'name' => 'Nama Baru',
            'warehouse_type_id' => $gudang->warehouse_type_id,
        ]);

        $this->assertSame(6, $gudang->refresh()->bins()->count());
    }

    #[Test]
    public function tc_wh_03_gudang_site_wajib_punya_proyek(): void
    {
        try {
            $this->buatGudang(['warehouse_type_id' => $this->tipe(WarehouseType::SITE)->id]);
            $this->fail('Gudang Site tanpa proyek seharusnya ditolak.');
        } catch (WarehouseRuleException $e) {
            $this->assertSame('BR-WH-04', $e->rule);
            $this->assertArrayHasKey('project_id', $e->fieldErrors);
        }

        $proyek = $this->makeProject();

        $site = $this->buatGudang([
            'warehouse_type_id' => $this->tipe(WarehouseType::SITE)->id,
            'project_id' => $proyek->id,
        ]);

        $this->assertTrue($site->isSite());
        $this->assertSame($proyek->id, $site->project_id);
    }

    #[Test]
    public function tc_wh_04_gudang_bukan_site_tidak_boleh_punya_proyek(): void
    {
        $proyek = $this->makeProject();

        try {
            $this->buatGudang(['project_id' => $proyek->id]);
            $this->fail('Gudang non-site berproyek seharusnya ditolak.');
        } catch (WarehouseRuleException $e) {
            $this->assertSame('BR-WH-04', $e->rule);
        }
    }

    #[Test]
    public function tc_wh_05_hierarki_gudang_tidak_boleh_melingkar(): void
    {
        $induk = $this->buatGudang(['code' => 'INDUK', 'name' => 'Gudang Induk']);
        $anak = $this->buatGudang(['code' => 'ANAK', 'name' => 'Gudang Anak', 'parent_id' => $induk->id]);

        $this->assertSame($induk->id, $anak->parent_id);

        // Induk dijadikan anak dari anaknya sendiri.
        try {
            app(SaveWarehouse::class)->handle($induk, [
                'name' => 'Gudang Induk',
                'warehouse_type_id' => $induk->warehouse_type_id,
                'parent_id' => $anak->id,
            ]);
            $this->fail('Hierarki melingkar seharusnya ditolak.');
        } catch (WarehouseRuleException $e) {
            $this->assertSame('BR-WH-05', $e->rule);
        }

        // Gudang menjadi induk dirinya sendiri.
        try {
            app(SaveWarehouse::class)->handle($anak, [
                'name' => 'Gudang Anak',
                'warehouse_type_id' => $anak->warehouse_type_id,
                'parent_id' => $anak->id,
            ]);
            $this->fail('Gudang tidak boleh menjadi induk dirinya sendiri.');
        } catch (WarehouseRuleException $e) {
            $this->assertSame('BR-WH-05', $e->rule);
        }

        $this->assertSame('Gudang Induk > Gudang Anak', $anak->refresh()->path());
    }

    #[Test]
    public function tc_wh_16_gudang_dengan_bin_penyimpanan_aktif_tidak_bisa_dinonaktifkan(): void
    {
        $gudang = $this->buatGudang();

        // Bin penyimpanan dibuat langsung supaya uji ini tidak bergantung layar.
        Bin::create([
            'warehouse_id' => $gudang->id,
            'code' => $gudang->code.'-A-R01-L1-B01',
            'bin_type' => BinType::Storage,
            'bin_status' => BinStatus::Active,
        ]);

        try {
            app(DeactivateWarehouse::class)->handle($gudang, 'NOT_NEEDED');
            $this->fail('Gudang dengan bin penyimpanan aktif seharusnya ditolak.');
        } catch (WarehouseRuleException $e) {
            $this->assertSame('BR-WH-07', $e->rule);
            $this->assertStringContainsString('bin penyimpanan aktif', $e->getMessage());
        }

        $this->assertTrue($gudang->refresh()->is_active);
    }

    #[Test]
    public function tc_wh_16b_gudang_tanpa_bin_penyimpanan_bisa_dinonaktifkan(): void
    {
        $gudang = $this->buatGudang();

        // BR-GEN-11: alasan wajib.
        try {
            app(DeactivateWarehouse::class)->handle($gudang, '');
            $this->fail('Alasan kosong seharusnya ditolak.');
        } catch (WarehouseRuleException $e) {
            $this->assertSame('BR-GEN-11', $e->rule);
        }

        $gudang = app(DeactivateWarehouse::class)->handle($gudang, 'NOT_NEEDED');

        $this->assertFalse($gudang->is_active);
        $this->assertSame(
            0,
            $gudang->bins()->where('bin_status', BinStatus::Active->value)->count(),
            'Bin gudang nonaktif ikut berhenti menerima dokumen.',
        );

        $gudang = app(DeactivateWarehouse::class)->reactivate($gudang);

        $this->assertTrue($gudang->is_active);
        $this->assertSame(6, $gudang->bins()->where('bin_status', BinStatus::Active->value)->count());
    }

    #[Test]
    public function tc_wh_16c_gudang_dengan_anak_aktif_tidak_bisa_dinonaktifkan(): void
    {
        $induk = $this->buatGudang(['code' => 'IND2', 'name' => 'Induk Dua']);
        $this->buatGudang(['code' => 'ANK2', 'name' => 'Anak Dua', 'parent_id' => $induk->id]);

        try {
            app(DeactivateWarehouse::class)->handle($induk, 'NOT_NEEDED');
            $this->fail('Gudang dengan anak aktif seharusnya ditolak.');
        } catch (WarehouseRuleException $e) {
            $this->assertSame('BR-WH-07', $e->rule);
            $this->assertStringContainsString('gudang anak', $e->getMessage());
        }
    }

    #[Test]
    public function tc_wh_19_tipe_gudang_bawaan_terkunci(): void
    {
        $site = $this->tipe(WarehouseType::SITE);

        $this->assertTrue($site->is_builtin);

        try {
            app(SaveWarehouseType::class)->deactivate($site);
            $this->fail('Tipe gudang bawaan seharusnya tidak bisa dinonaktifkan.');
        } catch (WarehouseRuleException $e) {
            $this->assertSame('BR-WH-04', $e->rule);
        }

        // Nama boleh diubah, kode tidak.
        $diubah = app(SaveWarehouseType::class)->handle($site, ['code' => 'SITE', 'name' => 'Gudang Lapangan']);

        $this->assertSame('Gudang Lapangan', $diubah->name);
        $this->assertSame('SITE', $diubah->code);

        try {
            app(SaveWarehouseType::class)->handle($site, ['code' => 'LAPANGAN', 'name' => 'Gudang Lapangan']);
            $this->fail('Kode tipe gudang seharusnya terkunci.');
        } catch (\App\Domain\Master\Exceptions\MasterRuleException $e) {
            $this->assertSame('BR-MST-01', $e->rule);
        }
    }

    #[Test]
    public function tc_wh_19b_tipe_buatan_company_ditolak_bila_masih_dipakai(): void
    {
        $tipe = app(SaveWarehouseType::class)->handle(null, ['code' => 'transit', 'name' => 'Gudang Transit']);

        $this->assertSame('TRANSIT', $tipe->code);
        $this->assertFalse($tipe->is_builtin);

        $this->buatGudang(['warehouse_type_id' => $tipe->id]);

        try {
            app(SaveWarehouseType::class)->deactivate($tipe);
            $this->fail('Tipe yang masih dipakai seharusnya ditolak.');
        } catch (WarehouseRuleException $e) {
            $this->assertSame('BR-WH-07', $e->rule);
        }
    }
}

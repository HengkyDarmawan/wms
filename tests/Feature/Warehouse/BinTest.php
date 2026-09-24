<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Domain\Master\Enums\CapacityMode;
use App\Domain\Master\Models\StorageCategory;
use App\Domain\Warehouse\Actions\ChangeBinStatus;
use App\Domain\Warehouse\Actions\EnsureSystemBins;
use App\Domain\Warehouse\Actions\GenerateBins;
use App\Domain\Warehouse\Actions\SaveBin;
use App\Domain\Warehouse\Actions\SaveLocation;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\RackLevel;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-WH-06 s.d. TC-WH-15 — bin, kode hierarkis, pembekuan, dan kapasitas
 * (BR-WH-01, BR-WH-02, BR-WH-03, BR-WH-06, BR-OPN-02, BR-STK-07).
 */
class BinTest extends TenantTestCase
{
    private Warehouse $gudang;

    private RackLevel $level;

    protected function setUp(): void
    {
        parent::setUp();

        $tipe = WarehouseType::query()->where('code', WarehouseType::MAIN)->firstOrFail();

        $this->gudang = app(SaveWarehouse::class)->handle(null, [
            'code' => 'CKG',
            'name' => 'Gudang Utama Cakung',
            'warehouse_type_id' => $tipe->id,
        ]);

        $lokasi = app(SaveLocation::class);
        $zona = $lokasi->saveZone($this->gudang, null, ['code' => 'A', 'name' => 'Material besi']);
        $rak = $lokasi->saveRack($zona, null, ['code' => 'R03']);
        $this->level = $lokasi->saveLevel($rak, null, ['code' => 'L2']);
    }

    #[Test]
    public function tc_wh_06_kode_bin_diturunkan_dari_hierarki(): void
    {
        $bin = app(SaveBin::class)->handle($this->gudang, null, [
            'bin_type' => BinType::Storage->value,
            'rack_level_id' => $this->level->id,
            'code' => 'B05',
        ]);

        $this->assertSame('CKG-A-R03-L2-B05', $bin->code, 'BR-WH-01: kode diturunkan dari hierarki.');
        $this->assertSame(BinStatus::Active, $bin->bin_status);
        $this->assertFalse($bin->is_virtual);
    }

    #[Test]
    public function tc_wh_07_kode_bin_terkunci_setelah_dibuat(): void
    {
        $bin = app(SaveBin::class)->handle($this->gudang, null, [
            'bin_type' => BinType::Storage->value,
            'rack_level_id' => $this->level->id,
            'code' => 'B05',
        ]);

        try {
            app(SaveBin::class)->handle($this->gudang, $bin, [
                'bin_type' => BinType::Storage->value,
                'rack_level_id' => $this->level->id,
                'code' => 'B99',
            ]);
            $this->fail('Kode bin seharusnya terkunci.');
        } catch (WarehouseRuleException $e) {
            $this->assertSame('BR-WH-01', $e->rule);
        }

        // Menyimpan ulang tanpa mengubah kode tetap boleh.
        $bin = app(SaveBin::class)->handle($this->gudang, $bin, [
            'bin_type' => BinType::Storage->value,
            'rack_level_id' => $this->level->id,
            'capacity_qty' => 100,
        ]);

        $this->assertSame('CKG-A-R03-L2-B05', $bin->code);
        $this->assertSame(100.0, (float) $bin->capacity_qty);
    }

    #[Test]
    public function tc_wh_08_pembuat_bin_massal_tidak_menabrak_kode(): void
    {
        $pertama = app(GenerateBins::class)->handle($this->level, 3);

        $this->assertCount(3, $pertama);
        $this->assertSame(
            ['CKG-A-R03-L2-B01', 'CKG-A-R03-L2-B02', 'CKG-A-R03-L2-B03'],
            array_map(fn (Bin $b) => $b->code, $pertama),
        );

        // Dijalankan lagi: nomor melanjutkan, tidak menimpa.
        $kedua = app(GenerateBins::class)->handle($this->level, 2);

        $this->assertSame(
            ['CKG-A-R03-L2-B04', 'CKG-A-R03-L2-B05'],
            array_map(fn (Bin $b) => $b->code, $kedua),
        );

        $this->assertSame(5, $this->level->bins()->count());

        try {
            app(GenerateBins::class)->handle($this->level, 0);
            $this->fail('Jumlah nol seharusnya ditolak.');
        } catch (WarehouseRuleException $e) {
            $this->assertSame('BR-GEN-11', $e->rule);
        }
    }

    #[Test]
    public function tc_wh_09_bin_bisa_dibekukan_dan_dicairkan(): void
    {
        $bin = app(GenerateBins::class)->handle($this->level, 1)[0];
        $aksi = app(ChangeBinStatus::class);

        // BR-GEN-11: alasan wajib.
        try {
            $aksi->freeze($bin, '');
            $this->fail('Alasan kosong seharusnya ditolak.');
        } catch (WarehouseRuleException $e) {
            $this->assertSame('BR-GEN-11', $e->rule);
        }

        // Sejak modul Count, frozen_by_count_id ber-FK ke stock_counts (A-104).
        $sesi = \App\Domain\Count\Models\StockCount::create([
            'number' => 'OPN/UJI/2609/0001', 'count_type' => 'spot_check', 'scope' => ['warehouse_ids' => [$bin->warehouse_id]],
        ]);

        $bin = $aksi->freeze($bin, 'COUNT_FIX', $sesi->id);

        $this->assertSame(BinStatus::Frozen, $bin->bin_status);
        $this->assertSame($sesi->id, (int) $bin->frozen_by_count_id);
        $this->assertFalse($bin->acceptsMovement(), 'BR-OPN-02: bin beku menolak dokumen baru.');

        // TC-WH-10
        $bin = $aksi->unfreeze($bin);

        $this->assertSame(BinStatus::Active, $bin->bin_status);
        $this->assertNull($bin->frozen_by_count_id);
        $this->assertTrue($bin->acceptsMovement());

        try {
            $aksi->unfreeze($bin);
            $this->fail('Bin yang tidak beku seharusnya ditolak.');
        } catch (WarehouseRuleException $e) {
            $this->assertSame('BR-OPN-02', $e->rule);
        }
    }

    #[Test]
    public function tc_wh_11_bin_sistem_tidak_bisa_dinonaktifkan(): void
    {
        $transit = $this->gudang->inTransitBin();

        $this->assertNotNull($transit);
        $this->assertTrue($transit->isSystemBin());

        try {
            app(ChangeBinStatus::class)->deactivate($transit, 'NOT_NEEDED');
            $this->fail('Bin virtual seharusnya tidak bisa dinonaktifkan.');
        } catch (WarehouseRuleException $e) {
            $this->assertSame('BR-WH-02', $e->rule);
        }

        // Bin penyimpanan biasa tetap bisa.
        $bin = app(GenerateBins::class)->handle($this->level, 1)[0];
        $bin = app(ChangeBinStatus::class)->deactivate($bin, 'NOT_NEEDED');

        $this->assertSame(BinStatus::Inactive, $bin->bin_status);
    }

    #[Test]
    public function tc_wh_11b_bin_bawaan_tidak_bisa_dibuat_manual(): void
    {
        try {
            app(SaveBin::class)->handle($this->gudang, null, [
                'bin_type' => BinType::Receiving->value,
                'rack_level_id' => $this->level->id,
            ]);
            $this->fail('Bin bawaan seharusnya hanya dibuat otomatis.');
        } catch (WarehouseRuleException $e) {
            $this->assertSame('BR-WH-02', $e->rule);
        }
    }

    #[Test]
    public function tc_wh_12_bin_on_site_satu_per_proyek(): void
    {
        $proyek = $this->makeProject();
        $aksi = app(EnsureSystemBins::class);

        $bin = $aksi->onSiteBin($this->gudang, $proyek);

        $this->assertSame(BinType::OnSite, $bin->bin_type);
        $this->assertSame($proyek->id, $bin->project_id);
        $this->assertTrue($bin->is_virtual);
        $this->assertStringContainsString('ONSITE', $bin->code);

        // Dipanggil lagi: bin yang sama dikembalikan, bukan bin kedua.
        $lagi = $aksi->onSiteBin($this->gudang, $proyek);

        $this->assertSame($bin->id, $lagi->id);
        $this->assertSame(
            1,
            Bin::query()->where('bin_type', BinType::OnSite->value)->where('project_id', $proyek->id)->count(),
            'BR-WH-03: satu bin On-site per proyek.',
        );
    }

    #[Test]
    public function tc_wh_13_bin_on_site_tanpa_proyek_ditolak(): void
    {
        try {
            app(SaveBin::class)->handle($this->gudang, null, ['bin_type' => BinType::OnSite->value]);
            $this->fail('Bin On-site tanpa proyek seharusnya ditolak.');
        } catch (WarehouseRuleException $e) {
            $this->assertSame('BR-WH-03', $e->rule);
        }

        // Sebaliknya: bin penyimpanan tidak boleh terikat proyek.
        $proyek = $this->makeProject();

        try {
            app(SaveBin::class)->handle($this->gudang, null, [
                'bin_type' => BinType::Storage->value,
                'rack_level_id' => $this->level->id,
                'project_id' => $proyek->id,
            ]);
            $this->fail('Bin penyimpanan berproyek seharusnya ditolak.');
        } catch (WarehouseRuleException $e) {
            $this->assertSame('BR-WH-03', $e->rule);
        }
    }

    #[Test]
    public function tc_wh_14_kapasitas_blokir_mengikuti_kategori_penyimpanan(): void
    {
        $blokir = StorageCategory::query()->where('capacity_mode', CapacityMode::Block->value)->firstOrFail();

        $bin = app(SaveBin::class)->handle($this->gudang, null, [
            'bin_type' => BinType::Storage->value,
            'rack_level_id' => $this->level->id,
            'code' => 'B10',
            'storage_category_id' => $blokir->id,
            'capacity_qty' => 50,
        ]);

        $this->assertSame(CapacityMode::Block, $bin->capacityMode());
        $this->assertTrue($bin->blocksOnOverCapacity(), 'BR-WH-06: kategori block menolak penempatan.');
        $this->assertTrue($bin->exceedsCapacity(51));
        $this->assertFalse($bin->exceedsCapacity(50));
    }

    #[Test]
    public function tc_wh_15_kapasitas_peringatan_tidak_memblokir(): void
    {
        $peringatan = StorageCategory::query()->where('capacity_mode', CapacityMode::Warn->value)->firstOrFail();

        $bin = app(SaveBin::class)->handle($this->gudang, null, [
            'bin_type' => BinType::Storage->value,
            'rack_level_id' => $this->level->id,
            'code' => 'B11',
            'storage_category_id' => $peringatan->id,
            'capacity_qty' => 10,
        ]);

        $this->assertSame(CapacityMode::Warn, $bin->capacityMode());
        $this->assertFalse($bin->blocksOnOverCapacity(), 'BR-STK-07: bawaan hanya peringatan.');
        $this->assertTrue($bin->exceedsCapacity(11), 'Tetap terdeteksi melampaui, tetapi tidak memblokir.');

        // Tanpa kategori penyimpanan pun bawaannya peringatan (A-37).
        $tanpaKategori = app(SaveBin::class)->handle($this->gudang, null, [
            'bin_type' => BinType::Storage->value,
            'rack_level_id' => $this->level->id,
            'code' => 'B12',
        ]);

        $this->assertSame(CapacityMode::Warn, $tanpaKategori->capacityMode());
    }

    #[Test]
    public function tc_wh_15b_penanda_hitung_bisa_dipasang_dan_dilepas(): void
    {
        $bin = app(GenerateBins::class)->handle($this->level, 1)[0];
        $aksi = app(ChangeBinStatus::class);

        $this->assertFalse($bin->count_flag);

        $bin = $aksi->flagForCount($bin);

        $this->assertTrue($bin->count_flag, 'A-67: bin ditandai perlu dihitung.');
        $this->assertSame(1, Bin::query()->flaggedForCount()->count());

        $bin = $aksi->flagForCount($bin, false);

        $this->assertFalse($bin->count_flag);
    }

    #[Test]
    public function tc_wh_06b_kode_zona_rak_dan_level_terkunci(): void
    {
        $lokasi = app(SaveLocation::class);
        $zona = $lokasi->saveZone($this->gudang, null, ['code' => 'b', 'name' => 'Kelistrikan']);

        $this->assertSame('B', $zona->code, 'Kode dibakukan huruf besar.');

        try {
            $lokasi->saveZone($this->gudang, $zona, ['code' => 'C', 'name' => 'Kelistrikan']);
            $this->fail('Kode zona seharusnya terkunci.');
        } catch (WarehouseRuleException $e) {
            $this->assertSame('BR-WH-01', $e->rule);
        }

        // Kode zona kembar di gudang yang sama ditolak.
        try {
            $lokasi->saveZone($this->gudang, null, ['code' => 'B', 'name' => 'Lain']);
            $this->fail('Kode zona kembar seharusnya ditolak.');
        } catch (WarehouseRuleException $e) {
            $this->assertSame('BR-WH-01', $e->rule);
        }
    }
}

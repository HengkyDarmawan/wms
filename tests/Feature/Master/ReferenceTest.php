<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Domain\Master\Actions\DeactivateItemCategory;
use App\Domain\Master\Actions\SaveItemCategory;
use App\Domain\Master\Actions\SaveReference;
use App\Domain\Master\Enums\CapacityMode;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Enums\RemovalStrategy;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\StorageCategory;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Models\Vehicle;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-MST-19 s.d. TC-MST-21 — kategori item, alasan baku, dan kendaraan
 * (BR-MST-05, BR-GEN-02, Blueprint §6.3a).
 */
class ReferenceTest extends TenantTestCase
{
    #[Test]
    public function tc_mst_19_kategori_dengan_item_aktif_tidak_bisa_dinonaktifkan(): void
    {
        $kategori = app(SaveItemCategory::class)->handle(null, [
            'code' => 'kat-uji',
            'name' => 'Kategori Uji',
        ]);

        $this->assertSame('KAT-UJI', $kategori->code);

        Item::create([
            'code' => 'ITM-KAT',
            'name' => 'Item di kategori',
            'item_category_id' => $kategori->id,
            'status' => ItemStatus::Active,
            'tracking_mode' => TrackingMode::None,
            'base_uom_id' => Uom::query()->where('code', 'PCS')->value('id'),
        ]);

        try {
            app(DeactivateItemCategory::class)->handle($kategori, 'NOT_NEEDED');
            $this->fail('Kategori dengan item aktif seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertSame('BR-MST-05', $e->rule);
            $this->assertStringContainsString('item aktif', $e->getMessage());
        }

        $this->assertTrue($kategori->refresh()->is_active);
    }

    #[Test]
    public function tc_mst_19b_kategori_mewariskan_toleransi_dan_strategi(): void
    {
        $aksi = app(SaveItemCategory::class);

        $induk = $aksi->handle(null, [
            'code' => 'MATERIAL-UJI',
            'name' => 'Material',
            'removal_strategy' => RemovalStrategy::Fifo->value,
            'tolerance_pct' => '2,5',
        ]);

        $anak = $aksi->handle(null, [
            'code' => 'PIPA-UJI',
            'name' => 'Pipa',
            'parent_id' => $induk->id,
        ]);

        $this->assertSame('Material > Pipa', $anak->path());
        $this->assertSame(2.5, $anak->effectiveTolerance()['pct'], 'Toleransi diwarisi dari induk.');

        // Kategori tidak boleh menjadi induk dirinya sendiri.
        try {
            $aksi->handle($induk, ['code' => 'MATERIAL-UJI', 'name' => 'Material', 'parent_id' => $anak->id]);
            $this->fail('Lingkaran induk-anak seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertSame('BR-MST-01', $e->rule);
        }
    }

    #[Test]
    public function tc_mst_19c_kategori_dengan_sub_kategori_aktif_tidak_bisa_dinonaktifkan(): void
    {
        $aksi = app(SaveItemCategory::class);

        $induk = $aksi->handle(null, ['code' => 'IND-UJI', 'name' => 'Induk']);
        $aksi->handle(null, ['code' => 'ANK-UJI', 'name' => 'Anak', 'parent_id' => $induk->id]);

        try {
            app(DeactivateItemCategory::class)->handle($induk, 'NOT_NEEDED');
            $this->fail('Kategori dengan sub-kategori aktif seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertSame('BR-MST-05', $e->rule);
            $this->assertStringContainsString('sub-kategori', $e->getMessage());
        }
    }

    #[Test]
    public function tc_mst_20_alasan_pembatalan_tersedia_untuk_dialog(): void
    {
        // Seeder referensi sudah mengisi alasan baku per konteks (BR-GEN-02).
        $bawaan = ReasonCode::options(ReasonContext::Cancel);

        $this->assertNotEmpty($bawaan);
        $this->assertArrayHasKey('DUPLICATE', $bawaan);

        $alasan = app(SaveReference::class)->saveReasonCode(null, [
            'context' => ReasonContext::Cancel->value,
            'code' => 'klien-batal',
            'label' => 'Dibatalkan klien',
        ]);

        $this->assertSame('KLIEN-BATAL', $alasan->code);
        $this->assertSame(ReasonContext::Cancel, $alasan->context);

        $this->assertArrayHasKey('KLIEN-BATAL', ReasonCode::options(ReasonContext::Cancel));

        // Konteks lain tidak ikut terbawa.
        $this->assertArrayNotHasKey('KLIEN-BATAL', ReasonCode::options(ReasonContext::Reject));

        // Alasan nonaktif hilang dari pilihan, tanpa dihapus (P-03).
        app(SaveReference::class)->toggle($alasan, false);

        $this->assertArrayNotHasKey('KLIEN-BATAL', ReasonCode::options(ReasonContext::Cancel));
        $this->assertDatabaseHas('reason_codes', ['code' => 'KLIEN-BATAL'], 'tenant');
    }

    #[Test]
    public function tc_mst_20b_kode_alasan_boleh_sama_di_konteks_berbeda(): void
    {
        $aksi = app(SaveReference::class);

        $aksi->saveReasonCode(null, ['context' => ReasonContext::Waste->value, 'code' => 'UJI', 'label' => 'Waste uji']);
        $aksi->saveReasonCode(null, ['context' => ReasonContext::Lost->value, 'code' => 'UJI', 'label' => 'Hilang uji']);

        $this->assertSame(2, ReasonCode::query()->where('code', 'UJI')->count());

        // Di konteks yang sama, kode kembar ditolak.
        try {
            $aksi->saveReasonCode(null, ['context' => ReasonContext::Waste->value, 'code' => 'UJI', 'label' => 'Kembar']);
            $this->fail('Kode alasan kembar di satu konteks seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertSame('BR-MST-01', $e->rule);
        }
    }

    #[Test]
    public function tc_mst_21_kendaraan_menyimpan_driver_bawaan(): void
    {
        $driver = $this->makeUser('driver');

        $kendaraan = app(SaveReference::class)->saveVehicle(null, [
            'plate_no' => 'b 9001 xx',
            'type' => 'Pick-up',
            'default_driver_id' => $driver->id,
        ]);

        $this->assertSame('B-9001-XX', $kendaraan->plate_no);
        $this->assertSame($driver->id, $kendaraan->defaultDriver->id);

        // Nomor polisi kembar ditolak.
        try {
            app(SaveReference::class)->saveVehicle(null, ['plate_no' => 'B-9001-XX']);
            $this->fail('Nomor polisi kembar seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertSame('BR-MST-01', $e->rule);
        }

        $this->assertSame(1, Vehicle::query()->where('plate_no', 'B-9001-XX')->count());
    }

    #[Test]
    public function tc_mst_21b_kategori_penyimpanan_menyimpan_mode_kapasitas(): void
    {
        $penyimpanan = app(SaveReference::class)->saveStorageCategory(null, [
            'code' => 'b3-uji',
            'name' => 'Bahan berbahaya uji',
            'capacity_mode' => CapacityMode::Block->value,
        ]);

        $this->assertSame('B3-UJI', $penyimpanan->code);
        $this->assertSame(CapacityMode::Block, $penyimpanan->capacity_mode);

        // Seeder sudah menyiapkan kategori penyimpanan bawaan (A-37).
        $this->assertGreaterThanOrEqual(6, StorageCategory::query()->active()->count());
    }
}

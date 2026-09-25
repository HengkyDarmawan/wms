<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Domain\Master\Actions\SaveItem;
use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\RemovalStrategy;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Models\Vendor;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Enums\VendorType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-MST-11 s.d. TC-MST-18 — item dan matriks kombinasi pelacakan
 * (BR-STK-08, BR-STK-09, BR-STK-11, BR-STK-12, BR-CNV-03, BR-MST-02, A-52).
 */
class ItemTest extends TenantTestCase
{
    private function uomId(string $code): int
    {
        return (int) Uom::query()->where('code', $code)->value('id');
    }

    /** @param  array<string, mixed>  $override */
    private function simpan(array $override = [], ?Item $item = null, ?array $conversions = null, ?array $vendors = null): Item
    {
        return app(SaveItem::class)->handle($item, array_merge([
            'code' => $item?->code ?? 'ITM-'.strtoupper(substr(uniqid(), -6)),
            'name' => 'Item Uji',
            'base_uom_id' => $item?->base_uom_id ?? $this->uomId('PCS'),
            'tracking_mode' => $item?->tracking_mode?->value ?? TrackingMode::None->value,
            'ownership_model' => $item?->ownership_model?->value ?? OwnershipModel::Consumable->value,
        ], $override), $conversions, $vendors);
    }

    #[Test]
    public function tc_mst_11_aset_wajib_memakai_serial(): void
    {
        try {
            $this->simpan([
                'ownership_model' => OwnershipModel::Asset->value,
                'tracking_mode' => TrackingMode::Lot->value,
            ]);
            $this->fail('Aset dengan pelacakan lot seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertArrayHasKey('ownership_model', $e->fieldErrors);
            $this->assertStringContainsString('Serial number', $e->fieldErrors['ownership_model'], 'BR-STK-08');
        }

        $aset = $this->simpan([
            'ownership_model' => OwnershipModel::Asset->value,
            'tracking_mode' => TrackingMode::Serial->value,
            'removal_strategy' => RemovalStrategy::Manual->value,
        ]);

        $this->assertTrue($aset->isAsset());
        $this->assertTrue($aset->tracksSerial());
    }

    #[Test]
    public function tc_mst_12_item_per_potong_butuh_satuan_dasar_panjang(): void
    {
        try {
            $this->simpan([
                'tracking_mode' => TrackingMode::Piece->value,
                'base_uom_id' => $this->uomId('PCS'),
                'removal_strategy' => RemovalStrategy::OffcutFirst->value,
            ]);
            $this->fail('Item per potong dengan satuan hitung seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertArrayHasKey('base_uom_id', $e->fieldErrors);
            $this->assertStringContainsString('berkategori panjang', $e->fieldErrors['base_uom_id'], 'BR-STK-09');
        }

        $pipa = $this->simpan([
            'tracking_mode' => TrackingMode::Piece->value,
            'base_uom_id' => $this->uomId('M'),
            'removal_strategy' => RemovalStrategy::OffcutFirst->value,
        ]);

        $this->assertTrue($pipa->tracksPiece());
    }

    #[Test]
    public function tc_mst_13_strategi_di_luar_matriks_ditolak(): void
    {
        try {
            $this->simpan([
                'tracking_mode' => TrackingMode::None->value,
                'removal_strategy' => RemovalStrategy::Fefo->value,
                'has_expiry' => true,
            ]);
            $this->fail('FEFO pada item tanpa pelacakan seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertArrayHasKey('removal_strategy', $e->fieldErrors);
        }
    }

    #[Test]
    public function tc_mst_14_fefo_menuntut_kedaluwarsa_aktif(): void
    {
        try {
            $this->simpan([
                'tracking_mode' => TrackingMode::Lot->value,
                'removal_strategy' => RemovalStrategy::Fefo->value,
                'has_expiry' => false,
            ]);
            $this->fail('FEFO tanpa kedaluwarsa seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertArrayHasKey('has_expiry', $e->fieldErrors);
            $this->assertStringContainsString('kedaluwarsa diaktifkan', $e->fieldErrors['has_expiry'], 'BR-STK-12');
        }

        $semen = $this->simpan([
            'tracking_mode' => TrackingMode::Lot->value,
            'removal_strategy' => RemovalStrategy::Fefo->value,
            'has_expiry' => true,
        ]);

        $this->assertTrue($semen->has_expiry);
        $this->assertSame(RemovalStrategy::Fefo, $semen->effectiveRemovalStrategy());
    }

    #[Test]
    public function tc_mst_14b_kedaluwarsa_hanya_untuk_lot_dan_serial(): void
    {
        try {
            $this->simpan(['tracking_mode' => TrackingMode::None->value, 'has_expiry' => true]);
            $this->fail('Kedaluwarsa pada item tanpa pelacakan seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertArrayHasKey('has_expiry', $e->fieldErrors);
        }
    }

    #[Test]
    public function tc_mst_15_item_bisa_dipotong_wajib_punya_panjang_minimum_offcut(): void
    {
        try {
            $this->simpan([
                'tracking_mode' => TrackingMode::Piece->value,
                'base_uom_id' => $this->uomId('M'),
                'removal_strategy' => RemovalStrategy::OffcutFirst->value,
                'is_cuttable' => true,
            ]);
            $this->fail('Item bisa dipotong tanpa panjang minimum seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertArrayHasKey('min_offcut_length', $e->fieldErrors);
            $this->assertStringContainsString('Panjang minimum offcut', $e->fieldErrors['min_offcut_length'], 'BR-CNV-03');
        }

        $pipa = $this->simpan([
            'tracking_mode' => TrackingMode::Piece->value,
            'base_uom_id' => $this->uomId('M'),
            'removal_strategy' => RemovalStrategy::OffcutFirst->value,
            'is_cuttable' => true,
            'min_offcut_length' => '0,5',
        ]);

        $this->assertSame(0.5, (float) $pipa->min_offcut_length);
    }

    #[Test]
    public function tc_mst_16_satuan_dasar_terkunci_setelah_ada_lot(): void
    {
        $item = $this->simpan([
            'tracking_mode' => TrackingMode::Lot->value,
            'base_uom_id' => $this->uomId('KG'),
        ]);

        $this->assertFalse($item->baseUomIsLocked());

        Lot::create(['item_id' => $item->id, 'lot_no' => 'LOT-001']);

        $item->refresh();

        $this->assertTrue($item->baseUomIsLocked());

        try {
            $this->simpan(['base_uom_id' => $this->uomId('TON')], $item);
            $this->fail('Satuan dasar seharusnya terkunci.');
        } catch (MasterRuleException $e) {
            $this->assertSame('BR-MST-02', $e->rule);
            $this->assertArrayHasKey('base_uom_id', $e->fieldErrors);
        }

        // Mengubah field lain tetap boleh selama satuan dasarnya tidak diusik.
        $item = $this->simpan(['name' => 'Nama Baru'], $item);

        $this->assertSame('Nama Baru', $item->name);
        $this->assertSame($this->uomId('KG'), (int) $item->base_uom_id);
    }

    #[Test]
    public function tc_mst_17_konversi_kemasan_tersimpan_dan_ditandai_potongan_nominal(): void
    {
        $item = $this->simpan(
            [
                'tracking_mode' => TrackingMode::Piece->value,
                'base_uom_id' => $this->uomId('M'),
                'removal_strategy' => RemovalStrategy::OffcutFirst->value,
                'is_cuttable' => true,
                'min_offcut_length' => 0.5,
            ],
            conversions: [
                ['uom_id' => $this->uomId('BATANG'), 'qty_base' => 6, 'is_nominal_piece' => true],
            ],
        );

        $konversi = $item->uomConversions()->first();

        $this->assertNotNull($konversi);
        $this->assertSame(6.0, (float) $konversi->qty_base);
        $this->assertTrue($konversi->is_nominal_piece);
        $this->assertSame(18.0, $konversi->toBase(3));

        // Satuan dasar tidak boleh diulang sebagai konversi.
        try {
            $this->simpan([], $item, [['uom_id' => $this->uomId('M'), 'qty_base' => 1]]);
            $this->fail('Satuan dasar sebagai konversi seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertArrayHasKey('conversions', $e->fieldErrors);
        }
    }

    #[Test]
    public function tc_mst_18_vendor_tetap_tersimpan_berurutan(): void
    {
        $utama = Vendor::create([
            'code' => 'V-UTAMA', 'name' => 'Vendor Utama',
            'vendor_type' => VendorType::Company, 'status' => VendorStatus::Active, 'is_active' => true,
        ]);

        $cadangan = Vendor::create([
            'code' => 'V-CADANGAN', 'name' => 'Vendor Cadangan',
            'vendor_type' => VendorType::Shop, 'status' => VendorStatus::Active, 'is_active' => true,
        ]);

        $item = $this->simpan(vendors: [
            ['vendor_id' => $utama->id, 'priority' => 1, 'is_preferred' => true],
            ['vendor_id' => $cadangan->id, 'priority' => 2],
        ]);

        $urutan = $item->itemVendors()->ordered()->get();

        $this->assertCount(2, $urutan);
        $this->assertSame($utama->id, $urutan[0]->vendor_id);
        $this->assertTrue($urutan[0]->is_preferred);
        $this->assertSame($cadangan->id, $urutan[1]->vendor_id);
        $this->assertSame(2, $urutan[1]->priority);

        // Menyimpan ulang tanpa vendor cadangan melepasnya dari item.
        $item = $this->simpan([], $item, vendors: [['vendor_id' => $utama->id, 'is_preferred' => true]]);

        $this->assertSame(1, $item->itemVendors()->count());
    }

    #[Test]
    public function tc_mst_18b_strategi_efektif_diwarisi_kategori(): void
    {
        $kategori = \App\Domain\Master\Models\ItemCategory::create([
            'code' => 'KAT-FIFO',
            'name' => 'Kategori FIFO',
            'removal_strategy' => RemovalStrategy::Fifo,
            'is_active' => true,
        ]);

        $item = $this->simpan(['item_category_id' => $kategori->id]);

        $this->assertNull($item->removal_strategy);
        $this->assertSame(RemovalStrategy::Fifo, $item->effectiveRemovalStrategy());

        // Item per potong tanpa strategi jatuh ke Sisa potongan dulu.
        $pipa = $this->simpan([
            'tracking_mode' => TrackingMode::Piece->value,
            'base_uom_id' => $this->uomId('M'),
        ]);

        $this->assertSame(RemovalStrategy::OffcutFirst, $pipa->effectiveRemovalStrategy());
    }
}

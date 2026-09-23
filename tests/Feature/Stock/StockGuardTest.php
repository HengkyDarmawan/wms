<?php

declare(strict_types=1);

namespace Tests\Feature\Stock;

use App\Domain\Master\Actions\DeactivateItem;
use App\Domain\Master\Enums\CapacityMode;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\StorageCategory;
use App\Domain\Master\Models\Uom;
use App\Domain\Stock\Actions\LockStockPeriod;
use App\Domain\Stock\Actions\ManageReservation;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Support\DocumentNumber;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Actions\ChangeBinStatus;
use App\Domain\Warehouse\Actions\DeactivateWarehouse;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-STK-18 s.d. TC-STK-24 — kapasitas bin, bin on-site, penjagaan BR-GEN-04,
 * penomoran dokumen, dan kunci periode.
 */
class StockGuardTest extends TenantTestCase
{
    private StockLedger $ledger;

    private Warehouse $gudang;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = app(StockLedger::class);

        $this->gudang = app(SaveWarehouse::class)->handle(null, [
            'code' => 'CKG',
            'name' => 'Gudang Utama Cakung',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);

        $this->item = Item::create([
            'code' => 'BAUT-M12',
            'name' => 'Baut M12',
            'status' => ItemStatus::Active,
            'tracking_mode' => TrackingMode::None,
            'base_uom_id' => Uom::query()->where('code', 'PCS')->value('id'),
        ]);
    }

    private function bin(string $code, ?int $storageCategoryId = null, ?float $capacity = null): Bin
    {
        return Bin::create([
            'warehouse_id' => $this->gudang->id,
            'code' => $code,
            'bin_type' => BinType::Storage,
            'storage_category_id' => $storageCategoryId,
            'capacity_qty' => $capacity,
        ]);
    }

    #[Test]
    public function tc_stk_18_kapasitas_blokir_menolak_kelebihan(): void
    {
        $blokir = StorageCategory::query()->where('capacity_mode', CapacityMode::Block->value)->firstOrFail();
        $bin = $this->bin('CKG-A-R01-L1-B01', $blokir->id, 50);

        try {
            $this->ledger->post(new MovementRequest(item: $this->item, qtyBase: 51, toBinId: $bin->id));
            $this->fail('Kelebihan kapasitas pada kategori blokir seharusnya ditolak.');
        } catch (LedgerException $e) {
            $this->assertSame('BR-WH-06', $e->rule);
        }

        // Tepat pada kapasitas tetap boleh.
        $this->ledger->post(new MovementRequest(item: $this->item, qtyBase: 50, toBinId: $bin->id));

        $this->assertSame(1, \App\Domain\Stock\Models\StockMovement::query()->count());
    }

    #[Test]
    public function tc_stk_19_kapasitas_peringatan_tidak_memblokir(): void
    {
        $peringatan = StorageCategory::query()->where('capacity_mode', CapacityMode::Warn->value)->firstOrFail();
        $bin = $this->bin('CKG-A-R01-L1-B02', $peringatan->id, 10);

        $this->ledger->post(new MovementRequest(item: $this->item, qtyBase: 11, toBinId: $bin->id));

        $this->assertSame(
            ['Kapasitas bin CKG-A-R01-L1-B02 terlampaui.'],
            $this->ledger->warnings(),
            'BR-STK-07: kelebihan dicatat sebagai peringatan, bukan penolakan.',
        );
    }

    #[Test]
    public function tc_stk_20_bin_on_site_hanya_menampung_aset(): void
    {
        $onSite = Bin::create([
            'warehouse_id' => $this->gudang->id,
            'code' => 'CKG-ONSITE-PRJ001',
            'bin_type' => BinType::OnSite,
            'project_id' => $this->makeProject()->id,
            'is_virtual' => true,
        ]);

        try {
            $this->ledger->post(new MovementRequest(item: $this->item, qtyBase: 3, toBinId: $onSite->id));
            $this->fail('Barang habis pakai di bin On-site seharusnya ditolak.');
        } catch (LedgerException $e) {
            $this->assertSame('BR-STK-14', $e->rule);
        }
    }

    #[Test]
    public function tc_stk_20b_bin_beku_menolak_pergerakan(): void
    {
        $bin = $this->bin('CKG-A-R01-L1-B03');

        app(ChangeBinStatus::class)->freeze($bin, 'COUNT_FIX');

        try {
            $this->ledger->post(new MovementRequest(item: $this->item, qtyBase: 3, toBinId: $bin->id));
            $this->fail('Bin beku seharusnya menolak pergerakan.');
        } catch (LedgerException $e) {
            $this->assertSame('BR-OPN-02', $e->rule);
        }
    }

    #[Test]
    public function tc_stk_21_gudang_bersaldo_tidak_bisa_dinonaktifkan(): void
    {
        $bin = $this->bin('CKG-A-R01-L1-B04');

        $this->ledger->post(new MovementRequest(item: $this->item, qtyBase: 5, toBinId: $bin->id));

        // Bin penyimpanan aktif sudah ditolak BR-WH-07; kosongkan dulu agar
        // yang diuji benar-benar penjagaan saldo.
        app(ChangeBinStatus::class)->freeze($bin, 'COUNT_FIX');

        try {
            app(DeactivateWarehouse::class)->handle($this->gudang, 'NOT_NEEDED');
            $this->fail('Gudang bersaldo seharusnya ditolak.');
        } catch (WarehouseRuleException $e) {
            $this->assertContains($e->rule, ['BR-GEN-04', 'BR-WH-07']);
        }

        $this->assertTrue($this->gudang->refresh()->is_active);
    }

    #[Test]
    public function tc_stk_21b_bin_bersaldo_tidak_bisa_dinonaktifkan(): void
    {
        $bin = $this->bin('CKG-A-R01-L1-B05');

        $this->ledger->post(new MovementRequest(item: $this->item, qtyBase: 5, toBinId: $bin->id));

        try {
            app(ChangeBinStatus::class)->deactivate($bin, 'NOT_NEEDED');
            $this->fail('Bin bersaldo seharusnya ditolak.');
        } catch (WarehouseRuleException $e) {
            $this->assertSame('BR-GEN-04', $e->rule);
            $this->assertStringContainsString('saldo', $e->getMessage());
        }
    }

    #[Test]
    public function tc_stk_22_item_bersaldo_tidak_bisa_dinonaktifkan(): void
    {
        $bin = $this->bin('CKG-A-R01-L1-B06');

        $this->ledger->post(new MovementRequest(item: $this->item, qtyBase: 5, toBinId: $bin->id));

        try {
            app(DeactivateItem::class)->handle($this->item, 'NOT_NEEDED');
            $this->fail('Item bersaldo seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertSame('BR-GEN-04', $e->rule);
        }

        // Setelah stoknya keluar, barulah boleh.
        $this->ledger->post(new MovementRequest(item: $this->item, qtyBase: 5, fromBinId: $bin->id));

        $item = app(DeactivateItem::class)->handle($this->item, 'NOT_NEEDED');

        $this->assertSame(ItemStatus::Inactive, $item->status);
    }

    #[Test]
    public function tc_stk_22b_item_dengan_reservasi_aktif_tidak_bisa_dinonaktifkan(): void
    {
        $bin = $this->bin('CKG-A-R01-L1-B07');

        $this->ledger->post(new MovementRequest(item: $this->item, qtyBase: 5, toBinId: $bin->id));
        app(ManageReservation::class)->reserveSoft($this->item, $this->gudang, 2, 'material_request', 1);

        // Saldo dikosongkan supaya yang menolak benar-benar reservasinya.
        $this->ledger->post(new MovementRequest(item: $this->item, qtyBase: 5, fromBinId: $bin->id));

        try {
            app(DeactivateItem::class)->handle($this->item, 'NOT_NEEDED');
            $this->fail('Item dengan reservasi aktif seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertSame('BR-GEN-04', $e->rule);
            $this->assertStringContainsString('reservasi aktif', $e->getMessage());
        }
    }

    #[Test]
    public function tc_stk_23_nomor_dokumen_tidak_pernah_kembar(): void
    {
        $penomor = app(DocumentNumber::class);

        $nomor = [];

        for ($i = 0; $i < 30; $i++) {
            $nomor[] = $penomor->next('GRN', 'CKG');
        }

        $this->assertCount(30, array_unique($nomor), 'Nomor dokumen tidak boleh kembar.');
        $this->assertStringStartsWith('GRN/CKG/', $nomor[0]);
        $this->assertStringEndsWith('/0001', $nomor[0]);
        $this->assertStringEndsWith('/0030', $nomor[29]);

        // Segmen berbeda punya urutan sendiri.
        $this->assertStringEndsWith('/0001', $penomor->next('GRN', 'BKS'));

        // Jenis dokumen berbeda juga.
        $this->assertStringEndsWith('/0001', $penomor->next('SJ', 'CKG'));

        // Nomor sementara untuk dokumen luring.
        $sementara = $penomor->temporary();

        $this->assertTrue($penomor->isTemporary($sementara));
        $this->assertFalse($penomor->isTemporary($nomor[0]));
    }

    #[Test]
    public function tc_stk_23b_kunci_periode_hanya_bisa_maju(): void
    {
        $aksi = app(LockStockPeriod::class);

        $this->assertNull($aksi->current());

        $kemarin = now()->subDay()->toDateString();
        $aksi->handle($kemarin);

        $this->assertSame($kemarin, $aksi->current());

        // Mundur ditolak.
        try {
            $aksi->handle(now()->subDays(5)->toDateString());
            $this->fail('Memundurkan tanggal kunci seharusnya ditolak.');
        } catch (LedgerException $e) {
            $this->assertSame('BR-STK-15', $e->rule);
        }

        // Melewati hari ini ditolak.
        try {
            $aksi->handle(now()->addDay()->toDateString());
            $this->fail('Tanggal kunci di masa depan seharusnya ditolak.');
        } catch (LedgerException $e) {
            $this->assertSame('BR-STK-15', $e->rule);
        }

        $this->assertSame($kemarin, $aksi->current());
    }

    #[Test]
    public function tc_stk_24_item_per_potong_melaporkan_jumlah_potongan(): void
    {
        $meter = Uom::query()->where('code', 'M')->value('id');

        $pipa = Item::create([
            'code' => 'PIPA-PVC-4',
            'name' => 'Pipa PVC 4 inci',
            'status' => ItemStatus::Active,
            'tracking_mode' => TrackingMode::Piece,
            'base_uom_id' => $meter,
            'is_cuttable' => true,
            'min_offcut_length' => 0.5,
        ]);

        $bin = $this->bin('CKG-A-R01-L1-B08');

        foreach ([6.0, 6.0, 2.5] as $i => $panjang) {
            $potongan = \App\Domain\Master\Models\Piece::create([
                'item_id' => $pipa->id,
                'piece_no' => 'P-'.($i + 1),
                'length' => $panjang,
            ]);

            $this->ledger->post(new MovementRequest(
                item: $pipa,
                qtyBase: $panjang,
                toBinId: $bin->id,
                pieceId: $potongan->id,
            ));
        }

        $saldo = \App\Domain\Stock\Models\StockBalance::query()
            ->where('item_id', $pipa->id)
            ->where('bin_id', $bin->id)
            ->get();

        $this->assertSame(3, $saldo->count(), 'Satu baris saldo per potongan.');
        $this->assertSame(14.5, round((float) $saldo->sum('qty_base'), 4), 'Total panjang.');
        $this->assertSame(3, (int) $saldo->sum('piece_count'), 'BR-STK-09: jumlah potongan ikut dilaporkan.');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Stock;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Uom;
use App\Domain\Stock\Actions\ManageReservation;
use App\Domain\Stock\Enums\ReservationLevel;
use App\Domain\Stock\Enums\ReservationStatus;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-STK-14 s.d. TC-STK-17 — reservasi lunak dan keras
 * (BR-STK-03, BR-STK-04, BR-STK-05, BR-STK-16).
 */
class ReservationTest extends TenantTestCase
{
    private StockLedger $ledger;

    private ManageReservation $reservasi;

    private Warehouse $gudang;

    private Bin $bin;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = app(StockLedger::class);
        $this->reservasi = app(ManageReservation::class);

        $this->gudang = app(SaveWarehouse::class)->handle(null, [
            'code' => 'CKG',
            'name' => 'Gudang Utama Cakung',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);

        $this->bin = Bin::create([
            'warehouse_id' => $this->gudang->id,
            'code' => 'CKG-A-R01-L1-B01',
            'bin_type' => BinType::Storage,
        ]);

        $this->item = Item::create([
            'code' => 'BAUT-M12',
            'name' => 'Baut M12',
            'status' => ItemStatus::Active,
            'tracking_mode' => TrackingMode::None,
            'base_uom_id' => Uom::query()->where('code', 'PCS')->value('id'),
        ]);

        // Saldo awal 20 di bin.
        $this->ledger->post(new MovementRequest(
            item: $this->item,
            qtyBase: 20,
            toBinId: $this->bin->id,
        ));
    }

    #[Test]
    public function tc_stk_14_stok_tersedia_dikurangi_reservasi_aktif(): void
    {
        $this->assertSame(20.0, $this->ledger->availableQty((int) $this->item->id, (int) $this->gudang->id));

        $this->reservasi->reserveSoft($this->item, $this->gudang, 4, 'material_request', 1, 10);

        $this->assertSame(
            16.0,
            $this->ledger->availableQty((int) $this->item->id, (int) $this->gudang->id),
            'BR-STK-03: reservasi aktif mengurangi stok tersedia, bukan saldo.',
        );
    }

    #[Test]
    public function tc_stk_15_reservasi_terpenuhi_berhenti_mengurangi(): void
    {
        $r = $this->reservasi->reserveSoft($this->item, $this->gudang, 5, 'material_request', 1);

        $this->assertSame(15.0, $this->ledger->availableQty((int) $this->item->id, (int) $this->gudang->id));

        // Barang benar-benar keluar, lalu reservasinya ditandai terpenuhi.
        $this->ledger->post(new MovementRequest(
            item: $this->item,
            qtyBase: 5,
            fromBinId: $this->bin->id,
        ));

        $r = $this->reservasi->consume($r);

        $this->assertSame(ReservationStatus::Consumed, $r->status);
        $this->assertSame(
            15.0,
            $this->ledger->availableQty((int) $this->item->id, (int) $this->gudang->id),
            'Stok tersedia tidak boleh berkurang dua kali.',
        );
    }

    #[Test]
    public function tc_stk_16_reservasi_dilepas_dengan_alasan(): void
    {
        $r = $this->reservasi->reserveSoft($this->item, $this->gudang, 6, 'material_request', 1);

        // BR-GEN-11: alasan wajib.
        try {
            $this->reservasi->release($r, '');
            $this->fail('Pelepasan tanpa alasan seharusnya ditolak.');
        } catch (LedgerException $e) {
            $this->assertSame('BR-GEN-11', $e->rule);
        }

        $r = $this->reservasi->release($r, 'NOT_NEEDED');

        $this->assertSame(ReservationStatus::Released, $r->status);
        $this->assertSame('NOT_NEEDED', $r->released_reason);
        $this->assertNotNull($r->released_at);
        $this->assertSame(20.0, $this->ledger->availableQty((int) $this->item->id, (int) $this->gudang->id));

        // Reservasi yang sudah dilepas tidak bisa dilepas lagi.
        try {
            $this->reservasi->release($r, 'NOT_NEEDED');
            $this->fail('Pelepasan kedua seharusnya ditolak.');
        } catch (LedgerException $e) {
            $this->assertSame('BR-STK-05', $e->rule);
        }
    }

    #[Test]
    public function tc_stk_16b_seluruh_reservasi_dokumen_dilepas_sekaligus(): void
    {
        $this->reservasi->reserveSoft($this->item, $this->gudang, 3, 'material_request', 9, 1);
        $this->reservasi->reserveSoft($this->item, $this->gudang, 2, 'material_request', 9, 2);
        $this->reservasi->reserveSoft($this->item, $this->gudang, 1, 'material_request', 99, 1);

        $jumlah = $this->reservasi->releaseForDocument('material_request', 9, 'NOT_NEEDED');

        $this->assertSame(2, $jumlah);
        $this->assertSame(1, StockReservation::query()->active()->count(), 'Dokumen lain tidak ikut terlepas.');
    }

    #[Test]
    public function tc_stk_17_reservasi_melebihi_stok_tersedia_ditolak(): void
    {
        $this->reservasi->reserveSoft($this->item, $this->gudang, 18, 'material_request', 1);

        try {
            $this->reservasi->reserveSoft($this->item, $this->gudang, 5, 'material_request', 2);
            $this->fail('Reservasi melebihi stok tersedia seharusnya ditolak.');
        } catch (LedgerException $e) {
            $this->assertSame('BR-STK-03', $e->rule);
            $this->assertStringContainsString('hanya 2', $e->getMessage());
        }

        $this->assertSame(1, StockReservation::query()->active()->count());
    }

    #[Test]
    public function tc_stk_17b_alokasi_keras_wajib_menunjuk_bin(): void
    {
        try {
            $this->reservasi->reserveHard($this->item, $this->gudang, 2, [], 'pick_task', 1);
            $this->fail('Alokasi keras tanpa bin seharusnya ditolak.');
        } catch (LedgerException $e) {
            $this->assertSame('BR-STK-04', $e->rule);
        }

        $keras = $this->reservasi->reserveHard(
            $this->item,
            $this->gudang,
            2,
            ['bin_id' => $this->bin->id],
            'pick_task',
            1,
        );

        $this->assertSame(ReservationLevel::Hard, $keras->level);
        $this->assertSame($this->bin->id, $keras->bin_id);
    }

    #[Test]
    public function tc_stk_17c_reservasi_menggantung_terdeteksi(): void
    {
        $lama = $this->reservasi->reserveSoft($this->item, $this->gudang, 3, 'material_request', 1);
        $this->reservasi->reserveSoft($this->item, $this->gudang, 2, 'material_request', 2);

        // Satu reservasi dibuat sepuluh hari lalu.
        $lama->forceFill(['created_at' => now()->subDays(10)])->save();

        $menggantung = StockReservation::query()->stale(7)->get();

        $this->assertCount(1, $menggantung, 'BR-STK-16: hanya yang melewati ambang yang muncul.');
        $this->assertSame($lama->id, $menggantung->first()->id);
        $this->assertSame(10, $menggantung->first()->ageInDays());
    }
}

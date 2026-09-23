<?php

declare(strict_types=1);

namespace Tests\Feature\Stock;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\CompanySetting;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Serial;
use App\Domain\Master\Models\Uom;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Stock\Models\StockMovement;
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
 * TC-STK-01 s.d. TC-STK-13, TC-STK-25 — kartu stok dan saldo
 * (P-01, BR-STK-01, BR-STK-06, BR-STK-15, BR-LED-01 s.d. BR-LED-06).
 */
class StockLedgerTest extends TenantTestCase
{
    private StockLedger $ledger;

    private Warehouse $gudang;

    private Bin $binA;

    private Bin $binB;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = app(StockLedger::class);

        $tipe = WarehouseType::query()->where('code', WarehouseType::MAIN)->firstOrFail();

        $this->gudang = app(SaveWarehouse::class)->handle(null, [
            'code' => 'CKG',
            'name' => 'Gudang Utama Cakung',
            'warehouse_type_id' => $tipe->id,
        ]);

        $this->binA = $this->buatBin('CKG-A-R01-L1-B01');
        $this->binB = $this->buatBin('CKG-A-R01-L1-B02');
        $this->item = $this->buatItem();
    }

    private function buatBin(string $code, BinType $type = BinType::Storage): Bin
    {
        return Bin::create([
            'warehouse_id' => $this->gudang->id,
            'code' => $code,
            'bin_type' => $type,
        ]);
    }

    private function buatItem(TrackingMode $mode = TrackingMode::None, OwnershipModel $ownership = OwnershipModel::Consumable): Item
    {
        return Item::create([
            'code' => 'ITM-'.strtoupper(substr(uniqid(), -6)),
            'name' => 'Item Uji',
            'status' => ItemStatus::Active,
            'tracking_mode' => $mode,
            'ownership_model' => $ownership,
            'base_uom_id' => Uom::query()->where('code', 'PCS')->value('id'),
        ]);
    }

    private function masuk(float $qty, ?Bin $bin = null, array $extra = []): StockMovement
    {
        return $this->ledger->post(new MovementRequest(
            item: $extra['item'] ?? $this->item,
            qtyBase: $qty,
            toBinId: ($bin ?? $this->binA)->id,
            lotId: $extra['lotId'] ?? null,
            serialId: $extra['serialId'] ?? null,
            eventType: $extra['eventType'] ?? null,
            documentType: $extra['documentType'] ?? null,
            documentId: $extra['documentId'] ?? null,
        ));
    }

    private function saldo(Bin $bin, ?Item $item = null): float
    {
        return (float) StockBalance::query()
            ->where('item_id', ($item ?? $this->item)->id)
            ->where('bin_id', $bin->id)
            ->sum('qty_base');
    }

    #[Test]
    public function tc_stk_01_posting_masuk_menambah_saldo(): void
    {
        $movement = $this->masuk(10);

        $this->assertSame(10.0, $this->saldo($this->binA));
        $this->assertTrue($movement->isInbound());
        $this->assertSame(1, StockMovement::query()->count());
        $this->assertSame(StockStatus::Available, $movement->stock_status);
    }

    #[Test]
    public function tc_stk_02_posting_keluar_mengurangi_saldo(): void
    {
        $this->masuk(10);

        $keluar = $this->ledger->post(new MovementRequest(
            item: $this->item,
            qtyBase: 4,
            fromBinId: $this->binA->id,
        ));

        $this->assertSame(6.0, $this->saldo($this->binA));
        $this->assertTrue($keluar->isOutbound());
    }

    #[Test]
    public function tc_stk_02b_pindah_antar_bin_menjaga_total(): void
    {
        $this->masuk(10);

        $pindah = $this->ledger->post(new MovementRequest(
            item: $this->item,
            qtyBase: 4,
            fromBinId: $this->binA->id,
            toBinId: $this->binB->id,
        ));

        $this->assertSame(6.0, $this->saldo($this->binA));
        $this->assertSame(4.0, $this->saldo($this->binB));
        $this->assertTrue($pindah->isTransfer());
    }

    #[Test]
    public function tc_stk_03_saldo_tidak_boleh_negatif(): void
    {
        $this->masuk(5);

        try {
            $this->ledger->post(new MovementRequest(
                item: $this->item,
                qtyBase: 6,
                fromBinId: $this->binA->id,
            ));
            $this->fail('Pengurangan melebihi saldo seharusnya ditolak.');
        } catch (LedgerException $e) {
            $this->assertSame('BR-STK-06', $e->rule);
            $this->assertStringContainsString('tidak cukup', $e->getMessage());
        }

        $this->assertSame(5.0, $this->saldo($this->binA), 'Saldo tidak boleh berubah saat posting ditolak.');
        $this->assertSame(1, StockMovement::query()->count());
    }

    #[Test]
    public function tc_stk_04_kartu_stok_tidak_bisa_diubah_atau_dihapus(): void
    {
        $movement = $this->masuk(10);

        try {
            $movement->forceFill(['qty_base' => 99])->save();
            $this->fail('Kartu stok seharusnya tidak bisa diubah.');
        } catch (LedgerException $e) {
            $this->assertSame('P-01', $e->rule);
        }

        try {
            $movement->delete();
            $this->fail('Kartu stok seharusnya tidak bisa dihapus.');
        } catch (LedgerException $e) {
            $this->assertSame('P-01', $e->rule);
        }

        $this->assertSame(10.0, (float) $movement->refresh()->qty_base);
    }

    #[Test]
    public function tc_stk_05_pergerakan_tanpa_asal_dan_tujuan_ditolak(): void
    {
        try {
            $this->ledger->post(new MovementRequest(item: $this->item, qtyBase: 5));
            $this->fail('Pergerakan tanpa bin seharusnya ditolak.');
        } catch (LedgerException $e) {
            $this->assertSame('BR-LED-01', $e->rule);
        }
    }

    #[Test]
    public function tc_stk_06_jumlah_nol_atau_negatif_ditolak(): void
    {
        foreach ([0.0, -3.0] as $qty) {
            try {
                $this->ledger->post(new MovementRequest(
                    item: $this->item,
                    qtyBase: $qty,
                    toBinId: $this->binA->id,
                ));
                $this->fail('Jumlah '.$qty.' seharusnya ditolak.');
            } catch (LedgerException $e) {
                $this->assertSame('BR-LED-02', $e->rule);
            }
        }
    }

    #[Test]
    public function tc_stk_07_item_berlot_wajib_menyebut_lot(): void
    {
        $item = $this->buatItem(TrackingMode::Lot);

        try {
            $this->masuk(5, null, ['item' => $item]);
            $this->fail('Item berlot tanpa lot seharusnya ditolak.');
        } catch (LedgerException $e) {
            $this->assertSame('BR-LED-03', $e->rule);
        }

        $lot = Lot::create(['item_id' => $item->id, 'lot_no' => 'LOT-001']);

        $this->masuk(5, null, ['item' => $item, 'lotId' => $lot->id]);

        $this->assertSame(5.0, $this->saldo($this->binA, $item));
    }

    #[Test]
    public function tc_stk_08_satu_serial_hanya_di_satu_bin(): void
    {
        $item = $this->buatItem(TrackingMode::Serial, OwnershipModel::Asset);
        $serial = Serial::create(['item_id' => $item->id, 'serial_no' => 'SN-001']);

        $this->masuk(1, $this->binA, ['item' => $item, 'serialId' => $serial->id]);

        try {
            $this->masuk(1, $this->binB, ['item' => $item, 'serialId' => $serial->id]);
            $this->fail('Serial yang sama di dua bin seharusnya ditolak.');
        } catch (LedgerException $e) {
            $this->assertSame('BR-LED-04', $e->rule);
        }

        // Memindahkannya tetap boleh, karena keluar dari bin lama sekaligus.
        $this->ledger->post(new MovementRequest(
            item: $item,
            qtyBase: 1,
            fromBinId: $this->binA->id,
            toBinId: $this->binB->id,
            serialId: $serial->id,
        ));

        $this->assertSame(0.0, $this->saldo($this->binA, $item));
        $this->assertSame(1.0, $this->saldo($this->binB, $item));
    }

    #[Test]
    public function tc_stk_08b_pergerakan_aset_wajib_menyebut_serial(): void
    {
        $aset = $this->buatItem(TrackingMode::Serial, OwnershipModel::Asset);

        try {
            $this->masuk(1, null, ['item' => $aset]);
            $this->fail('Aset tanpa serial seharusnya ditolak.');
        } catch (LedgerException $e) {
            $this->assertContains($e->rule, ['BR-LED-03', 'BR-STK-08']);
        }
    }

    #[Test]
    public function tc_stk_09_pembalikan_mengembalikan_saldo(): void
    {
        $this->masuk(10);

        $keluar = $this->ledger->post(new MovementRequest(
            item: $this->item,
            qtyBase: 4,
            fromBinId: $this->binA->id,
            toBinId: $this->binB->id,
        ));

        $pembalik = $this->ledger->reverse($keluar);

        $this->assertSame(10.0, $this->saldo($this->binA));
        $this->assertSame(0.0, $this->saldo($this->binB));
        $this->assertSame($keluar->id, (int) $pembalik->reverses_movement_id);
        $this->assertSame($keluar->to_bin_id, $pembalik->from_bin_id, 'Asal dan tujuan ditukar.');
    }

    #[Test]
    public function tc_stk_10_pembalikan_hanya_sekali(): void
    {
        $this->masuk(10);
        $pembalik = $this->ledger->reverse(StockMovement::query()->firstOrFail());

        try {
            $this->ledger->reverse(StockMovement::query()->firstOrFail());
            $this->fail('Pembalikan kedua seharusnya ditolak.');
        } catch (LedgerException $e) {
            $this->assertSame('BR-LED-05', $e->rule);
        }

        try {
            $this->ledger->reverse($pembalik);
            $this->fail('Baris pembalik seharusnya tidak bisa dibalik lagi.');
        } catch (LedgerException $e) {
            $this->assertSame('BR-LED-05', $e->rule);
        }
    }

    #[Test]
    public function tc_stk_11_kejadian_stok_tertulis_bersama_pergerakan(): void
    {
        $movement = $this->masuk(10, null, [
            'eventType' => StockEventType::GoodsReceived,
            'documentType' => 'goods_receipt',
            'documentId' => 77,
        ]);

        $kejadian = StockEvent::query()->firstOrFail();

        $this->assertSame(StockEventType::GoodsReceived, $kejadian->event_type);
        $this->assertSame('goods_receipt', $kejadian->source_type);
        $this->assertSame(77, (int) $kejadian->source_id);
        $this->assertSame($movement->id, $kejadian->payload['movement_id']);
        $this->assertEqualsWithDelta(10.0, (float) $kejadian->payload['qty_base'], 0.0001);
        $this->assertNull($kejadian->published_at, 'Outbox belum dikonsumsi.');

        // D-07: tidak ada nilai uang di payload.
        $this->assertArrayNotHasKey('price', $kejadian->payload);
        $this->assertArrayNotHasKey('amount', $kejadian->payload);
    }

    #[Test]
    public function tc_stk_12_posting_gagal_tidak_meninggalkan_kejadian(): void
    {
        $this->masuk(5);

        try {
            $this->ledger->post(new MovementRequest(
                item: $this->item,
                qtyBase: 99,
                fromBinId: $this->binA->id,
                eventType: StockEventType::GoodsShipped,
            ));
        } catch (LedgerException) {
            // diharapkan
        }

        $this->assertSame(0, StockEvent::query()->count(), 'Kejadian ikut batal bersama pergerakannya.');
        $this->assertSame(5.0, $this->saldo($this->binA));
    }

    #[Test]
    public function tc_stk_13_periode_terkunci_menolak_mutasi(): void
    {
        $this->masuk(10);

        CompanySetting::put('stock_lock_date', now()->toDateString());

        try {
            $this->masuk(5);
            $this->fail('Posting di periode terkunci seharusnya ditolak.');
        } catch (LedgerException $e) {
            $this->assertSame('BR-STK-15', $e->rule);
        }

        $this->assertSame(10.0, $this->saldo($this->binA));
    }

    #[Test]
    public function tc_stk_25_saldo_bisa_dibangun_ulang_dari_kartu_stok(): void
    {
        $this->masuk(10);
        $this->masuk(7, $this->binB);

        $this->ledger->post(new MovementRequest(
            item: $this->item,
            qtyBase: 3,
            fromBinId: $this->binA->id,
            toBinId: $this->binB->id,
        ));

        $dibangunUlang = $this->ledger->rebuildFromLedger((int) $this->item->id);

        $tersimpan = StockBalance::query()
            ->where('item_id', $this->item->id)
            ->get()
            ->mapWithKeys(fn (StockBalance $b) => [
                implode('|', [$b->item_id, $b->bin_id, 0, 0, 0, $b->stock_status->value]) => round((float) $b->qty_base, 4),
            ])
            ->all();

        $this->assertSame($tersimpan, $dibangunUlang, 'Saldo tersimpan harus sama dengan hasil agregasi ledger.');
        $this->assertSame(7.0, $this->saldo($this->binA));
        $this->assertSame(10.0, $this->saldo($this->binB));
    }
}

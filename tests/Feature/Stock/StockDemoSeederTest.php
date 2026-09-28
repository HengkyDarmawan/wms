<?php

declare(strict_types=1);

namespace Tests\Feature\Stock;

use App\Domain\Master\Models\FeatureSetting;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\Vehicle;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Models\Warehouse;
use Database\Seeders\Tenant\DemoSeeder;
use Database\Seeders\Tenant\StockDemoSeeder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-STK-34 — stok awal demo masuk lewat kartu stok, bukan menulis saldo (P-01, A-72).
 */
class StockDemoSeederTest extends TenantTestCase
{
    #[Test]
    public function tc_stk_34_stok_demo_masuk_lewat_kartu_stok_dan_tidak_berlipat(): void
    {
        (new DemoSeeder)->run();

        $ledger = app(StockLedger::class);
        $baut = Item::query()->where('code', 'BAUT-M12')->firstOrFail();
        $ckg = Warehouse::withoutGlobalScopes()->where('code', 'CKG')->firstOrFail();

        $this->assertSame(1000.0, $ledger->availableQty((int) $baut->id, (int) $ckg->id));

        // Tiap kode stok demo punya saldo di gudang.
        foreach (['BAUT-M12' => 1300.0, 'SEMEN-PCC-50' => 4000.0, 'PIPA-PVC-4' => 87.7, 'GENSET-5KVA' => 2.0] as $kode => $total) {
            $itemId = Item::query()->where('code', $kode)->value('id');
            $this->assertEqualsWithDelta($total, (float) StockBalance::query()->where('item_id', $itemId)->sum('qty_base'), 0.0001, 'Saldo '.$kode);
        }

        // Saldo sama dengan hasil penjumlahan kartu stok.
        foreach (StockBalance::query()->get() as $saldo) {
            $this->assertGreaterThan(0, (float) $saldo->qty_base);
        }
        $this->assertEqualsWithDelta(
            (float) StockBalance::query()->sum('qty_base'),
            array_sum($ledger->rebuildFromLedger()),
            0.0001,
        );

        // Setiap pergerakan menerbitkan kejadian di outbox (BR-LED-06).
        // A-283: pipa Barang biasa — 2 baut + 3 semen + 2 pipa + 2 genset, tanpa potongan.
        $jumlah = StockMovement::query()->where('notes', StockDemoSeeder::NOTES)->count();
        $this->assertSame(9, $jumlah);
        $this->assertSame(0, Piece::query()->count());
        $this->assertFalse(FeatureSetting::enabled('piece'), 'A-284: DEMO tanpa per potong.');
        $this->assertFalse(FeatureSetting::enabled('qc'), 'A-284: DEMO tanpa QC penerimaan.');
        $this->assertSame($jumlah, StockEvent::query()->count());

        // A-311: driver bawaan = nama + HP teks, bukan akun (00-akun-uji §2).
        $this->assertSame(0, Vehicle::query()->whereNotNull('default_driver_id')->count());
        $this->assertSame(['B 9001 XX' => 'Gani', 'B 9002 XX' => 'Hadi'], Vehicle::query()->orderBy('plate_no')->pluck('default_driver_name', 'plate_no')->all());
        $this->assertSame('6281200000008', Vehicle::query()->where('plate_no', 'B 9001 XX')->value('default_driver_phone'));

        // Menjalankan ulang tidak menggandakan stok.
        (new StockDemoSeeder)->run();
        $this->assertSame($jumlah, StockMovement::query()->count());
        $this->assertSame(2, Vehicle::query()->count());
    }
}

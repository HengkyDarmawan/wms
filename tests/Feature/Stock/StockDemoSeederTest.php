<?php

declare(strict_types=1);

namespace Tests\Feature\Stock;

use App\Domain\Master\Models\Item;
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
        $jumlah = StockMovement::query()->where('notes', StockDemoSeeder::NOTES)->count();
        $this->assertSame(23, $jumlah);
        $this->assertSame($jumlah, StockEvent::query()->count());

        $this->assertSame(2, Vehicle::query()->whereNotNull('default_driver_id')->count());

        // Menjalankan ulang tidak menggandakan stok.
        (new StockDemoSeeder)->run();
        $this->assertSame($jumlah, StockMovement::query()->count());
        $this->assertSame(2, Vehicle::query()->count());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Stock;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Uom;
use App\Domain\Notification\Models\Notification;
use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Domain\Platform\Support\OperatingCompanies;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Stock\Support\StockReconciler;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\WarehouseType;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-STK-35 — rekonsiliasi saldo terjadwal `stock:reconcile` (BR-STK-01,
 * A-243): selisih saldo vs kartu stok dilaporkan ke Admin Company, saldo
 * tidak diubah otomatis (P-01).
 */
class StockReconcileTest extends TenantTestCase
{
    #[Test]
    public function tc_stk_35_selisih_saldo_dilaporkan_tanpa_diperbaiki_otomatis(): void
    {
        NotificationFacade::fake();
        $admin = $this->makeUser('company_admin');
        $kepala = $this->makeUser('warehouse_head');

        $gudang = app(SaveWarehouse::class)->handle(null, ['code' => 'CKG', 'name' => 'Gudang Utama Cakung',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id')]);
        $binA = Bin::create(['warehouse_id' => $gudang->id, 'code' => 'CKG-A-R01-L1-B01', 'bin_type' => BinType::Storage]);
        $binB = Bin::create(['warehouse_id' => $gudang->id, 'code' => 'CKG-A-R01-L1-B02', 'bin_type' => BinType::Storage]);
        $item = Item::create(['code' => 'BAUT-M12', 'name' => 'Baut M12', 'status' => ItemStatus::Active,
            'tracking_mode' => TrackingMode::None, 'base_uom_id' => Uom::query()->where('code', 'PCS')->value('id')]);

        $ledger = app(StockLedger::class);
        $ledger->post(new MovementRequest(item: $item, qtyBase: 10, toBinId: $binA->id));
        $ledger->post(new MovementRequest(item: $item, qtyBase: 4, fromBinId: $binA->id, toBinId: $binB->id));

        $this->assertSame([], app(StockReconciler::class)->differences());
        $this->artisan('stock:reconcile')->expectsOutputToContain('saldo cocok')->assertSuccessful();

        // Saldo dirusak di luar kartu stok: dilaporkan, tidak diperbaiki.
        StockBalance::query()->where('bin_id', $binA->id)->update(['qty_base' => 9]);
        $selisih = app(StockReconciler::class)->differences();
        $this->assertCount(1, $selisih);
        $this->assertSame(6.0, $selisih[0]['ledger']);
        $this->assertSame(9.0, $selisih[0]['balance']);
        $this->assertSame(3.0, $selisih[0]['difference']);

        $this->artisan('stock:reconcile')->expectsOutputToContain('1 saldo tidak cocok')->assertSuccessful();
        $this->assertSame(9.0, (float) StockBalance::query()->where('bin_id', $binA->id)->value('qty_base'), 'Tidak diperbaiki otomatis.');
        $this->assertSame(1, Notification::query()->inApp()->where('user_id', $admin->id)->where('type', 'stock.balance_mismatch')->count());
        $this->assertSame(0, Notification::query()->where('user_id', $kepala->id)->where('type', 'stock.balance_mismatch')->count());

        // Company ditangguhkan dilewati job terjadwal (A-236) — di sini lewat --tenants.
        $this->setSubscriptionStatus(SubscriptionStatus::Suspended);
        $this->assertTrue(OperatingCompanies::halted($this->company));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Uom;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-DSH-01 — Beranda menampilkan kartu pekerjaan menunggu sesuai izin dan
 * cakupan pengguna (A-186).
 */
class DashboardTest extends TenantTestCase
{
    #[Test]
    public function tc_dsh_01_kartu_pekerjaan_mengikuti_izin_dan_cakupan(): void
    {
        $gudang = app(SaveWarehouse::class)->handle(null, [
            'code' => 'CKG', 'name' => 'Gudang Cakung',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);
        $bks = app(SaveWarehouse::class)->handle(null, [
            'code' => 'BKS', 'name' => 'Gudang Bekasi',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);
        $item = Item::create(['code' => 'BAUT', 'name' => 'Baut', 'status' => ItemStatus::Active, 'tracking_mode' => TrackingMode::None,
            'base_uom_id' => Uom::query()->where('code', 'PCS')->value('id')]);

        // REQ tanpa gudang sumber masuk tinjauan (TC-REQ-06).
        $proyek = $this->makeProject();
        $pemohon = $this->makeUser('internal_requester');
        $req = app(SaveRequest::class)->handle(null, ['project_id' => $proyek->id, 'required_date' => now()->addDays(3)->toDateString()],
            [['item_id' => $item->id, 'qty_base' => 5]], $pemohon);
        app(SubmitRequest::class)->handle($req, $pemohon);

        $kepala = $this->makeUser('warehouse_head');
        $this->actingAs($kepala)->get($this->tenantUrl('/'))->assertOk()
            ->assertSee(__('Pekerjaan menunggu'))
            ->assertSee(__('REQ perlu ditinjau'))
            ->assertSee(route('requests.index', ['statusFilter' => 'under_review']), false)
            ->assertSee(__('Put-away menunggu'))
            ->assertDontSee(__('Modul berikutnya'));

        // Driver tidak memegang izin tinjau/terima: kartunya tidak muncul.
        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl('/'))->assertOk()
            ->assertDontSee(__('REQ perlu ditinjau'))->assertDontSee(__('Put-away menunggu'))
            ->assertSee(__('SJ dalam perjalanan'));

        // Penindak Lanjut PR melihat PRQ yang belum dipesan.
        $this->actingAs($this->makeUser('pr_follow_up'))->get($this->tenantUrl('/'))->assertOk()->assertSee(__('PRQ belum dipesan'));

        $this->assertNotNull($gudang);
        $this->assertNotNull($bks);
        $this->actingAs($this->makeUser('warehouse_staff', ScopeType::Warehouse, $bks->id))->get($this->tenantUrl('/'))->assertOk();
    }
}

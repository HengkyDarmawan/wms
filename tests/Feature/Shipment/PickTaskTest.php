<?php

declare(strict_types=1);

namespace Tests\Feature\Shipment;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Uom;
use App\Domain\Request\Actions\ApproveRequest;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Shipment\Actions\CreatePickTask;
use App\Domain\Shipment\Actions\ProcessPickTask;
use App\Domain\Shipment\Enums\PickTaskStatus;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Shipment\Support\WarehouseBins;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Actions\ChangeBinStatus;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-PCK-01 s.d. TC-PCK-08 — tugas picking (BR-SJ-01, BR-SJ-02, BR-OPN-02).
 */
class PickTaskTest extends TenantTestCase
{
    private Project $proyek;

    private Warehouse $gudang;

    private Bin $bin;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->proyek = $this->makeProject();

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

        app(StockLedger::class)->post(new MovementRequest(
            item: $this->item,
            qtyBase: 100,
            toBinId: $this->bin->id,
        ));
    }

    private function alasanId(): int
    {
        $id = ReasonCode::query()->value('id');

        $this->assertNotNull($id);

        return (int) $id;
    }

    /** REQ yang sudah disetujui, lengkap dengan reservasi lunaknya. */
    private function reqDisetujui(float $qty = 20): MaterialRequest
    {
        $pemohon = $this->makeUser('internal_requester');

        $req = app(SaveRequest::class)->handle(
            null,
            ['project_id' => $this->proyek->id, 'required_date' => now()->addDays(3)->toDateString()],
            [['item_id' => $this->item->id, 'qty_base' => $qty]],
            $pemohon,
        );

        $req->openLines()->first()->forceFill([
            'source_warehouse_id' => $this->gudang->id,
            'fulfillment_source' => 'stock',
        ])->save();

        $req = app(SubmitRequest::class)->handle($req->refresh(), $pemohon);

        return app(ApproveRequest::class)->handle($req, $this->makeUser('company_admin'));
    }

    private function pckSiap(float $qty = 20): PickTask
    {
        $tugas = app(CreatePickTask::class)->handle($this->reqDisetujui($qty), $this->makeUser('warehouse_head'));

        return $tugas[0];
    }

    #[Test]
    public function tc_pck_01_pck_dibuat_dengan_alokasi_per_bin(): void
    {
        $req = $this->reqDisetujui();

        $tugas = app(CreatePickTask::class)->handle($req, $this->makeUser('warehouse_head'));

        $this->assertCount(1, $tugas, 'Satu gudang sumber, satu PCK.');

        $pck = $tugas[0];

        $this->assertSame(PickTaskStatus::Pending, $pck->status);
        $this->assertMatchesRegularExpression('#^PCK/CKG/\d{4}/\d{4}$#', $pck->number);
        $this->assertSame(1, $pck->lines()->count());
        $this->assertSame((int) $this->bin->id, (int) $pck->lines()->first()->bin_id);
        $this->assertSame(20.0, (float) $pck->lines()->first()->qty_allocated);

        // BR-STK-04: alokasi keras menggantikan janji lunak, tidak menumpuk.
        $this->assertSame(
            0,
            StockReservation::query()->active()->forDocument('material_request', (int) $req->id)->count(),
        );
        $this->assertSame(
            1,
            StockReservation::query()->active()->forDocument('pick_task', (int) $pck->id)->count(),
        );
        $this->assertSame(
            80.0,
            app(StockLedger::class)->availableQty((int) $this->item->id, (int) $this->gudang->id),
            'Stok tersedia berkurang sekali, bukan dua kali.',
        );

        // Katalog Status §2.1: REQ bergeser ke Diproses.
        $this->assertSame('in_progress', $req->refresh()->status->value);
    }

    #[Test]
    public function tc_pck_02_pck_dimulai(): void
    {
        $pck = app(ProcessPickTask::class)->start($this->pckSiap(), $this->makeUser('warehouse_staff'));

        $this->assertSame(PickTaskStatus::InProgress, $pck->status);
        $this->assertNotNull($pck->started_at);
    }

    #[Test]
    public function tc_pck_03_bin_beku_menolak_picking(): void
    {
        $pck = $this->pckSiap();

        app(ChangeBinStatus::class)->freeze($this->bin, 'STOCK_COUNT', null, $this->makeUser('warehouse_head'));

        try {
            app(ProcessPickTask::class)->start($pck, $this->makeUser('warehouse_staff'));
            $this->fail('Picking dari bin beku seharusnya ditolak.');
        } catch (ShipmentRuleException $e) {
            $this->assertSame('BR-OPN-02', $e->rule);
        }
    }

    #[Test]
    public function tc_pck_04_penyelesaian_memindahkan_stok_ke_loading_area(): void
    {
        $staf = $this->makeUser('warehouse_staff');
        $pck = app(ProcessPickTask::class)->start($this->pckSiap(), $staf);

        $baris = $pck->lines()->first();
        app(ProcessPickTask::class)->recordLine($baris, 20, null, null, null, $staf);

        $pck = app(ProcessPickTask::class)->complete($pck->refresh(), $staf);

        $this->assertSame(PickTaskStatus::Completed, $pck->status);

        $loading = app(WarehouseBins::class)->loadingArea($this->gudang);

        $this->assertSame(
            20.0,
            (float) StockBalance::query()
                ->where('item_id', $this->item->id)
                ->where('bin_id', $loading->id)
                ->value('qty_base'),
        );
        $this->assertSame(
            80.0,
            (float) StockBalance::query()
                ->where('item_id', $this->item->id)
                ->where('bin_id', $this->bin->id)
                ->value('qty_base'),
        );

        // Alokasi berubah menjadi pergerakan; tidak lagi mengurangi tersedia.
        $this->assertSame(
            0,
            StockReservation::query()->active()->forDocument('pick_task', (int) $pck->id)->count(),
        );
    }

    #[Test]
    public function tc_pck_05_short_pick_tanpa_alasan_ditolak(): void
    {
        $staf = $this->makeUser('warehouse_staff');
        $pck = app(ProcessPickTask::class)->start($this->pckSiap(), $staf);

        app(ProcessPickTask::class)->recordLine($pck->lines()->first(), 15, null, null, null, $staf);

        try {
            app(ProcessPickTask::class)->complete($pck->refresh(), $staf);
            $this->fail('Short pick tanpa alasan seharusnya ditolak.');
        } catch (ShipmentRuleException $e) {
            $this->assertSame('BR-SJ-02', $e->rule);
        }
    }

    #[Test]
    public function tc_pck_06_short_pick_menandai_bin_dan_membuat_backorder(): void
    {
        $staf = $this->makeUser('warehouse_staff');
        $req = $this->reqDisetujui();
        $pck = app(CreatePickTask::class)->handle($req, $this->makeUser('warehouse_head'))[0];
        $pck = app(ProcessPickTask::class)->start($pck, $staf);

        app(ProcessPickTask::class)->recordLine($pck->lines()->first(), 15, null, null, $this->alasanId(), $staf);

        $pck = app(ProcessPickTask::class)->complete($pck->refresh(), $staf);

        $this->assertSame(PickTaskStatus::Completed, $pck->status);
        $this->assertTrue($pck->hasShortPick());

        // BR-SJ-02: bin ditandai perlu dihitung dan sisanya jadi backorder.
        $this->assertTrue((bool) $this->bin->refresh()->count_flag);
        $this->assertSame(5.0, (float) $req->refresh()->openLines()->first()->qty_backorder);

        $loading = app(WarehouseBins::class)->loadingArea($this->gudang);

        $this->assertSame(
            15.0,
            (float) StockBalance::query()
                ->where('item_id', $this->item->id)
                ->where('bin_id', $loading->id)
                ->value('qty_base'),
            'Hanya yang benar-benar diambil yang berpindah.',
        );
    }

    #[Test]
    public function tc_pck_06b_ambil_melebihi_alokasi_ditolak(): void
    {
        $staf = $this->makeUser('warehouse_staff');
        $pck = app(ProcessPickTask::class)->start($this->pckSiap(), $staf);

        try {
            app(ProcessPickTask::class)->recordLine($pck->lines()->first(), 25, null, null, null, $staf);
            $this->fail('Mengambil melebihi alokasi seharusnya ditolak.');
        } catch (ShipmentRuleException $e) {
            $this->assertSame('BR-SJ-02', $e->rule);
        }
    }

    #[Test]
    public function tc_pck_06c_ganti_bin_tanpa_alasan_ditolak(): void
    {
        $staf = $this->makeUser('warehouse_staff');
        $lain = Bin::create([
            'warehouse_id' => $this->gudang->id,
            'code' => 'CKG-A-R01-L1-B02',
            'bin_type' => BinType::Storage,
        ]);

        $pck = app(ProcessPickTask::class)->start($this->pckSiap(), $staf);

        try {
            app(ProcessPickTask::class)->recordLine($pck->lines()->first(), 20, (int) $lain->id, null, null, $staf);
            $this->fail('Mengganti bin tanpa alasan seharusnya ditolak.');
        } catch (ShipmentRuleException $e) {
            $this->assertSame('BR-SJ-01', $e->rule);
        }

        $baris = app(ProcessPickTask::class)->recordLine(
            $pck->lines()->first(),
            20,
            (int) $lain->id,
            'Bin saran kosong, barang dipindah kemarin',
            null,
            $staf,
        );

        $this->assertSame((int) $lain->id, (int) $baris->bin_id);
        $this->assertTrue($baris->binWasOverridden());
    }

    #[Test]
    public function tc_pck_07_pck_selesai_tidak_bisa_dibatalkan(): void
    {
        $staf = $this->makeUser('warehouse_staff');
        $pck = app(ProcessPickTask::class)->start($this->pckSiap(), $staf);

        app(ProcessPickTask::class)->recordLine($pck->lines()->first(), 20, null, null, null, $staf);
        $pck = app(ProcessPickTask::class)->complete($pck->refresh(), $staf);

        try {
            app(ProcessPickTask::class)->cancel($pck, $this->alasanId(), $this->makeUser('warehouse_head'));
            $this->fail('PCK yang sudah selesai seharusnya tidak bisa dibatalkan.');
        } catch (ShipmentRuleException $e) {
            $this->assertSame('BR-SJ-01', $e->rule);
        }
    }

    #[Test]
    public function tc_pck_08_pembatalan_melepas_alokasi_tanpa_memindahkan_stok(): void
    {
        $pck = app(ProcessPickTask::class)->start($this->pckSiap(), $this->makeUser('warehouse_staff'));

        $pck = app(ProcessPickTask::class)->cancel($pck, $this->alasanId(), $this->makeUser('warehouse_head'));

        $this->assertSame(PickTaskStatus::Cancelled, $pck->status);
        $this->assertSame(
            0,
            StockReservation::query()->active()->forDocument('pick_task', (int) $pck->id)->count(),
        );
        $this->assertSame(
            100.0,
            (float) StockBalance::query()
                ->where('item_id', $this->item->id)
                ->where('bin_id', $this->bin->id)
                ->value('qty_base'),
            'Stok tidak pernah bergerak, jadi tidak ada yang perlu dikembalikan.',
        );
        $this->assertSame(
            100.0,
            app(StockLedger::class)->availableQty((int) $this->item->id, (int) $this->gudang->id),
        );
    }

    #[Test]
    public function tc_pck_08b_pembatalan_tanpa_alasan_ditolak(): void
    {
        $pck = $this->pckSiap();

        try {
            app(ProcessPickTask::class)->cancel($pck, null, $this->makeUser('warehouse_head'));
            $this->fail('Pembatalan tanpa alasan seharusnya ditolak.');
        } catch (ShipmentRuleException $e) {
            $this->assertSame('BR-GEN-11', $e->rule);
        }
    }
}

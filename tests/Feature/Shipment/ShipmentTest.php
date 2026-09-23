<?php

declare(strict_types=1);

namespace Tests\Feature\Shipment;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Carrier;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Models\Vehicle;
use App\Domain\Request\Actions\ApproveRequest;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Shipment\Actions\ConfirmDelivery;
use App\Domain\Shipment\Actions\CreatePickTask;
use App\Domain\Shipment\Actions\CreateShipment;
use App\Domain\Shipment\Actions\ProcessPickTask;
use App\Domain\Shipment\Actions\ShipShipment;
use App\Domain\Shipment\Enums\DiscrepancyStatus;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Support\WarehouseBins;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockEvent;
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
 * TC-SJ-01 s.d. TC-SJ-12 — surat jalan dan bukti terima
 * (BR-SJ-04 s.d. BR-SJ-07, BR-SJ-09, BR-SJ-10).
 */
class ShipmentTest extends TenantTestCase
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
        return (int) ReasonCode::query()->value('id');
    }

    /** PCK yang sudah selesai; barangnya sudah di Loading Area. */
    private function pckSelesai(float $qty = 20, ?Project $proyek = null): PickTask
    {
        $pemohon = $this->makeUser('internal_requester');

        $req = app(SaveRequest::class)->handle(
            null,
            [
                'project_id' => ($proyek ?? $this->proyek)->id,
                'required_date' => now()->addDays(3)->toDateString(),
            ],
            [['item_id' => $this->item->id, 'qty_base' => $qty]],
            $pemohon,
        );

        $req->openLines()->first()->forceFill([
            'source_warehouse_id' => $this->gudang->id,
            'fulfillment_source' => 'stock',
        ])->save();

        $req = app(SubmitRequest::class)->handle($req->refresh(), $pemohon);
        $req = app(ApproveRequest::class)->handle($req, $this->makeUser('company_admin'));

        $staf = $this->makeUser('warehouse_staff');
        $pck = app(CreatePickTask::class)->handle($req, $this->makeUser('warehouse_head'))[0];
        $pck = app(ProcessPickTask::class)->start($pck, $staf);

        foreach ($pck->lines as $baris) {
            app(ProcessPickTask::class)->recordLine($baris, (float) $baris->qty_allocated, null, null, null, $staf);
        }

        return app(ProcessPickTask::class)->complete($pck->refresh(), $staf);
    }

    /** @param  array<string, mixed>  $extra */
    private function buatSj(array $pckIds, array $extra = []): Shipment
    {
        $kendaraan = Vehicle::create(['plate_no' => 'B'.random_int(1000, 9999).'XYZ']);

        return app(CreateShipment::class)->handle($pckIds, array_merge([
            'destination_type' => 'project_client',
            'destination_project_id' => $this->proyek->id,
            'shipment_method' => 'own_fleet',
            'vehicle_id' => $kendaraan->id,
            'driver_id' => $this->makeUser('driver')->id,
        ], $extra), $this->makeUser('warehouse_staff'));
    }

    #[Test]
    public function tc_sj_01_beberapa_pck_tujuan_sama_digabung(): void
    {
        $a = $this->pckSelesai(20);
        $b = $this->pckSelesai(15);

        $sj = $this->buatSj([$a->id, $b->id]);

        $this->assertSame(ShipmentStatus::Prepared, $sj->status);
        $this->assertMatchesRegularExpression('#^SJ/CKG/\d{4}/\d{4}$#', $sj->number);
        $this->assertSame(2, $sj->lines()->count(), 'BR-SJ-09: dua PCK dalam satu surat jalan.');
        $this->assertSame(35.0, (float) $sj->lines()->sum('qty_shipped'));
    }

    #[Test]
    public function tc_sj_02_pck_gudang_berbeda_tidak_bisa_digabung(): void
    {
        $bks = app(SaveWarehouse::class)->handle(null, [
            'code' => 'BKS',
            'name' => 'Gudang Cabang Bekasi',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);

        $a = $this->pckSelesai(10);

        // PCK kedua dipalsukan ke gudang lain: yang diuji penggabungannya.
        $b = $this->pckSelesai(10);
        $b->forceFill(['warehouse_id' => $bks->id])->save();

        try {
            $this->buatSj([$a->id, $b->id]);
            $this->fail('PCK dari gudang berbeda seharusnya tidak bisa digabung.');
        } catch (ShipmentRuleException $e) {
            $this->assertSame('BR-SJ-09', $e->rule);
        }
    }

    #[Test]
    public function tc_sj_03_kendaraan_sendiri_wajib_kendaraan_dan_driver(): void
    {
        $pck = $this->pckSelesai();

        try {
            app(CreateShipment::class)->handle([$pck->id], [
                'destination_type' => 'project_client',
                'destination_project_id' => $this->proyek->id,
                'shipment_method' => 'own_fleet',
            ], $this->makeUser('warehouse_staff'));
            $this->fail('Kendaraan sendiri tanpa kendaraan seharusnya ditolak.');
        } catch (ShipmentRuleException $e) {
            $this->assertSame('BR-SJ-07', $e->rule);
            $this->assertArrayHasKey('vehicle_id', $e->fieldErrors);
        }
    }

    #[Test]
    public function tc_sj_04_ekspedisi_wajib_nama_dan_resi(): void
    {
        $pck = $this->pckSelesai();
        $ekspedisi = Carrier::create(['name' => 'Ekspedisi Uji']);

        try {
            app(CreateShipment::class)->handle([$pck->id], [
                'destination_type' => 'project_client',
                'destination_project_id' => $this->proyek->id,
                'shipment_method' => 'carrier',
                'carrier_id' => $ekspedisi->id,
            ], $this->makeUser('warehouse_staff'));
            $this->fail('Ekspedisi tanpa nomor resi seharusnya ditolak.');
        } catch (ShipmentRuleException $e) {
            $this->assertSame('BR-SJ-07', $e->rule);
            $this->assertArrayHasKey('tracking_no', $e->fieldErrors);
        }

        $sj = app(CreateShipment::class)->handle([$pck->id], [
            'destination_type' => 'project_client',
            'destination_project_id' => $this->proyek->id,
            'shipment_method' => 'carrier',
            'carrier_id' => $ekspedisi->id,
            'tracking_no' => 'RESI-001',
        ], $this->makeUser('warehouse_staff'));

        $this->assertStringContainsString('RESI-001', $sj->carrierLabel());
    }

    #[Test]
    public function tc_sj_05_pengiriman_memindahkan_stok_ke_dalam_perjalanan(): void
    {
        $sj = $this->buatSj([$this->pckSelesai()->id]);

        $sj = app(ShipShipment::class)->handle($sj, 'Muat pukul 08.00', $this->makeUser('driver'));

        $this->assertSame(ShipmentStatus::Shipped, $sj->status);
        $this->assertNotNull($sj->shipped_at);

        $transit = app(WarehouseBins::class)->inTransit($this->gudang);
        $loading = app(WarehouseBins::class)->loadingArea($this->gudang);

        $this->assertSame(20.0, $this->saldo($transit));
        $this->assertSame(0.0, $this->saldo($loading));

        $this->assertTrue(
            StockEvent::query()->where('event_type', 'goods_shipped')->exists(),
            'BR-SJ-04: pengiriman melahirkan kejadian goods_shipped.',
        );
    }

    #[Test]
    public function tc_sj_06_sj_terkirim_tidak_bisa_dibatalkan(): void
    {
        $sj = app(ShipShipment::class)->handle($this->buatSj([$this->pckSelesai()->id]), null, $this->makeUser('driver'));

        try {
            app(ShipShipment::class)->cancel($sj, $this->alasanId(), $this->makeUser('warehouse_head'));
            $this->fail('SJ yang sudah berangkat seharusnya tidak bisa dibatalkan.');
        } catch (ShipmentRuleException $e) {
            $this->assertSame('BR-SJ-06', $e->rule);
        }
    }

    #[Test]
    public function tc_sj_06b_sj_disiapkan_bisa_dibatalkan_dengan_alasan(): void
    {
        $sj = $this->buatSj([$this->pckSelesai()->id]);

        $sj = app(ShipShipment::class)->cancel($sj, $this->alasanId(), $this->makeUser('warehouse_head'));

        $this->assertSame(ShipmentStatus::Cancelled, $sj->status);
        $this->assertSame(
            20.0,
            $this->saldo(app(WarehouseBins::class)->loadingArea($this->gudang)),
            'Barang tetap di Loading Area; PCK tetap selesai.',
        );
    }

    #[Test]
    public function tc_sj_07_semua_baik_ke_klien_keluar_dari_ledger(): void
    {
        $sj = app(ShipShipment::class)->handle($this->buatSj([$this->pckSelesai()->id]), null, $this->makeUser('driver'));

        $bukti = app(ConfirmDelivery::class)->handle(
            $sj,
            ['received_by_name' => 'Pak Budi', 'channel' => 'driver_pwa'],
            [['shipment_line_id' => $sj->lines()->first()->id, 'qty_good' => 20]],
            $this->makeUser('driver'),
        );

        $this->assertSame(ShipmentStatus::Delivered, $sj->refresh()->status);
        $this->assertSame('Pak Budi', $bukti->received_by_name);
        $this->assertNotNull($bukti->confirm_deadline_at);

        $this->assertSame(
            0.0,
            $this->saldo(app(WarehouseBins::class)->inTransit($this->gudang)),
            'BR-SJ-04: jual putus mengeluarkan barang dari ledger.',
        );
        $this->assertTrue(StockEvent::query()->where('event_type', 'goods_delivered')->exists());
    }

    #[Test]
    public function tc_sj_08_tujuan_gudang_tetap_dalam_perjalanan(): void
    {
        $tujuan = app(SaveWarehouse::class)->handle(null, [
            'code' => 'BKS',
            'name' => 'Gudang Cabang Bekasi',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);

        $sj = $this->buatSj([$this->pckSelesai()->id], [
            'destination_type' => 'warehouse',
            'destination_project_id' => null,
            'destination_warehouse_id' => $tujuan->id,
        ]);

        $sj = app(ShipShipment::class)->handle($sj, null, $this->makeUser('driver'));

        app(ConfirmDelivery::class)->handle(
            $sj,
            ['received_by_name' => 'Kepala Gudang BKS'],
            [['shipment_line_id' => $sj->lines()->first()->id, 'qty_good' => 20]],
            $this->makeUser('driver'),
        );

        $this->assertSame(ShipmentStatus::Delivered, $sj->refresh()->status);
        $this->assertSame(
            20.0,
            $this->saldo(app(WarehouseBins::class)->inTransit($this->gudang)),
            'BR-SJ-04: barang tetap milik gudang asal sampai GRN tujuan dibuat.',
        );
        $this->assertTrue(StockEvent::query()->where('event_type', 'stock_transferred')->exists());
    }

    #[Test]
    public function tc_sj_09_jumlah_bukti_terima_harus_sama_dengan_dikirim(): void
    {
        $sj = app(ShipShipment::class)->handle($this->buatSj([$this->pckSelesai()->id]), null, $this->makeUser('driver'));

        try {
            app(ConfirmDelivery::class)->handle(
                $sj,
                ['received_by_name' => 'Pak Budi'],
                [['shipment_line_id' => $sj->lines()->first()->id, 'qty_good' => 18]],
                $this->makeUser('driver'),
            );
            $this->fail('Jumlah yang tidak genap seharusnya ditolak.');
        } catch (ShipmentRuleException $e) {
            $this->assertSame('BR-SJ-05', $e->rule);
            $this->assertStringContainsString('seharusnya 20', $e->getMessage());
        }
    }

    #[Test]
    public function tc_sj_10_kerusakan_tanpa_foto_ditolak(): void
    {
        $sj = app(ShipShipment::class)->handle($this->buatSj([$this->pckSelesai()->id]), null, $this->makeUser('driver'));

        try {
            app(ConfirmDelivery::class)->handle(
                $sj,
                ['received_by_name' => 'Pak Budi'],
                [['shipment_line_id' => $sj->lines()->first()->id, 'qty_good' => 18, 'qty_damaged' => 2]],
                $this->makeUser('driver'),
            );
            $this->fail('Kerusakan tanpa foto seharusnya ditolak.');
        } catch (ShipmentRuleException $e) {
            $this->assertSame('BR-SJ-05', $e->rule);
            $this->assertArrayHasKey('damage_photo_path', $e->fieldErrors);
        }
    }

    #[Test]
    public function tc_sj_11_selisih_membuka_dsc_dan_menurunkan_status(): void
    {
        $sj = app(ShipShipment::class)->handle($this->buatSj([$this->pckSelesai()->id]), null, $this->makeUser('driver'));

        app(ConfirmDelivery::class)->handle(
            $sj,
            ['received_by_name' => 'Pak Budi'],
            [[
                'shipment_line_id' => $sj->lines()->first()->id,
                'qty_good' => 15,
                'qty_damaged' => 3,
                'qty_missing' => 2,
                'damage_photo_path' => 'bukti/rusak.jpg',
            ]],
            $this->makeUser('driver'),
        );

        $sj->refresh();

        $this->assertSame(ShipmentStatus::PartiallyDelivered, $sj->status);

        $dsc = $sj->discrepancies()->first();

        $this->assertNotNull($dsc);
        $this->assertSame(DiscrepancyStatus::Open, $dsc->status);
        $this->assertSame(2, $dsc->lines()->count(), 'Rusak dan kurang masing-masing satu baris.');
        $this->assertMatchesRegularExpression('#^DSC/CKG/\d{4}/\d{4}$#', $dsc->number);
    }

    #[Test]
    public function tc_sj_12_rusak_dan_kurang_tetap_di_dalam_perjalanan(): void
    {
        $sj = app(ShipShipment::class)->handle($this->buatSj([$this->pckSelesai()->id]), null, $this->makeUser('driver'));

        app(ConfirmDelivery::class)->handle(
            $sj,
            ['received_by_name' => 'Pak Budi'],
            [[
                'shipment_line_id' => $sj->lines()->first()->id,
                'qty_good' => 15,
                'qty_damaged' => 3,
                'qty_missing' => 2,
                'damage_photo_path' => 'bukti/rusak.jpg',
            ]],
            $this->makeUser('driver'),
        );

        $transit = app(WarehouseBins::class)->inTransit($this->gudang);

        // BR-SJ-10: rusak berkondisi `damaged`, kurang tetap `available`.
        $this->assertSame(3.0, $this->saldo($transit, StockStatus::Damaged));
        $this->assertSame(2.0, $this->saldo($transit, StockStatus::Available));
    }

    private function saldo(Bin $bin, StockStatus $status = StockStatus::Available): float
    {
        return (float) StockBalance::query()
            ->where('item_id', $this->item->id)
            ->where('bin_id', $bin->id)
            ->where('stock_status', $status->value)
            ->sum('qty_base');
    }
}

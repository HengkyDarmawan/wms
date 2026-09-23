<?php

declare(strict_types=1);

namespace Tests\Feature\Shipment;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Models\Vehicle;
use App\Domain\Request\Actions\ApproveRequest;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Request\Enums\RequestLineStatus;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Shipment\Actions\ConfirmDelivery;
use App\Domain\Shipment\Actions\CreatePickTask;
use App\Domain\Shipment\Actions\CreateShipment;
use App\Domain\Shipment\Actions\IssueDeliveryToken;
use App\Domain\Shipment\Actions\ProcessPickTask;
use App\Domain\Shipment\Actions\ResolveDiscrepancy;
use App\Domain\Shipment\Actions\ShipShipment;
use App\Domain\Shipment\Enums\DiscrepancyStatus;
use App\Domain\Shipment\Enums\DiscrepancyType;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Shipment\Models\DeliveryDiscrepancy;
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
 * TC-DSC-01 s.d. TC-DSC-05 — penyelesaian selisih pengiriman (BR-SJ-10),
 * ditambah tautan bukti terima bertoken (BR-SJ-05, A-41).
 */
class DiscrepancyTest extends TenantTestCase
{
    private Project $proyek;

    private Warehouse $gudang;

    private Bin $bin;

    private Item $item;

    private MaterialRequest $req;

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

    /**
     * Rantai lengkap sampai DSC terbuka: 20 dikirim, 15 baik, 3 rusak, 2 kurang.
     */
    private function dscTerbuka(): DeliveryDiscrepancy
    {
        $pemohon = $this->makeUser('internal_requester');

        $req = app(SaveRequest::class)->handle(
            null,
            ['project_id' => $this->proyek->id, 'required_date' => now()->addDays(3)->toDateString()],
            [['item_id' => $this->item->id, 'qty_base' => 20]],
            $pemohon,
        );

        $req->openLines()->first()->forceFill([
            'source_warehouse_id' => $this->gudang->id,
            'fulfillment_source' => 'stock',
        ])->save();

        $req = app(SubmitRequest::class)->handle($req->refresh(), $pemohon);
        $this->req = app(ApproveRequest::class)->handle($req, $this->makeUser('company_admin'));

        $staf = $this->makeUser('warehouse_staff');
        $pck = app(CreatePickTask::class)->handle($this->req, $this->makeUser('warehouse_head'))[0];
        $pck = app(ProcessPickTask::class)->start($pck, $staf);

        foreach ($pck->lines as $baris) {
            app(ProcessPickTask::class)->recordLine($baris, (float) $baris->qty_allocated, null, null, null, $staf);
        }

        $pck = app(ProcessPickTask::class)->complete($pck->refresh(), $staf);

        $sj = app(CreateShipment::class)->handle([$pck->id], [
            'destination_type' => 'project_client',
            'destination_project_id' => $this->proyek->id,
            'shipment_method' => 'own_fleet',
            'vehicle_id' => Vehicle::create(['plate_no' => 'B1234XYZ'])->id,
            'driver_id' => $this->makeUser('driver')->id,
        ], $staf);

        $sj = app(ShipShipment::class)->handle($sj, null, $this->makeUser('driver'));

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

        return $sj->refresh()->discrepancies()->with('lines')->first();
    }

    /** @return array<int, array<string, mixed>> */
    private function keputusan(DeliveryDiscrepancy $dsc, string $disposisi, array $extra = []): array
    {
        return $dsc->lines->map(fn ($l) => array_merge([
            'line_id' => $l->id,
            'disposition' => $disposisi,
            'client_decision' => 'still_needed',
        ], $extra))->all();
    }

    #[Test]
    public function tc_dsc_01_disposisi_disesuaikan_mengeluarkan_stok(): void
    {
        $dsc = $this->dscTerbuka();
        $transit = app(WarehouseBins::class)->inTransit($this->gudang);

        $this->assertSame(3.0, $this->saldo($transit, StockStatus::Damaged));
        $this->assertSame(2.0, $this->saldo($transit, StockStatus::Available));

        $dsc = app(ResolveDiscrepancy::class)->handle(
            $dsc,
            $this->keputusan($dsc, 'adjusted', ['reason_code_id' => $this->alasanId()]),
            'Hilang di perjalanan',
            $this->makeUser('warehouse_head'),
        );

        $this->assertSame(DiscrepancyStatus::Resolved, $dsc->status);
        $this->assertNotNull($dsc->resolved_at);
        $this->assertSame(0.0, $this->saldo($transit, StockStatus::Damaged));
        $this->assertSame(0.0, $this->saldo($transit, StockStatus::Available));
        $this->assertTrue(StockEvent::query()->where('event_type', 'delivery_discrepancy')->exists());
    }

    #[Test]
    public function tc_dsc_02_disposisi_kembali_ke_gudang_memindahkan_ke_bin_retur(): void
    {
        $dsc = $this->dscTerbuka();

        app(ResolveDiscrepancy::class)->handle(
            $dsc,
            $this->keputusan($dsc, 'returned_to_warehouse'),
            null,
            $this->makeUser('warehouse_head'),
        );

        $retur = app(WarehouseBins::class)->returnBin($this->gudang);
        $transit = app(WarehouseBins::class)->inTransit($this->gudang);

        // Kondisi ikut pindah apa adanya: yang rusak tetap rusak di bin Retur.
        $this->assertSame(3.0, $this->saldo($retur, StockStatus::Damaged));
        $this->assertSame(2.0, $this->saldo($retur, StockStatus::Available));
        $this->assertSame(0.0, $this->saldo($transit, StockStatus::Damaged));
        $this->assertSame(0.0, $this->saldo($transit, StockStatus::Available));
    }

    #[Test]
    public function tc_dsc_03_disposisi_kirim_pengganti_tidak_menyentuh_stok(): void
    {
        $dsc = $this->dscTerbuka();
        $transit = app(WarehouseBins::class)->inTransit($this->gudang);

        app(ResolveDiscrepancy::class)->handle(
            $dsc,
            $this->keputusan($dsc, 'reship'),
            null,
            $this->makeUser('warehouse_head'),
        );

        // BR-SJ-10: yang dikirim ulang adalah barang baru; yang di perjalanan
        // menunggu keputusan berikutnya.
        $this->assertSame(3.0, $this->saldo($transit, StockStatus::Damaged));
        $this->assertSame(2.0, $this->saldo($transit, StockStatus::Available));

        // Kelima unit kembali menjadi backorder baris REQ.
        $this->assertSame(5.0, (float) $this->req->refresh()->openLines()->first()->qty_backorder);
    }

    #[Test]
    public function tc_dsc_04_klien_tidak_membutuhkan_menutup_baris_req(): void
    {
        $dsc = $this->dscTerbuka();

        app(ResolveDiscrepancy::class)->handle(
            $dsc,
            $this->keputusan($dsc, 'adjusted', [
                'reason_code_id' => $this->alasanId(),
                'client_decision' => 'not_needed',
            ]),
            null,
            $this->makeUser('warehouse_head'),
        );

        $baris = $this->req->refresh()->lines()->first();

        $this->assertSame(RequestLineStatus::Closed, $baris->status);
        $this->assertSame(0.0, (float) $baris->qty_backorder);
    }

    #[Test]
    public function tc_dsc_05_disposisi_tanpa_kelengkapan_ditolak(): void
    {
        $dsc = $this->dscTerbuka();
        $kepala = $this->makeUser('warehouse_head');

        // `adjusted` mengeluarkan barang dari pembukuan: alasan wajib.
        try {
            app(ResolveDiscrepancy::class)->handle($dsc, $this->keputusan($dsc, 'adjusted'), null, $kepala);
            $this->fail('Disposisi disesuaikan tanpa alasan seharusnya ditolak.');
        } catch (ShipmentRuleException $e) {
            $this->assertSame('BR-GEN-11', $e->rule);
        }

        // `claimed` menuntut nomor klaim.
        try {
            app(ResolveDiscrepancy::class)->handle($dsc, $this->keputusan($dsc, 'claimed'), null, $kepala);
            $this->fail('Klaim tanpa nomor klaim seharusnya ditolak.');
        } catch (ShipmentRuleException $e) {
            $this->assertSame('BR-SJ-10', $e->rule);
        }

        $this->assertSame(DiscrepancyStatus::Open, $dsc->refresh()->status);
    }

    #[Test]
    public function tc_dsc_05b_baris_yang_belum_diputuskan_menahan_penyelesaian(): void
    {
        $dsc = $this->dscTerbuka();

        $satu = $dsc->lines->first();

        try {
            app(ResolveDiscrepancy::class)->handle(
                $dsc,
                [['line_id' => $satu->id, 'disposition' => 'reship']],
                null,
                $this->makeUser('warehouse_head'),
            );
            $this->fail('Baris yang belum diputuskan seharusnya menahan penyelesaian.');
        } catch (ShipmentRuleException $e) {
            $this->assertSame('BR-SJ-10', $e->rule);
            $this->assertStringContainsString('1 baris belum diputuskan', $e->getMessage());
        }
    }

    #[Test]
    public function tc_dsc_05c_dsc_yang_sudah_selesai_tidak_bisa_diulang(): void
    {
        $dsc = $this->dscTerbuka();
        $kepala = $this->makeUser('warehouse_head');

        $dsc = app(ResolveDiscrepancy::class)->handle($dsc, $this->keputusan($dsc, 'reship'), null, $kepala);

        try {
            app(ResolveDiscrepancy::class)->handle($dsc, $this->keputusan($dsc, 'reship'), null, $kepala);
            $this->fail('DSC yang sudah selesai seharusnya tidak bisa diselesaikan lagi.');
        } catch (ShipmentRuleException $e) {
            $this->assertSame('BR-SJ-10', $e->rule);
        }
    }

    #[Test]
    public function tc_sj_05b_tautan_bukti_terima_bertoken_dan_otp(): void
    {
        $dsc = $this->dscTerbuka();
        $sj = $dsc->shipment;

        // SJ ini sudah punya bukti terima, jadi tautannya ditolak.
        try {
            app(IssueDeliveryToken::class)->handle($sj, '08123456789', $this->makeUser('warehouse_staff'));
            $this->fail('Tautan untuk SJ yang sudah bertanda terima seharusnya ditolak.');
        } catch (ShipmentRuleException $e) {
            $this->assertSame('BR-SJ-05', $e->rule);
        }
    }

    #[Test]
    public function tc_sj_05c_otp_salah_menambah_percobaan_dan_terkunci(): void
    {
        $pemohon = $this->makeUser('internal_requester');

        $req = app(SaveRequest::class)->handle(
            null,
            ['project_id' => $this->proyek->id, 'required_date' => now()->addDays(3)->toDateString()],
            [['item_id' => $this->item->id, 'qty_base' => 5]],
            $pemohon,
        );

        $req->openLines()->first()->forceFill([
            'source_warehouse_id' => $this->gudang->id,
            'fulfillment_source' => 'stock',
        ])->save();

        $req = app(ApproveRequest::class)->handle(
            app(SubmitRequest::class)->handle($req->refresh(), $pemohon),
            $this->makeUser('company_admin'),
        );

        $staf = $this->makeUser('warehouse_staff');
        $pck = app(CreatePickTask::class)->handle($req, $this->makeUser('warehouse_head'))[0];
        $pck = app(ProcessPickTask::class)->start($pck, $staf);

        foreach ($pck->lines as $baris) {
            app(ProcessPickTask::class)->recordLine($baris, (float) $baris->qty_allocated, null, null, null, $staf);
        }

        $pck = app(ProcessPickTask::class)->complete($pck->refresh(), $staf);

        $sj = app(CreateShipment::class)->handle([$pck->id], [
            'destination_type' => 'project_client',
            'destination_project_id' => $this->proyek->id,
            'shipment_method' => 'own_fleet',
            'vehicle_id' => Vehicle::create(['plate_no' => 'B9999ZZZ'])->id,
            'driver_id' => $this->makeUser('driver')->id,
        ], $staf);

        $sj = app(ShipShipment::class)->handle($sj, null, $this->makeUser('driver'));

        $hasil = app(IssueDeliveryToken::class)->handle($sj, '08123456789', $staf);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $hasil['otp']);
        $this->assertTrue($hasil['token']->isUsable());

        // OTP benar diterima.
        $token = app(IssueDeliveryToken::class)->verify($hasil['token']->token, $hasil['otp']);
        $this->assertSame((int) $hasil['token']->id, (int) $token->id);

        // OTP salah menambah percobaan; setelah batas, tautannya terkunci.
        for ($i = 0; $i < 5; $i++) {
            try {
                app(IssueDeliveryToken::class)->verify($hasil['token']->token, '000000');
            } catch (ShipmentRuleException $e) {
                $this->assertSame('BR-SJ-05', $e->rule);
            }
        }

        try {
            app(IssueDeliveryToken::class)->verify($hasil['token']->token, $hasil['otp']);
            $this->fail('Tautan seharusnya terkunci setelah lima percobaan gagal.');
        } catch (ShipmentRuleException $e) {
            $this->assertSame('NFR-04', $e->rule);
        }
    }

    private function saldo(Bin $bin, StockStatus $status): float
    {
        return (float) StockBalance::query()
            ->where('item_id', $this->item->id)
            ->where('bin_id', $bin->id)
            ->where('stock_status', $status->value)
            ->sum('qty_base');
    }
}

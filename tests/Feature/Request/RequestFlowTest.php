<?php

declare(strict_types=1);

namespace Tests\Feature\Request;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\ProjectStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Uom;
use App\Domain\Request\Actions\ApproveRequest;
use App\Domain\Request\Actions\CancelRequest;
use App\Domain\Request\Actions\CloseRequestShort;
use App\Domain\Request\Actions\ReviewRequest;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SplitRequestLine;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Enums\RequestLineStatus;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Stock\Enums\ReservationStatus;
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
 * TC-REQ-01 s.d. TC-REQ-16 — alur REQ dari dibuat sampai ditutup
 * (BR-REQ-01 s.d. BR-REQ-09, BR-PRJ-01, BR-GEN-11).
 */
class RequestFlowTest extends TenantTestCase
{
    private Project $proyek;

    private Warehouse $gudang;

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

        $this->item = Item::create([
            'code' => 'BAUT-M12',
            'name' => 'Baut M12',
            'status' => ItemStatus::Active,
            'tracking_mode' => TrackingMode::None,
            'base_uom_id' => Uom::query()->where('code', 'PCS')->value('id'),
        ]);

        // Saldo awal supaya reservasi lunak punya sesuatu untuk dijanjikan.
        $bin = Bin::create([
            'warehouse_id' => $this->gudang->id,
            'code' => 'CKG-A-R01-L1-B01',
            'bin_type' => BinType::Storage,
        ]);

        app(StockLedger::class)->post(new MovementRequest(
            item: $this->item,
            qtyBase: 100,
            toBinId: $bin->id,
        ));
    }

    /** @param  array<int, array<string, mixed>>  $lines */
    private function buatReq(array $lines = [], ?Project $proyek = null, ?\App\Domain\Access\Models\User $pemohon = null): MaterialRequest
    {
        $pemohon ??= $this->makeUser('internal_requester');

        return app(SaveRequest::class)->handle(
            null,
            [
                'project_id' => ($proyek ?? $this->proyek)->id,
                'required_date' => now()->addDays(3)->toDateString(),
            ],
            $lines === [] ? [['item_id' => $this->item->id, 'qty_base' => 10]] : $lines,
            $pemohon,
        );
    }

    private function alasanId(string $code = 'NOT_NEEDED'): int
    {
        $id = ReasonCode::query()->where('code', $code)->value('id');

        if ($id === null) {
            $id = ReasonCode::query()->value('id');
        }

        $this->assertNotNull($id, 'Seeder referensi harus menyediakan kode alasan.');

        return (int) $id;
    }

    #[Test]
    public function tc_req_01_req_dibuat_dengan_nomor(): void
    {
        $req = $this->buatReq();

        $this->assertSame(MaterialRequestStatus::Draft, $req->status);
        $this->assertMatchesRegularExpression(
            '#^REQ/'.preg_quote($this->proyek->code, '#').'/\d{4}/\d{4}$#',
            $req->number,
            'BR-GEN-06: nomor REQ memakai kode proyek sebagai segmen.',
        );
        $this->assertSame(1, $req->openLines()->count());
    }

    #[Test]
    public function tc_req_02_proyek_ditutup_menolak_req_baru(): void
    {
        $ditutup = $this->makeProject(['status' => ProjectStatus::Closed]);

        try {
            $this->buatReq([], $ditutup);
            $this->fail('REQ untuk proyek tertutup seharusnya ditolak.');
        } catch (RequestRuleException $e) {
            $this->assertSame('BR-PRJ-01', $e->rule);
        }
    }

    #[Test]
    public function tc_req_03_req_tanpa_baris_tidak_bisa_diajukan(): void
    {
        $pemohon = $this->makeUser('internal_requester');
        $req = $this->buatReq([], null, $pemohon);

        // Seluruh baris dilepas dari form; yang tersimpan dibatalkan (P-03).
        app(SaveRequest::class)->handle($req, [], [], $pemohon);

        try {
            app(SubmitRequest::class)->handle($req->refresh(), $pemohon);
            $this->fail('REQ tanpa baris seharusnya ditolak.');
        } catch (RequestRuleException $e) {
            $this->assertSame('BR-REQ-01', $e->rule);
        }
    }

    #[Test]
    public function tc_req_04_req_klien_masuk_ditinjau(): void
    {
        $klien = $this->makeUser('client_user', \App\Domain\Access\Enums\ScopeType::Project, $this->proyek->id, [
            'client_id' => $this->proyek->client_id,
        ]);

        $req = $this->buatReq([], null, $klien);
        $req = app(SubmitRequest::class)->handle($req, $klien);

        $this->assertSame(MaterialRequestStatus::UnderReview, $req->status);
        $this->assertTrue($req->isFromClient());
    }

    #[Test]
    public function tc_req_05_req_internal_lengkap_langsung_menunggu_approval(): void
    {
        $pemohon = $this->makeUser('internal_requester');
        $req = $this->buatReq([], null, $pemohon);

        $baris = $req->openLines()->first();
        $baris->forceFill([
            'source_warehouse_id' => $this->gudang->id,
            'fulfillment_source' => 'stock',
        ])->save();

        $req = app(SubmitRequest::class)->handle($req->refresh(), $pemohon);

        $this->assertSame(MaterialRequestStatus::PendingApproval, $req->status);
    }

    #[Test]
    public function tc_req_06_req_internal_tanpa_sumber_tertahan_ditinjau(): void
    {
        $pemohon = $this->makeUser('internal_requester');
        $req = app(SubmitRequest::class)->handle($this->buatReq([], null, $pemohon), $pemohon);

        $this->assertSame(
            MaterialRequestStatus::UnderReview,
            $req->status,
            'BR-REQ-04: baris tanpa gudang sumber ditinjau staf dulu.',
        );
    }

    #[Test]
    public function tc_req_07_baris_non_katalog_menahan_kenaikan_ke_approval(): void
    {
        $pemohon = $this->makeUser('internal_requester');
        $req = $this->buatReq([['non_catalog_text' => 'Baut ukuran besar', 'qty_base' => 5]], null, $pemohon);
        $req = app(SubmitRequest::class)->handle($req, $pemohon);

        $staf = $this->makeUser('warehouse_staff');

        try {
            app(ReviewRequest::class)->submitToApproval($req, $staf);
            $this->fail('Baris non-katalog yang belum dipetakan seharusnya menahan REQ.');
        } catch (RequestRuleException $e) {
            $this->assertSame('BR-REQ-03', $e->rule);
        }
    }

    #[Test]
    public function tc_req_08_baris_non_katalog_dipetakan_ke_item_sementara(): void
    {
        $pemohon = $this->makeUser('internal_requester');
        $req = $this->buatReq([['non_catalog_text' => 'Baut ukuran besar', 'qty_base' => 5]], null, $pemohon);
        $req = app(SubmitRequest::class)->handle($req, $pemohon);

        $staf = $this->makeUser('warehouse_staff');
        $baris = $req->openLines()->first();

        $baris = app(ReviewRequest::class)->mapToProvisionalItem(
            $baris,
            'BAUT-BESAR',
            'Baut ukuran besar',
            (int) Uom::query()->where('code', 'PCS')->value('id'),
            $staf,
        );

        $this->assertTrue($baris->isMapped());
        $this->assertSame(
            ItemStatus::Provisional,
            Item::query()->findOrFail($baris->item_id)->status,
            'BR-REQ-03: item baru lahir berstatus sementara.',
        );
    }

    #[Test]
    public function tc_req_09_pinjam_hanya_untuk_item_berserial(): void
    {
        try {
            $this->buatReq([[
                'item_id' => $this->item->id,
                'qty_base' => 2,
                'line_ownership' => 'loan',
            ]]);
            $this->fail('Baris pinjam untuk item tanpa serial seharusnya ditolak.');
        } catch (RequestRuleException $e) {
            $this->assertSame('BR-REQ-06', $e->rule);
        }
    }

    #[Test]
    public function tc_req_10_baris_dipecah_antar_gudang_dengan_total_terjaga(): void
    {
        $bks = app(SaveWarehouse::class)->handle(null, [
            'code' => 'BKS',
            'name' => 'Gudang Cabang Bekasi',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);

        $pemohon = $this->makeUser('internal_requester');
        $req = app(SubmitRequest::class)->handle(
            $this->buatReq([['item_id' => $this->item->id, 'qty_base' => 100]], null, $pemohon),
            $pemohon,
        );

        $baris = $req->openLines()->first();
        $staf = $this->makeUser('warehouse_staff');

        $pecahan = app(SplitRequestLine::class)->handle(
            $baris,
            [['warehouse_id' => $bks->id, 'qty_base' => 40]],
            $staf,
        );

        $this->assertCount(1, $pecahan);
        $this->assertSame(60.0, (float) $baris->refresh()->qty_base);
        $this->assertSame(40.0, (float) $pecahan[0]->qty_base);
        $this->assertSame($baris->id, $pecahan[0]->split_from_line_id);
        $this->assertSame(
            100.0,
            (float) $req->openLines()->sum('qty_base'),
            'BR-REQ-04: total permintaan tidak berubah karena pemecahan.',
        );
    }

    #[Test]
    public function tc_req_11_approval_membuat_reservasi_lunak(): void
    {
        $req = $this->reqMenungguApproval();
        $approver = $this->makeUser('warehouse_head');

        $req = app(ApproveRequest::class)->handle($req, $approver);

        $this->assertSame(MaterialRequestStatus::Approved, $req->status);

        $reservasi = StockReservation::query()
            ->active()
            ->forDocument('material_request', (int) $req->id)
            ->get();

        $this->assertCount(1, $reservasi, 'BR-REQ-05: baris bersumber stok mendapat reservasi lunak.');
        $this->assertSame(10.0, (float) $reservasi->first()->qty_base);
        $this->assertSame(
            90.0,
            app(StockLedger::class)->availableQty((int) $this->item->id, (int) $this->gudang->id),
            'BR-STK-03: stok tersedia berkurang sebanyak yang dijanjikan.',
        );
    }

    #[Test]
    public function tc_req_12_baris_tanpa_sumber_menahan_approval(): void
    {
        $req = $this->reqMenungguApproval();

        $req->openLines()->first()->forceFill(['fulfillment_source' => null])->save();

        try {
            app(ApproveRequest::class)->handle($req->refresh(), $this->makeUser('warehouse_head'));
            $this->fail('Baris tanpa sumber seharusnya menahan approval.');
        } catch (RequestRuleException $e) {
            $this->assertSame('BR-REQ-05', $e->rule);
        }
    }

    #[Test]
    public function tc_req_13_pemohon_tidak_bisa_menyetujui_dokumennya_sendiri(): void
    {
        $pemohon = $this->makeUser('warehouse_head');
        $req = $this->reqMenungguApproval($pemohon);

        try {
            app(ApproveRequest::class)->handle($req, $pemohon);
            $this->fail('Pemohon menyetujui dokumennya sendiri seharusnya ditolak.');
        } catch (RequestRuleException $e) {
            $this->assertSame('BR-REQ-07', $e->rule);
        }
    }

    #[Test]
    public function tc_req_14_pembatalan_tanpa_alasan_ditolak(): void
    {
        $req = app(ApproveRequest::class)->handle($this->reqMenungguApproval(), $this->makeUser('warehouse_head'));

        try {
            app(CancelRequest::class)->handle($req, null, null, $this->makeUser('warehouse_head'));
            $this->fail('Pembatalan tanpa alasan seharusnya ditolak.');
        } catch (RequestRuleException $e) {
            $this->assertSame('BR-GEN-11', $e->rule);
        }
    }

    #[Test]
    public function tc_req_15_pembatalan_melepas_reservasi(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $req = app(ApproveRequest::class)->handle($this->reqMenungguApproval(), $kepala);

        $req = app(CancelRequest::class)->handle($req, $this->alasanId(), 'Proyek ditunda', $kepala);

        $this->assertSame(MaterialRequestStatus::Cancelled, $req->status);
        $this->assertSame(
            0,
            StockReservation::query()->active()->forDocument('material_request', (int) $req->id)->count(),
        );
        $this->assertSame(
            100.0,
            app(StockLedger::class)->availableQty((int) $this->item->id, (int) $this->gudang->id),
            'BR-STK-05: stok tersedia kembali utuh setelah reservasi dilepas.',
        );
        $this->assertSame(
            RequestLineStatus::Cancelled,
            $req->lines()->first()->status,
        );
    }

    #[Test]
    public function tc_req_15b_req_dengan_barang_terkirim_tidak_bisa_dibatalkan(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $req = app(ApproveRequest::class)->handle($this->reqMenungguApproval(), $kepala);

        // Modul pengiriman belum ada; jejaknya ditulis langsung ke baris.
        $req->lines()->first()->forceFill(['qty_shipped' => 4])->save();

        try {
            app(CancelRequest::class)->handle($req->refresh(), $this->alasanId(), null, $kepala);
            $this->fail('REQ yang sebagian barangnya sudah dikirim seharusnya tidak bisa dibatalkan.');
        } catch (RequestRuleException $e) {
            $this->assertSame('BR-REQ-09', $e->rule);
        }
    }

    #[Test]
    public function tc_req_16_tutup_dengan_sisa_melepas_reservasi_sisa(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $req = app(ApproveRequest::class)->handle($this->reqMenungguApproval(), $kepala);

        // Sebagian sudah terkirim, lalu REQ masuk terpenuhi sebagian.
        $req->lines()->first()->forceFill(['qty_shipped' => 4])->save();
        $req->forceFill(['status' => MaterialRequestStatus::PartiallyFulfilled])->save();

        $req = app(CloseRequestShort::class)->handle($req->refresh(), $this->alasanId(), 'Sisa tidak dipakai', $kepala);

        $this->assertSame(MaterialRequestStatus::ClosedShort, $req->status);
        $this->assertSame(RequestLineStatus::Closed, $req->lines()->first()->status);
        $this->assertSame(
            0,
            StockReservation::query()->active()->forDocument('material_request', (int) $req->id)->count(),
        );
        $this->assertSame(
            ReservationStatus::Released,
            StockReservation::query()->forDocument('material_request', (int) $req->id)->first()->status,
        );
    }

    /** REQ internal lengkap yang sudah sampai `pending_approval`. */
    private function reqMenungguApproval(?\App\Domain\Access\Models\User $pemohon = null): MaterialRequest
    {
        $pemohon ??= $this->makeUser('internal_requester');

        $req = $this->buatReq([], null, $pemohon);

        $req->openLines()->first()->forceFill([
            'source_warehouse_id' => $this->gudang->id,
            'fulfillment_source' => 'stock',
        ])->save();

        return app(SubmitRequest::class)->handle($req->refresh(), $pemohon);
    }
}

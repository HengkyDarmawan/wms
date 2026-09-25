<?php

declare(strict_types=1);

namespace Tests\Feature\PurchaseRequest;

use App\Domain\Master\Enums\ReasonContext;
use App\Domain\PurchaseRequest\Actions\CreatePurchaseRequest;
use App\Domain\PurchaseRequest\Actions\SubmitPurchaseRequest;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestOrigin;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestStatus;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\Receipt\Actions\CompleteGoodsReceipt;
use App\Domain\Receipt\Actions\CompletePutaway;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Request\Actions\CancelRequest;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Stock\Enums\ReservationLevel;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Stock\Support\StockLedger;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\PurchaseRequest\Concerns\PurchaseFixtures;
use Tests\TenantTestCase;

/**
 * TC-PRQ-07 s.d. TC-PRQ-09 — rantai lintas modul REQ bersumber pembelian →
 * PRQ backorder → catatan pemesanan → GRN → put-away → reservasi ke REQ →
 * PCK → SJ (BR-REQ-05, BR-REQ-08, A-171), pembatalan REQ yang ikut
 * membatalkan PRQ (BR-REQ-09), dan job titik pesan ulang (BR-REQ-11).
 */
class PurchaseRequestChainTest extends TenantTestCase
{
    use PurchaseFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPembelian();
    }

    private function reqPembelian(float $qty): MaterialRequest
    {
        $pemohon = $this->makeUser('internal_requester');

        $req = app(SaveRequest::class)->handle(
            null,
            ['project_id' => $this->proyek->id, 'required_date' => now()->addDays(7)->toDateString()],
            [['item_id' => $this->baut->id, 'qty_base' => $qty]],
            $pemohon,
        );

        $req->openLines()->first()->forceFill([
            'source_warehouse_id' => $this->gudang->id,
            'fulfillment_source' => 'purchase',
        ])->save();

        return app(SubmitRequest::class)->handle($req->refresh(), $pemohon);
    }

    #[Test]
    public function tc_prq_07_backorder_req_sampai_barang_dikirim_ke_proyek(): void
    {
        $req = $this->reqPembelian(40);
        $this->assertSame(MaterialRequestStatus::Approved, $req->status);

        // REQ disetujui → PRQ backorder per gudang pemenuh, langsung diajukan.
        $prq = PurchaseRequest::query()->where('material_request_id', $req->id)->sole();
        $this->assertSame(PurchaseRequestOrigin::Backorder, $prq->origin);
        $this->assertSame(PurchaseRequestStatus::Approved, $prq->status);
        $this->assertSame((int) $this->proyek->id, (int) $prq->project_id);
        $this->assertSame(40.0, (float) $prq->lines()->sole()->qty_base);
        $this->assertSame((int) $req->lines()->sole()->id, (int) $prq->lines()->sole()->material_request_line_id);

        $ref = $this->barisPesanan($this->pesan($prq));
        $grn = $this->grnDiterima([['item_id' => $this->baut->id, 'qty_received' => 40, 'purchase_request_order_line_id' => $ref]]);
        $this->assertSame(PurchaseRequestStatus::Fulfilled, $prq->refresh()->status);

        $grn = app(CompleteGoodsReceipt::class)->handle($grn, $this->makeUser());
        app(CompletePutaway::class)->handle($grn->putawayTasks()->sole(), [], $this->makeUser());

        // BR-REQ-08: barang yang ditaruh langsung direservasi lunak ke baris REQ penunggu.
        $reqLine = $req->lines()->sole();
        $this->assertSame(40.0, (float) $reqLine->qty_reserved);
        $this->assertSame(40.0, (float) StockReservation::query()->active()->where('level', ReservationLevel::Soft->value)
            ->forDocument('material_request', (int) $req->id)->sum('qty_base'));
        $this->assertSame(0.0, app(StockLedger::class)->availableQty($this->baut->id, $this->gudang->id), 'Stok tiba tidak diambil REQ lain.');

        // Lalu dipetik dan dikirim seperti REQ biasa.
        $reqLine->forceFill(['fulfillment_source' => 'stock'])->save();
        $pck = $this->pckSelesai($req->refresh());
        $sj = $this->buktiTerima($this->sjBerangkat($pck, ['destination_type' => 'project_client', 'destination_project_id' => $this->proyek->id]), 40);

        $this->assertSame(ShipmentStatus::Delivered, $sj->status);
        $this->assertSame(MaterialRequestStatus::Completed, $req->refresh()->status);
    }

    #[Test]
    public function tc_prq_08_req_dibatalkan_sebelum_dipesan_ikut_membatalkan_prq(): void
    {
        $req = $this->reqPembelian(10);
        $prq = PurchaseRequest::query()->where('material_request_id', $req->id)->sole();

        app(CancelRequest::class)->handle($req, $this->alasan(ReasonContext::Cancel), 'Tidak jadi', $this->makeUser('warehouse_head'));
        $this->assertSame(PurchaseRequestStatus::Cancelled, $prq->refresh()->status);

        // Sudah dipesan: PRQ dibiarkan, barangnya kelak menjadi stok biasa.
        $kedua = $this->reqPembelian(10);
        $prqKedua = PurchaseRequest::query()->where('material_request_id', $kedua->id)->sole();
        $this->pesan($prqKedua);
        app(CancelRequest::class)->handle($kedua, $this->alasan(ReasonContext::Cancel), null, $this->makeUser('warehouse_head'));
        $this->assertSame(PurchaseRequestStatus::Forwarded, $prqKedua->refresh()->status);
    }

    #[Test]
    public function tc_prq_09_job_titik_pesan_ulang_membuat_draf_sekali(): void
    {
        $this->baut->forceFill(['reorder_point' => 10, 'min_stock' => 30])->save();
        $this->grnDiterima([['item_id' => $this->baut->id, 'qty_received' => 5]]);
        $grn = app(CompleteGoodsReceipt::class)->handle(GoodsReceipt::query()->latest('id')->first(), $this->makeUser());
        app(CompletePutaway::class)->handle($grn->putawayTasks()->sole(), [], $this->makeUser());

        $this->artisan('purchase-requests:reorder')->assertSuccessful();

        $draf = PurchaseRequest::query()->where('origin', PurchaseRequestOrigin::ReorderPoint->value)->sole();
        $this->assertSame(PurchaseRequestStatus::Draft, $draf->status);
        $this->assertSame((int) $this->gudang->id, (int) $draf->warehouse_id);
        $this->assertSame(25.0, (float) $draf->lines()->sole()->qty_base, 'Stok minimum 30 − tersedia 5.');

        // Tidak dibuat ulang selama masih ada PRQ terbuka.
        $this->artisan('purchase-requests:reorder')->assertSuccessful();
        $this->assertSame(1, PurchaseRequest::query()->where('origin', PurchaseRequestOrigin::ReorderPoint->value)->count());

        // Kepala Gudang meninjau jumlah lalu mengajukan.
        $kepala = $this->makeUser('warehouse_head');
        app(CreatePurchaseRequest::class)->update($draf, ['notes' => 'Ditinjau'], [['item_id' => $this->baut->id, 'qty_base' => 50]], $kepala);
        $draf = app(SubmitPurchaseRequest::class)->handle($draf->refresh(), $kepala);
        $this->assertSame(PurchaseRequestStatus::Approved, $draf->status);
        $this->assertSame(50.0, (float) $draf->lines()->sole()->qty_base);
    }
}

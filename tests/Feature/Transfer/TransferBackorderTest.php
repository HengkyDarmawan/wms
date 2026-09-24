<?php

declare(strict_types=1);

namespace Tests\Feature\Transfer;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\Vehicle;
use App\Domain\Request\Actions\CancelRequest;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Enums\RequestLineStatus;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Shipment\Actions\CreatePickTask;
use App\Domain\Shipment\Actions\CreateShipment;
use App\Domain\Shipment\Actions\ShipShipment;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Stock\Enums\ReservationLevel;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Transfer\Enums\TransferOrigin;
use App\Domain\Transfer\Enums\TransferStatus;
use App\Domain\Transfer\Models\Transfer;
use App\Domain\Warehouse\Models\Warehouse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalFixtures;
use Tests\Feature\Transfer\Concerns\TransferFixtures;
use Tests\TenantTestCase;

/**
 * TC-TRF-10 s.d. TC-TRF-13 — TRF dari backorder REQ (BR-REQ-05, BR-REQ-08,
 * BR-REQ-15, A-106, A-108): REQ bersumber transfer → TRF → PCK/SJ di gudang
 * asal → bukti terima → GRN gudang tujuan → put-away → reservasi ke REQ →
 * PCK/SJ ke proyek → REQ selesai.
 */
class TransferBackorderTest extends TenantTestCase
{
    use ApprovalFixtures;
    use TransferFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanTransfer();
    }

    /** REQ proyek dengan satu baris bersumber transfer ke gudang pemenuh BKS. */
    private function reqTransfer(float $qty, ?Warehouse $pemenuh = null): MaterialRequest
    {
        $pemohon = $this->makeUser('internal_requester');

        $req = app(SaveRequest::class)->handle(
            null,
            ['project_id' => $this->proyek->id, 'required_date' => now()->addDays(5)->toDateString()],
            [['item_id' => $this->baut->id, 'qty_base' => $qty]],
            $pemohon,
        );

        $req->openLines()->first()->forceFill([
            'source_warehouse_id' => ($pemenuh ?? $this->bks)->id,
            'fulfillment_source' => 'transfer',
        ])->save();

        return app(SubmitRequest::class)->handle($req->refresh(), $pemohon);
    }

    #[Test]
    public function tc_trf_10_rantai_penuh_req_bersumber_transfer_sampai_req_selesai(): void
    {
        $req = $this->reqTransfer(30);
        $baris = $req->lines()->first();

        $this->assertSame(MaterialRequestStatus::Approved, $req->status);

        // BR-REQ-05: TRF backorder CKG (induk BKS, stok cukup — A-106) → BKS.
        $trf = Transfer::query()->where('source_type', 'material_request')->where('source_id', $req->id)->sole();
        $this->assertSame(TransferOrigin::Backorder, $trf->origin);
        $this->assertSame((int) $this->gudang->id, (int) $trf->from_warehouse_id);
        $this->assertSame((int) $this->bks->id, (int) $trf->to_warehouse_id);
        $this->assertSame((int) $baris->id, (int) $trf->lines()->first()->material_request_line_id);
        $this->assertSame(TransferStatus::InProgress, $trf->status);

        // Belum ada barang di BKS: baris REQ belum bisa dipetik.
        try {
            app(CreatePickTask::class)->handle($req, $this->makeUser('warehouse_head'));
            $this->fail('Baris transfer belum boleh dipetik sebelum barangnya tiba.');
        } catch (ShipmentRuleException $e) {
            $this->assertSame('BR-SJ-01', $e->rule);
        }

        // Kaki keluar di CKG, kaki masuk di BKS.
        $pck = $this->jalankanPck($this->pckTrf($trf));
        $sj = $this->terimaSj($this->sjDari($pck, $this->bks));
        $grn = $this->grnTransferSelesai($sj, $this->bks);

        $this->assertSame(TransferStatus::Completed, $trf->refresh()->status);
        $this->assertSame(0.0, (float) $baris->refresh()->qty_reserved, 'Barang di Penerimaan belum dijanjikan (A-85, A-108).');

        // BR-REQ-08 (A-108): setelah put-away barang direservasi ke REQ penunggu.
        $this->putSelesai($grn, $this->binBks);
        $baris->refresh();
        $this->assertSame(30.0, (float) $baris->qty_reserved);
        $this->assertSame(30.0, (float) StockReservation::query()->active()->where('level', ReservationLevel::Soft->value)
            ->forDocument('material_request', $req->id)->where('document_line_id', $baris->id)->sum('qty_base'));
        $this->assertSame(0.0, app(StockLedger::class)->availableQty($this->baut->id, $this->bks->id), 'Stok BKS terjanji ke REQ.');

        // Baris transfer kini dipetik di BKS dan dikirim ke proyek.
        $pckReq = app(CreatePickTask::class)->handle($req->refresh(), $this->makeUser('warehouse_head'))[0];
        $this->assertSame((int) $this->bks->id, (int) $pckReq->warehouse_id);
        $this->assertSame(MaterialRequestStatus::InProgress, $req->refresh()->status);

        $pckReq = $this->jalankanPck($pckReq);
        $sjReq = app(CreateShipment::class)->handle([$pckReq->id], [
            'destination_type' => 'project_client',
            'destination_project_id' => $this->proyek->id,
            'shipment_method' => 'own_fleet',
            'vehicle_id' => Vehicle::create(['plate_no' => 'B7777TR'])->id,
            'driver_id' => $this->makeUser('driver')->id,
        ], $this->makeUser('warehouse_staff'));
        $sjReq = app(ShipShipment::class)->handle($sjReq, null, $this->makeUser('driver'));
        $this->terimaSj($sjReq);

        $req->refresh();
        $this->assertSame(MaterialRequestStatus::Completed, $req->status, 'REQ terpenuhi lewat gudang tujuan TRF.');
        $this->assertSame(RequestLineStatus::Closed, $req->lines()->first()->status);
        $this->assertSame(30.0, (float) $req->lines()->first()->qty_received);
    }

    #[Test]
    public function tc_trf_11_tanpa_gudang_asal_yang_cukup_approval_req_tertahan(): void
    {
        try {
            $this->reqTransfer(500);
            $this->fail('REQ seharusnya tertahan BR-REQ-05.');
        } catch (RequestRuleException $e) {
            $this->assertSame('BR-REQ-05', $e->rule);
            $this->assertStringContainsString('tidak ada gudang lain', $e->getMessage());
        }

        $this->assertSame(0, Transfer::query()->count());
    }

    #[Test]
    public function tc_trf_12_gudang_asal_bukan_induk_dipilih_dari_stok_terbanyak(): void
    {
        // CKG = induk BKS, tetapi pemenuh sekarang KRW1 (Gudang Site, tanpa induk);
        // BKS punya stok lebih banyak dari CKG → dipilih BKS (A-106).
        $this->stok($this->binBks, $this->baut, 150);

        $req = $this->reqTransfer(120, $this->krw1);
        $trf = Transfer::query()->where('source_id', $req->id)->sole();

        $this->assertSame((int) $this->bks->id, (int) $trf->from_warehouse_id);
        $this->assertSame((int) $this->proyek->id, (int) $trf->to_project_id);
    }

    #[Test]
    public function tc_trf_13_req_dibatalkan_membatalkan_trf_backorder_yang_belum_berjalan(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $this->aturan(ApprovalDocumentType::Transfer, [$this->lapisUser($kepala)]);

        $req = $this->reqTransfer(10);
        $trf = Transfer::query()->where('source_id', $req->id)->sole();
        $this->assertSame(TransferStatus::PendingApproval, $trf->status);

        app(CancelRequest::class)->handle($req, $this->alasan(ReasonContext::Cancel), null, $kepala);

        $this->assertSame(TransferStatus::Cancelled, $trf->refresh()->status, 'BR-REQ-15 / A-108.');
        $this->assertSame(0, StockReservation::query()->active()->forDocument('transfer', $trf->id)->count());
    }
}

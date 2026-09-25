<?php

declare(strict_types=1);

namespace Tests\Feature\Request;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Uom;
use App\Domain\Request\Actions\AddRequestLines;
use App\Domain\Request\Actions\CancelRequestLine;
use App\Domain\Request\Actions\RespondSubstitution;
use App\Domain\Request\Actions\ReviewRequest;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Enums\RequestLineStatus;
use App\Domain\Request\Enums\RequestOrigin;
use App\Domain\Request\Enums\SubstitutionResponse;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Approval\Concerns\ApprovalFixtures;
use Tests\TenantTestCase;

/**
 * TC-REQ-17 s.d. TC-REQ-24 — interaksi klien: tambah baris, penggantian item,
 * permintaan pembatalan, dan tanggal janji (BR-REQ-12, BR-REQ-13, BR-REQ-14,
 * BR-REQ-15).
 */
class ClientInteractionTest extends TenantTestCase
{
    use ApprovalFixtures;

    private Project $proyek;

    private Warehouse $gudang;

    private Item $item;

    private Item $pengganti;

    private User $klien;

    protected function setUp(): void
    {
        parent::setUp();

        $this->proyek = $this->makeProject();

        $this->gudang = app(SaveWarehouse::class)->handle(null, [
            'code' => 'CKG',
            'name' => 'Gudang Utama Cakung',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);

        $pcs = (int) Uom::query()->where('code', 'PCS')->value('id');

        $this->item = Item::create([
            'code' => 'BAUT-M12',
            'name' => 'Baut M12',
            'status' => ItemStatus::Active,
            'tracking_mode' => TrackingMode::None,
            'base_uom_id' => $pcs,
        ]);

        $this->pengganti = Item::create([
            'code' => 'BAUT-M14',
            'name' => 'Baut M14',
            'status' => ItemStatus::Active,
            'tracking_mode' => TrackingMode::None,
            'base_uom_id' => $pcs,
        ]);

        $bin = Bin::create([
            'warehouse_id' => $this->gudang->id,
            'code' => 'CKG-A-R01-L1-B01',
            'bin_type' => BinType::Storage,
        ]);

        foreach ([$this->item, $this->pengganti] as $item) {
            app(StockLedger::class)->post(new MovementRequest(
                item: $item,
                qtyBase: 100,
                toBinId: $bin->id,
            ));
        }

        $this->klien = $this->makeUser('client_user', ScopeType::Project, $this->proyek->id, [
            'client_id' => $this->proyek->client_id,
        ]);
    }

    /** REQ klien yang sudah masuk `under_review`. */
    private function reqKlien(array $lines = []): MaterialRequest
    {
        $req = app(SaveRequest::class)->handle(
            null,
            [
                'project_id' => $this->proyek->id,
                'required_date' => now()->addDays(3)->toDateString(),
            ],
            $lines === [] ? [['item_id' => $this->item->id, 'qty_base' => 10]] : $lines,
            $this->klien,
        );

        return app(SubmitRequest::class)->handle($req, $this->klien);
    }

    private function alasanId(): int
    {
        $id = ReasonCode::query()->value('id');

        $this->assertNotNull($id, 'Seeder referensi harus menyediakan kode alasan.');

        return (int) $id;
    }

    /** REQ klien yang sudah disetujui, lengkap dengan reservasinya. */
    private function reqDisetujui(): MaterialRequest
    {
        $req = $this->reqKlien();
        $staf = $this->makeUser('warehouse_staff');

        app(ReviewRequest::class)->setSource(
            $req->openLines()->first(),
            (int) $this->gudang->id,
            'stock',
            now()->addDays(5)->toDateString(),
            $staf,
        );

        // Tanpa aturan approval, REQ langsung disetujui dan direservasi (A-08).
        return app(ReviewRequest::class)->submitToApproval($req->refresh(), $staf);
    }

    #[Test]
    public function tc_req_17_klien_menambah_baris_saat_menunggu_approval(): void
    {
        // Satu lapis supaya REQ benar-benar berhenti di pending_approval.
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($this->makeUser('warehouse_head'))]);

        $req = $this->reqKlien();
        $staf = $this->makeUser('warehouse_staff');

        app(ReviewRequest::class)->setSource(
            $req->openLines()->first(),
            (int) $this->gudang->id,
            'stock',
            null,
            $staf,
        );

        $req = app(ReviewRequest::class)->submitToApproval($req->refresh(), $staf);

        $this->assertSame(MaterialRequestStatus::PendingApproval, $req->status);
        $this->assertNotNull($req->reviewed_at);

        $req = app(AddRequestLines::class)->handle(
            $req,
            [['item_id' => $this->pengganti->id, 'qty_base' => 4]],
            $this->klien,
        );

        $this->assertSame(
            MaterialRequestStatus::UnderReview,
            $req->status,
            'BR-REQ-12: penambahan saat menunggu approval mengembalikan REQ ke tinjau.',
        );
        $this->assertNull($req->reviewed_at, 'Snapshot approval dibuang bersama jejak tinjau.');
        $this->assertNull($req->approval_snapshot_id);
        $this->assertSame(
            'cancelled',
            ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::MaterialRequest, (int) $req->id)->sole()->status->value,
            'BR-REQ-12: snapshot lama dihentikan, tugasnya tidak bisa diputus lagi.',
        );
        $this->assertSame(2, $req->openLines()->count());
    }

    #[Test]
    public function tc_req_18_klien_menambah_baris_setelah_disetujui_membuat_req_tambahan(): void
    {
        $induk = $this->reqDisetujui();

        $tambahan = app(AddRequestLines::class)->handle(
            $induk,
            [['item_id' => $this->pengganti->id, 'qty_base' => 4]],
            $this->klien,
        );

        $this->assertNotSame((int) $induk->id, (int) $tambahan->id);
        $this->assertSame(RequestOrigin::Supplement, $tambahan->origin);
        $this->assertSame((int) $induk->id, (int) $tambahan->parent_request_id);
        $this->assertSame(MaterialRequestStatus::UnderReview, $tambahan->status);
        $this->assertNotSame($induk->number, $tambahan->number, 'REQ Tambahan punya nomor sendiri.');

        // REQ induk tidak berubah: yang sudah dikerjakan gudang tetap utuh.
        $this->assertSame(MaterialRequestStatus::Approved, $induk->refresh()->status);
        $this->assertSame(1, $induk->openLines()->count());
        $this->assertCount(1, $induk->supplements);
    }

    #[Test]
    public function tc_req_19_klien_menolak_penggantian_membatalkan_baris(): void
    {
        $req = $this->reqDisetujui();
        $staf = $this->makeUser('warehouse_staff');

        // Penggantian hanya terjadi saat tinjau, jadi REQ dikembalikan dulu.
        $req->forceFill(['status' => MaterialRequestStatus::UnderReview])->save();

        $baris = app(ReviewRequest::class)->mapLine($req->openLines()->first(), (int) $this->pengganti->id, $staf);

        $this->assertTrue($baris->isSubstituted());
        $this->assertTrue($baris->substitutionIsPending());
        $this->assertSame('BAUT-M12 — Baut M12', $baris->original_item_text);

        $baris = app(RespondSubstitution::class)->reject($baris, $this->alasanId(), $this->klien);

        $this->assertSame(SubstitutionResponse::Rejected, $baris->substitution_response);
        $this->assertSame(RequestLineStatus::Cancelled, $baris->status);
        $this->assertSame(
            0,
            StockReservation::query()->active()->forDocument('material_request', (int) $req->id)->count(),
            'BR-STK-05: reservasi baris ikut dilepas.',
        );
    }

    #[Test]
    public function tc_req_19b_penolakan_penggantian_wajib_beralasan(): void
    {
        $req = $this->reqKlien();
        $staf = $this->makeUser('warehouse_staff');

        $baris = app(ReviewRequest::class)->mapLine($req->openLines()->first(), (int) $this->pengganti->id, $staf);

        try {
            app(RespondSubstitution::class)->reject($baris, null, $this->klien);
            $this->fail('Penolakan tanpa alasan seharusnya ditolak.');
        } catch (RequestRuleException $e) {
            $this->assertSame('BR-GEN-11', $e->rule);
        }
    }

    #[Test]
    public function tc_req_20_penggantian_lewat_tenggat_dianggap_disetujui(): void
    {
        $req = $this->reqKlien();
        $staf = $this->makeUser('warehouse_staff');

        $baris = app(ReviewRequest::class)->mapLine($req->openLines()->first(), (int) $this->pengganti->id, $staf);

        // Tenggat dimundurkan seolah sudah lewat sehari.
        $baris->forceFill(['substitution_deadline_at' => now()->subDay()])->save();

        // Job terjadwal tiap jam `requests:expire-substitutions` (A-239).
        $this->artisan('requests:expire-substitutions')
            ->expectsOutputToContain('1 penggantian kedaluwarsa')
            ->assertSuccessful();

        $this->assertSame(SubstitutionResponse::Expired, $baris->refresh()->substitution_response);
        $this->assertSame(0, app(RespondSubstitution::class)->expireOverdue(), 'Aman dijalankan berulang.');
        $this->assertSame(
            RequestLineStatus::Open,
            $baris->status,
            'BR-REQ-13: lewat tenggat berarti setuju, jadi barisnya tetap berjalan.',
        );

        // Setelah kedaluwarsa, klien tidak bisa lagi menolaknya.
        try {
            app(RespondSubstitution::class)->reject($baris, $this->alasanId(), $this->klien);
            $this->fail('Tanggapan setelah tenggat seharusnya ditolak.');
        } catch (RequestRuleException $e) {
            $this->assertSame('BR-REQ-13', $e->rule);
        }
    }

    #[Test]
    public function tc_req_21_permintaan_pembatalan_dikonfirmasi_staf(): void
    {
        $req = $this->reqDisetujui();
        $baris = $req->openLines()->first();

        $baris = app(CancelRequestLine::class)->request($baris, $this->alasanId(), $this->klien);

        $this->assertTrue($baris->awaitsCancelConfirmation());
        $this->assertSame(
            1,
            StockReservation::query()->active()->forDocument('material_request', (int) $req->id)->count(),
            'Reservasi belum dilepas selama permintaan belum dikonfirmasi.',
        );

        $baris = app(CancelRequestLine::class)->confirm($baris, $this->makeUser('warehouse_staff'));

        $this->assertSame(RequestLineStatus::Cancelled, $baris->status);
        $this->assertSame(
            0,
            StockReservation::query()->active()->forDocument('material_request', (int) $req->id)->count(),
        );
        $this->assertSame(
            100.0,
            app(StockLedger::class)->availableQty((int) $this->item->id, (int) $this->gudang->id),
        );
    }

    #[Test]
    public function tc_req_22_permintaan_pembatalan_ditolak_staf(): void
    {
        $req = $this->reqDisetujui();
        $baris = app(CancelRequestLine::class)->request($req->openLines()->first(), $this->alasanId(), $this->klien);

        $baris = app(CancelRequestLine::class)->refuse($baris, 'Barang sudah disiapkan', $this->makeUser('warehouse_staff'));

        $this->assertSame(RequestLineStatus::Open, $baris->status);
        $this->assertFalse($baris->awaitsCancelConfirmation());
        $this->assertSame(
            1,
            StockReservation::query()->active()->forDocument('material_request', (int) $req->id)->count(),
            'Reservasi tetap ada karena barisnya tidak jadi dibatalkan.',
        );
    }

    #[Test]
    public function tc_req_22b_baris_yang_sudah_terkirim_tidak_bisa_diminta_batal(): void
    {
        $req = $this->reqDisetujui();
        $baris = $req->openLines()->first();

        $baris->forceFill(['qty_shipped' => 3])->save();

        try {
            app(CancelRequestLine::class)->request($baris->refresh(), $this->alasanId(), $this->klien);
            $this->fail('Baris yang sudah dikirim seharusnya tidak bisa diminta batal.');
        } catch (RequestRuleException $e) {
            $this->assertSame('BR-REQ-15', $e->rule);
        }
    }

    #[Test]
    public function tc_req_23_req_klien_yang_lama_ditinjau_ditandai_terlambat(): void
    {
        $lama = $this->reqKlien();
        $this->reqKlien();

        $lama->forceFill(['created_at' => now()->subDays(3)])->save();

        $terlambat = MaterialRequest::query()->reviewOverdue(1)->get();

        $this->assertCount(1, $terlambat, 'BR-REQ-14: hanya yang melewati SLA yang muncul.');
        $this->assertSame((int) $lama->id, (int) $terlambat->first()->id);
        $this->assertSame(3, $terlambat->first()->reviewAgeInDays());
    }

    #[Test]
    public function tc_req_24_tanggal_janji_tercatat_di_riwayat(): void
    {
        $req = $this->reqKlien();
        $staf = $this->makeUser('warehouse_staff');
        $janji = now()->addDays(6)->toDateString();

        $baris = app(ReviewRequest::class)->setSource(
            $req->openLines()->first(),
            (int) $this->gudang->id,
            'stock',
            $janji,
            $staf,
        );

        $this->assertSame($janji, $baris->promised_date?->toDateString());

        $tercatat = Activity::query()
            ->where('log_name', 'request')
            ->where('description', 'Tanggal janji baris REQ diubah')
            ->exists();

        $this->assertTrue($tercatat, 'BR-REQ-14: perubahan tanggal janji harus tercatat.');
    }
}

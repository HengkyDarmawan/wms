<?php

declare(strict_types=1);

namespace Tests\Feature\Transfer;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApprovalSnapshotStatus;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\ProjectStatus;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Shipment\Actions\CreatePickTask;
use App\Domain\Shipment\Actions\CreateShipment;
use App\Domain\Shipment\Actions\ProcessPickTask;
use App\Domain\Shipment\Enums\PickTaskStatus;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Stock\Enums\ReservationLevel;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Transfer\Actions\ApproveTransfer;
use App\Domain\Transfer\Actions\CancelTransfer;
use App\Domain\Transfer\Enums\TransferKind;
use App\Domain\Transfer\Enums\TransferOrigin;
use App\Domain\Transfer\Enums\TransferStatus;
use App\Domain\Transfer\Exceptions\TransferRuleException;
use App\Domain\Transfer\Models\Transfer;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalFixtures;
use Tests\Feature\Transfer\Concerns\TransferFixtures;
use Tests\TenantTestCase;

/**
 * TC-TRF-01 s.d. TC-TRF-09 — TRF manual antar gudang, antar proyek, dan dalam
 * proyek (Katalog §2.7, BR-RET-01/02, A-50, A-107): guard, approval, reservasi,
 * PCK otomatis, jejak SJ/GRN, penyelesaian, pembatalan.
 */
class TransferTest extends TenantTestCase
{
    use ApprovalFixtures;
    use TransferFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanTransfer();
    }

    private function gagal(callable $aksi, string $aturan, string $kelas = TransferRuleException::class): void
    {
        try {
            $aksi();
            $this->fail('Seharusnya ditolak '.$aturan.'.');
        } catch (TransferRuleException|ShipmentRuleException $e) {
            $this->assertInstanceOf($kelas, $e);
            $this->assertSame($aturan, $e->rule, $e->getMessage());
        }
    }

    #[Test]
    public function tc_trf_01_tanpa_aturan_disetujui_otomatis_lalu_pck_di_gudang_asal(): void
    {
        $trf = $this->trf($this->gudang, $this->bks, [['item_id' => $this->baut->id, 'qty_base' => 30]]);

        $this->assertStringStartsWith('TRF/CKG/', $trf->number, 'Nomor memakai gudang asal (A-43).');
        $this->assertSame(TransferStatus::InProgress, $trf->status, 'Disetujui otomatis (A-08), PCK dibuat (alur 5 langkah 5).');
        $this->assertSame(TransferOrigin::Manual, $trf->origin);
        $this->assertSame(TransferKind::BetweenWarehouses, $trf->kind());
        $this->assertNull($trf->from_project_id);

        $pck = $this->pckTrf($trf);
        $this->assertSame((int) $this->gudang->id, (int) $pck->warehouse_id);
        $this->assertSame(PickTaskStatus::Pending, $pck->status);
        $this->assertSame(30.0, (float) $pck->lines()->sum('qty_allocated'));

        // Reservasi lunak TRF berubah menjadi alokasi keras PCK (BR-STK-04).
        $this->assertSame(0, StockReservation::query()->active()->forDocument('transfer', $trf->id)->count());
        $this->assertSame(30.0, (float) StockReservation::query()->active()->forDocument('pick_task', $pck->id)->sum('qty_base'));
        $this->assertSame(70.0, app(StockLedger::class)->availableQty($this->baut->id, $this->gudang->id));

        $snapshot = ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::Transfer, $trf->id)->sole();
        $this->assertSame(ApprovalSnapshotStatus::Approved, $snapshot->status);
    }

    #[Test]
    public function tc_trf_02_guard_pembuatan(): void
    {
        $baris = [['item_id' => $this->baut->id, 'qty_base' => 5]];

        $this->gagal(fn () => $this->trf($this->gudang, $this->gudang, $baris), 'BR-RET-02');
        $this->gagal(fn () => $this->trf($this->gudang, $this->bks, []), 'BR-RET-01');
        $this->gagal(fn () => $this->trf($this->gudang, $this->bks, [['item_id' => $this->baut->id, 'qty_base' => 0]]), 'BR-LED-02');
        $this->gagal(fn () => $this->trf($this->gudang, $this->bks, [['item_id' => $this->baut->id, 'qty_base' => 101]]), 'BR-STK-03');
        $this->gagal(fn () => $this->trf($this->gudang, $this->bks, [['item_id' => $this->genset->id, 'qty_base' => 1.5]]), 'BR-LED-04');

        $this->kabel->forceFill(['status' => ItemStatus::Provisional])->save();
        $this->gagal(fn () => $this->trf($this->gudang, $this->bks, [['item_id' => $this->kabel->id, 'qty_base' => 1]]), 'BR-REQ-03');

        $this->bks->forceFill(['is_active' => false])->save();
        $this->gagal(fn () => $this->trf($this->gudang, $this->bks, $baris), 'BR-WH-07');
        $this->bks->forceFill(['is_active' => true])->save();

        // BR-ACC-05: pengaju harus bercakupan gudang asal atau tujuan.
        $stafLain = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->krw2->id);
        $this->gagal(fn () => $this->trf($this->gudang, $this->bks, $baris, $stafLain), 'BR-ACC-05');

        // BR-PRJ-01: Gudang Site proyek yang tidak aktif tidak menerima TRF.
        $this->proyek->forceFill(['status' => ProjectStatus::Closed])->save();
        $this->gagal(fn () => $this->trf($this->gudang, $this->krw1, $baris), 'BR-PRJ-01');

        $this->assertSame(0, Transfer::query()->count());
    }

    #[Test]
    public function tc_trf_03_approval_berlapis_sod_dan_tolak(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $this->aturan(ApprovalDocumentType::Transfer, [$this->lapisUser($kepala)]);
        $staf = $this->makeUser('warehouse_staff');

        $trf = $this->trf($this->gudang, $this->bks, [['item_id' => $this->baut->id, 'qty_base' => 20]], $staf);
        $this->assertSame(TransferStatus::PendingApproval, $trf->status);
        $this->assertSame(0, StockReservation::query()->active()->forDocument('transfer', $trf->id)->count(), 'Reservasi baru saat disetujui.');

        // BR-APR-03: pengaju tidak memutus TRF-nya sendiri.
        $this->gagal(fn () => app(ApproveTransfer::class)->approve($trf, $staf), 'BR-APR-03');

        $trf = app(ApproveTransfer::class)->approve($trf, $kepala);
        $this->assertSame(TransferStatus::InProgress, $trf->status);
        $this->assertSame((int) $kepala->id, (int) $trf->approved_by);
        $this->assertTrue($trf->hasLivePickTask());

        // Tolak: Alasan wajib (BR-GEN-11).
        $kedua = $this->trf($this->gudang, $this->bks, [['item_id' => $this->baut->id, 'qty_base' => 5]], $staf);
        $this->gagal(fn () => app(ApproveTransfer::class)->reject($kedua, null, null, $kepala), 'BR-GEN-11');

        $kedua = app(ApproveTransfer::class)->reject($kedua, $this->alasan(ReasonContext::Reject), 'Tidak perlu', $kepala);
        $this->assertSame(TransferStatus::Rejected, $kedua->status);
        $this->assertNotNull($kedua->reject_reason_id);
        $this->assertFalse($kedua->hasLivePickTask());
    }

    #[Test]
    public function tc_trf_04_pembatalan_sebelum_ada_pck(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $this->aturan(ApprovalDocumentType::Transfer, [$this->lapisUser($kepala)]);

        $trf = $this->trf($this->gudang, $this->bks, [['item_id' => $this->baut->id, 'qty_base' => 10]]);

        $this->gagal(fn () => app(CancelTransfer::class)->handle($trf, null, null, $kepala), 'BR-GEN-11');

        $trf = app(CancelTransfer::class)->handle($trf, $this->alasan(ReasonContext::Cancel), 'Salah gudang', $kepala);
        $this->assertSame(TransferStatus::Cancelled, $trf->status);
        $this->assertNotNull($trf->cancelled_at);
        $this->assertSame(
            ApprovalSnapshotStatus::Cancelled,
            ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::Transfer, $trf->id)->latest('id')->first()->status,
        );

        // TRF yang sudah punya PCK (in_progress) tidak bisa dibatalkan (Katalog §2.7).
        $jalan = app(ApproveTransfer::class)->approve(
            $this->trf($this->gudang, $this->bks, [['item_id' => $this->baut->id, 'qty_base' => 10]]),
            $kepala,
        );
        $this->gagal(fn () => app(CancelTransfer::class)->handle($jalan, $this->alasan(ReasonContext::Cancel), null, $kepala), 'BR-GEN-03');
    }

    #[Test]
    public function tc_trf_05_pck_gagal_otomatis_trf_tetap_disetujui_lalu_pck_manual(): void
    {
        // Bin sumber dibeku: stok tetap tersedia, tetapi alokasi picking menolaknya.
        $this->binA->forceFill(['bin_status' => BinStatus::Frozen])->save();

        $trf = $this->trf($this->gudang, $this->bks, [['item_id' => $this->baut->id, 'qty_base' => 25]]);

        $this->assertSame(TransferStatus::Approved, $trf->status, 'A-107: persetujuan tidak batal karena PCK gagal.');
        $this->assertFalse($trf->hasLivePickTask());
        $this->assertSame(25.0, (float) StockReservation::query()->active()->where('level', ReservationLevel::Soft->value)
            ->forDocument('transfer', $trf->id)->sum('qty_base'), 'Reservasi lunak di gudang asal tetap memegang barang.');

        $this->gagal(fn () => app(CreatePickTask::class)->forTransfer($trf, $this->makeUser('warehouse_head')), 'BR-SJ-01', ShipmentRuleException::class);

        // Batal saat masih `approved` tanpa PCK: reservasi dilepas.
        $batal = $this->trf($this->gudang, $this->bks, [['item_id' => $this->baut->id, 'qty_base' => 5]]);
        app(CancelTransfer::class)->handle($batal, $this->alasan(ReasonContext::Cancel), null, $this->makeUser('warehouse_head'));
        $this->assertSame(0, StockReservation::query()->active()->forDocument('transfer', $batal->id)->count());

        $this->binA->forceFill(['bin_status' => BinStatus::Active])->save();
        $pck = app(CreatePickTask::class)->forTransfer($trf, $this->makeUser('warehouse_head'));

        $this->assertSame(TransferStatus::InProgress, $trf->refresh()->status);
        $this->assertSame(0, StockReservation::query()->active()->forDocument('transfer', $trf->id)->count());
        $this->assertSame(25.0, (float) $pck->lines()->sum('qty_allocated'));

        // PCK dibatalkan → TRF tetap diproses; PCK baru hanya untuk sisa yang belum dialokasikan.
        app(ProcessPickTask::class)->cancel($pck, $this->alasan(ReasonContext::Cancel), $this->makeUser('warehouse_head'));
        $baru = app(CreatePickTask::class)->forTransfer($trf->refresh(), $this->makeUser('warehouse_head'));
        $this->assertSame(25.0, (float) $baru->lines()->sum('qty_allocated'));
    }

    #[Test]
    public function tc_trf_06_rantai_penuh_antar_gudang_sampai_selesai(): void
    {
        $trf = $this->trf($this->gudang, $this->bks, [['item_id' => $this->baut->id, 'qty_base' => 40]]);
        $pck = $this->jalankanPck($this->pckTrf($trf));

        $sj = $this->sjDari($pck, $this->bks);
        $this->assertSame(40.0, (float) $trf->lines()->first()->qty_shipped, 'SJ berangkat mencatat terkirim di baris TRF.');
        $this->assertSame(40.0, $this->saldo($this->binSistem($this->gudang, BinType::InTransit), $this->baut), 'BR-STK-13: milik gudang asal.');

        $sj = $this->terimaSj($sj);
        $this->assertSame(TransferStatus::InProgress, $trf->refresh()->status, 'Bukti terima belum menyelesaikan TRF.');

        $grn = $this->grnTransferSelesai($sj, $this->bks);
        $trf->refresh();

        $this->assertSame(TransferStatus::Completed, $trf->status, 'Katalog §2.7: GRN tujuan selesai → TRF selesai.');
        $this->assertNotNull($trf->completed_at);
        $this->assertSame(40.0, (float) $trf->lines()->first()->qty_received);
        $this->assertSame(40.0, $this->saldo($this->binSistem($this->bks, BinType::Receiving), $this->baut));

        $kejadian = StockEvent::query()->where('source_type', 'goods_receipt')->where('source_id', $grn->id)->sole();
        $this->assertSame('stock_transferred', $kejadian->event_type->value);
        $this->assertSame((int) $this->gudang->id, (int) $kejadian->payload['from_warehouse_id']);

        $this->putSelesai($grn, $this->binBks);
        $this->assertSame(40.0, app(StockLedger::class)->availableQty($this->baut->id, $this->bks->id));
        $this->assertSame(60.0, app(StockLedger::class)->availableQty($this->baut->id, $this->gudang->id));
    }

    #[Test]
    public function tc_trf_07_sj_wajib_ke_gudang_tujuan_trf(): void
    {
        $trf = $this->trf($this->gudang, $this->bks, [['item_id' => $this->baut->id, 'qty_base' => 10]]);
        $pck = $this->jalankanPck($this->pckTrf($trf));

        $data = ['shipment_method' => 'self_delivered', 'carried_by_name' => 'Pak Ujang'];

        $this->gagal(fn () => app(CreateShipment::class)->handle([$pck->id], $data + [
            'destination_type' => 'project_client', 'destination_project_id' => $this->proyek->id,
        ], $this->makeUser()), 'BR-SJ-09', ShipmentRuleException::class);

        $this->gagal(fn () => app(CreateShipment::class)->handle([$pck->id], $data + [
            'destination_type' => 'warehouse', 'destination_warehouse_id' => $this->krw1->id,
        ], $this->makeUser()), 'BR-SJ-09', ShipmentRuleException::class);

        $sj = app(CreateShipment::class)->handle([$pck->id], $data + [
            'destination_type' => 'warehouse', 'destination_warehouse_id' => $this->bks->id,
        ], $this->makeUser());

        $this->assertSame('transfer', $sj->lines()->first()->ownership_effect->value);
    }

    #[Test]
    public function tc_trf_08_dalam_proyek_jalur_ringan_dan_antar_proyek(): void
    {
        // Aturan TRF antar gudang tidak mengena transfer dalam proyek (kondisi proyek).
        $this->stok($this->binKrw1, $this->baut, 12);
        $fajar = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->krw1->id);
        $this->assignRole($fajar, 'warehouse_staff', ScopeType::Warehouse, $this->krw2->id);

        $trf = $this->trf($this->krw1, $this->krw2, [['item_id' => $this->baut->id, 'qty_base' => 12]], $fajar);

        $this->assertSame(TransferKind::WithinProject, $trf->kind());
        $this->assertSame((int) $this->proyek->id, (int) $trf->from_project_id);
        $this->assertSame((int) $trf->from_project_id, (int) $trf->to_project_id, 'A-50: from_project_id = to_project_id.');
        $this->assertSame(TransferStatus::InProgress, $trf->status, 'Tanpa aturan → tanpa approval (BR-RET-02).');

        $pck = $this->jalankanPck($this->pckTrf($trf));
        $sj = $this->sjDari($pck, $this->krw2, ['shipment_method' => 'self_delivered', 'carried_by_name' => 'Fajar', 'vehicle_id' => null, 'driver_id' => null]);
        $this->assertSame('self_delivered', $sj->shipment_method->value);

        $grn = $this->grnTransferSelesai($this->terimaSj($sj), $this->krw2);
        $this->assertSame(TransferStatus::Completed, $trf->refresh()->status);
        $this->assertNull($grn->lines()->first()->qc_result, 'GRN tujuan = konfirmasi PIC titik tanpa QC (A-50).');

        // Antar proyek: Gudang Site proyek lain.
        $lain = $this->makeProject();
        $krw9 = $this->buatGudangSite('KRW9', $lain);
        $antar = $this->trf($this->gudang, $krw9, [['item_id' => $this->baut->id, 'qty_base' => 1]]);
        $this->assertSame(TransferKind::BetweenProjects, $antar->kind());
        $this->assertSame((int) $lain->id, (int) $antar->to_project_id);
    }

    #[Test]
    public function tc_trf_09_cakupan_gudang_asal_atau_tujuan(): void
    {
        $trf = $this->trf($this->gudang, $this->bks, [['item_id' => $this->baut->id, 'qty_base' => 3]]);

        $kepalaBks = $this->makeUser('warehouse_head', ScopeType::Warehouse, $this->bks->id);
        $stafKrw = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->krw1->id);

        $this->actingAs($kepalaBks);
        $this->assertTrue(Transfer::query()->whereKey($trf->id)->exists(), 'Gudang tujuan membaca TRF masuknya.');

        $this->actingAs($stafKrw);
        $this->assertFalse(Transfer::query()->whereKey($trf->id)->exists());
        $this->get($this->tenantUrl('transfers/'.$trf->id))->assertNotFound();
    }
}

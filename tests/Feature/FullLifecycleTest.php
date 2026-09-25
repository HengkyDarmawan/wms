<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\User;
use App\Domain\Adjustment\Actions\ApproveStockAdjustment;
use App\Domain\Adjustment\Actions\CreateStockAdjustment;
use App\Domain\Adjustment\Enums\StockAdjustmentStatus;
use App\Domain\Approval\Actions\DecideApproval;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Models\ApprovalTask;
use App\Domain\Asset\Enums\AssetHandoverStatus;
use App\Domain\Conversion\Enums\ConversionStatus;
use App\Domain\Count\Actions\CreateStockCount;
use App\Domain\Count\Actions\ReconcileStockCount;
use App\Domain\Count\Actions\RecordCount;
use App\Domain\Count\Actions\StartStockCount;
use App\Domain\Count\Enums\StockCountStatus;
use App\Domain\Count\Models\CountAssignment;
use App\Domain\Issue\Enums\MaterialIssueStatus;
use App\Domain\Master\Enums\AssetState;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Notification\Models\Notification;
use App\Domain\Receipt\Actions\CompleteGoodsReceipt;
use App\Domain\Receipt\Actions\CompletePutaway;
use App\Domain\Receipt\Enums\QcResult;
use App\Domain\Request\Actions\RespondDeliveryReceipt;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Return\Enums\GoodsReturnStatus;
use App\Domain\Shipment\Enums\ReceiptConfirmation;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Support\StockLedger;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Asset\Concerns\AssetFixtures;
use Tests\Feature\Conversion\Concerns\ConversionFixtures;
use Tests\Feature\Issue\Concerns\IssueFixtures;
use Tests\TenantTestCase;

/**
 * TC-E2E-01 — rantai Fase 1 dari ujung ke ujung dengan aksi sungguhan (butir
 * Penutup prompt serah terima): GRN vendor → QC → put-away → REQ jual putus →
 * PCK → SJ → bukti terima → konfirmasi pemohon → kirim ke Gudang Site → ISU →
 * retur sisa → pilah → konversi pipa → opname → penyesuaian → aset pinjam &
 * kembali → periksa → pilah. Di akhir, seluruh saldo harus sama persis dengan
 * hasil membangun ulang dari kartu stok (BR-STK-01, BR-LED-01) dan tidak ada
 * saldo negatif (BR-STK-06).
 */
class FullLifecycleTest extends TenantTestCase
{
    use AssetFixtures;
    use ConversionFixtures;
    use IssueFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanAset();

        $this->pipa->forceFill(['is_cuttable' => true, 'min_offcut_length' => 0.5, 'kerf' => 0.005])->save();
        $this->batang = $this->potongan($this->binA, 6.0);
    }

    #[Test]
    public function tc_e2e_01_rantai_fase_1_konsisten_dengan_kartu_stok(): void
    {
        $ledger = app(StockLedger::class);
        $kepala = $this->makeUser('warehouse_head', ScopeType::Warehouse, $this->gudang->id);

        // 1. GRN vendor kabel (wajib QC) → lolos QC → selesai → put-away.
        $grn = $this->grnDiterima([['item_id' => $this->kabel->id, 'qty_received' => 50]]);
        $this->assertSame(0.0, $ledger->availableQty($this->kabel->id, $this->gudang->id), 'Karantina belum tersedia.');
        $this->qc($grn, 0, QcResult::Passed, false);
        $grn = app(CompleteGoodsReceipt::class)->handle($grn->refresh(), $this->makeUser());
        // Bin A & B sudah berisi barang lain, jadi tidak ada saran: staf memilih bin A.
        $put = $grn->putawayTasks()->sole();
        app(CompletePutaway::class)->handle($put, $put->lines->mapWithKeys(fn ($l) => [$l->id => ['bin_id' => $this->binA->id, 'override_reason' => 'Rak kabel']])->all(), $this->makeUser());
        $this->assertSame(50.0, $ledger->availableQty($this->kabel->id, $this->gudang->id));

        // 2. REQ jual putus kabel 30 → PCK → SJ → diterima → pemohon mengonfirmasi (BR-REQ-10).
        $sj = $this->terimaSj($this->terkirimKeKlien($this->kabel, 30));
        $this->assertSame(ShipmentStatus::Delivered, $sj->status);
        $req = MaterialRequest::query()->findOrFail($sj->lines()->first()->pickTaskLine->pickTask->source_id);
        $this->assertSame(MaterialRequestStatus::Completed, $req->status);
        $pemohon = User::query()->findOrFail($req->requester_id);
        app(RespondDeliveryReceipt::class)->confirm($req, $sj->proof, $pemohon);
        $this->assertSame(ReceiptConfirmation::Confirmed, $sj->proof->refresh()->confirmation);
        $this->assertTrue(Notification::query()->where('user_id', $pemohon->id)->where('type', 'delivery.received')->exists());

        // 3. Baut 40 dikirim ke Gudang Site KRW1 (REQ → SJ → GRN transfer → put-away).
        $this->kirimKeSite($this->baut, 40);
        $this->assertSame(40.0, $this->saldo($this->binKrw1, $this->baut));

        // 4. ISU 25 di site, dikonfirmasi.
        $isu = $this->konfirmasi($this->isu([['key' => $this->kunciIsu($this->binKrw1, $this->baut), 'qty_base' => 25, 'work_note' => 'Pemasangan']]));
        $this->assertSame(MaterialIssueStatus::Confirmed, $isu->status);
        $this->assertSame(15.0, $this->saldo($this->binKrw1, $this->baut));

        // 5. Sisa 15 diretur ke CKG, diterima GRN retur, dipilah 13 layak & 2 rusak.
        $ret = $this->ret([['key' => $this->kunciSite($this->binKrw1, $this->baut), 'qty_base' => 15]]);
        $this->grnRetur($ret);
        $ret = $this->pilah($ret, [$ret->lines()->sole()->id => [
            ['sorting' => 'good', 'qty' => 13, 'target_bin_id' => $this->binB->id],
            ['sorting' => 'damaged', 'qty' => 2, 'reason_code_id' => $this->alasan(ReasonContext::Damage)],
        ]]);
        $this->assertSame(GoodsReturnStatus::Sorted, $ret->status);
        $this->assertSame(0.0, $this->saldo($this->binKrw1, $this->baut));
        $this->assertSame(13.0, $this->saldo($this->binB, $this->baut));

        // 6. Konversi: pipa 6 m dipotong 2 × 2,5 m + offcut 0,99 + kerf 0,01.
        $cnv = $this->selesai($this->cnvPotong());
        $this->assertSame(ConversionStatus::Completed, $cnv->status);

        // 7. Opname bulanan bin A & B tanpa selisih → direkonsiliasi → disetujui (lapis minimum Kepala Gudang).
        [$hitung1, $hitung2] = [$this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->gudang->id), $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->gudang->id)];
        $sesi = app(CreateStockCount::class)->handle([
            'count_type' => 'monthly', 'warehouse_ids' => [$this->gudang->id], 'bin_ids' => [$this->binA->id, $this->binB->id],
            'freeze_bins' => true, 'team_user_ids' => [$hitung1->id, $hitung2->id],
        ], $kepala);
        $sesi = app(StartStockCount::class)->handle($sesi, $kepala);

        foreach (CountAssignment::query()->where('stock_count_id', $sesi->id)->where('round', 1)->get() as $t) {
            $penghitung = User::query()->findOrFail($t->counter_user_id);
            app(RecordCount::class)->save($t, $t->linesQuery()->get()->mapWithKeys(fn ($l) => [$l->id => (float) $l->system_qty])->all(), $penghitung);
            app(RecordCount::class)->finish($t->refresh(), $penghitung);
        }

        // SoD (BR-OPN-09): yang merekonsiliasi tidak menyetujui; Kepala Gudang kedua yang memutus.
        $this->makeUser('warehouse_head', ScopeType::Warehouse, $this->gudang->id);
        $sesi = app(ReconcileStockCount::class)->handle($sesi->refresh(), $kepala);
        $snapshot = ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::StockCount, $sesi->id)->latest('id')->first();

        if ($snapshot !== null && $sesi->status !== StockCountStatus::Closed) {
            $penyetuju = User::query()->findOrFail(ApprovalTask::query()->open()->where('approval_snapshot_id', $snapshot->id)->value('approver_user_id'));
            app(DecideApproval::class)->approveDocument(ApprovalDocumentType::StockCount, $sesi->id, $penyetuju);
        }

        $this->assertSame(StockCountStatus::Closed, $sesi->refresh()->status);

        // 8. Penyesuaian manual: baut rusak 2 di bin Retur dikeluarkan (ADJ lewat approval).
        $adj = app(CreateStockAdjustment::class)->handle([
            'warehouse_id' => $this->gudang->id,
            'reason_code_id' => ReasonCode::query()->where('context', ReasonContext::Adjustment->value)->value('id'),
        ], [['direction' => 'out', 'bin_id' => $this->binA->id, 'item_id' => $this->baut->id, 'qty' => 2]], $hitung1);
        $adj = app(ApproveStockAdjustment::class)->approve($adj, $kepala);
        $this->assertSame(StockAdjustmentStatus::Posted, $adj->refresh()->status);

        // 9. Aset: genset dipinjam → kembali → diperiksa → dipilah layak.
        $this->pinjamkan();
        $this->assertSame(AssetState::OnLoan, $this->gns->refresh()->asset_state);
        $kembali = $this->kembalikan();
        $this->periksa($this->ast());
        $this->pilah($kembali, [$kembali->lines()->sole()->id => [['sorting' => 'good', 'qty' => 1, 'target_bin_id' => $this->binB->id]]]);
        $this->assertSame(AssetHandoverStatus::Inspected, $this->ast()->status);
        $this->assertSame(AssetState::Available, $this->gns->refresh()->asset_state);

        // 10. Konsistensi: saldo = bangun ulang kartu stok; tidak ada saldo negatif.
        $dariKartu = array_filter($ledger->rebuildFromLedger(), fn ($n) => abs($n) > 0.00005);
        $dariSaldo = StockBalance::query()->where('qty_base', '!=', 0)->get()
            ->mapWithKeys(fn (StockBalance $b) => [implode('|', [$b->item_id, $b->bin_id, (int) $b->lot_id, (int) $b->serial_id, (int) $b->piece_id, $b->stock_status->value]) => round((float) $b->qty_base, 4)])
            ->all();

        ksort($dariKartu);
        ksort($dariSaldo);
        $this->assertSame($dariKartu, $dariSaldo, 'Saldo harus bisa dibangun ulang persis dari kartu stok.');
        $this->assertSame(0, StockBalance::query()->where('qty_base', '<', 0)->count());
        // Baut: 100 − 40 ke site; di site 25 dipakai, 15 kembali (13 layak ke bin B, 2 rusak di bin Retur); ADJ −2 dari bin A.
        $baut = fn (string $kondisi) => round((float) StockBalance::query()->where('item_id', $this->baut->id)->where('stock_status', $kondisi)->sum('qty_base'), 4);
        $this->assertSame(58.0 + 13.0, $baut('available'));
        $this->assertSame(2.0, $baut('damaged'));
    }
}

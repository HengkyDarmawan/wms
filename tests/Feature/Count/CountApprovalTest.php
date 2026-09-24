<?php

declare(strict_types=1);

namespace Tests\Feature\Count;

use App\Domain\Adjustment\Enums\AdjustmentOrigin;
use App\Domain\Adjustment\Enums\StockAdjustmentStatus;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApprovalSnapshotStatus;
use App\Domain\Approval\Exceptions\ApprovalRuleException;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Count\Actions\ApproveStockCount;
use App\Domain\Count\Actions\ReconcileStockCount;
use App\Domain\Count\Actions\RecordCountRootCause;
use App\Domain\Count\Enums\StockCountStatus;
use App\Domain\Count\Exceptions\CountRuleException;
use App\Domain\Count\Models\StockCount;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Stock\Actions\LockStockPeriod;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Enums\BinStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalFixtures;
use Tests\Feature\Count\Concerns\CountFixtures;
use Tests\TenantTestCase;

/**
 * TC-OPN-10 s.d. TC-OPN-14 dan TC-OPN-16 — rekonsiliasi, approval tingkat
 * sesi lewat mesin approval, pemisahan tugas, posting ADJ, penutupan, dan
 * kunci periode (BR-OPN-06, BR-OPN-07, BR-OPN-09, BR-OPN-10, BR-STK-15).
 */
class CountApprovalTest extends TenantTestCase
{
    use ApprovalFixtures;
    use CountFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanOpname();
    }

    /** Sesi dua bin dengan selisih besar pada baut (−20) dan genset hilang (−1). */
    private function sesiSelisih(array $data = [], $pembuat = null): StockCount
    {
        $sesi = $this->sesiBerjalan($data, $pembuat);

        return $this->hitungPutaran($sesi, 1, [
            $this->baris($sesi, $this->baut)->id => 80,
            $this->baris($sesi, $this->genset)->id => 0,
        ]);
    }

    private function akar(StockCount $sesi): void
    {
        foreach ([$this->baut, $this->genset] as $item) {
            app(RecordCountRootCause::class)->handle($this->baris($sesi, $item), 'damaged_lost', 'Ditemukan rusak', $this->kepala);
        }
    }

    #[Test]
    public function tc_opn_10_rekonsiliasi_menuntut_akar_masalah_dan_membuat_draf_adj(): void
    {
        $sesi = $this->sesiBerjalan();
        $this->tolakCount(fn () => app(ReconcileStockCount::class)->handle($sesi, $this->kepala), 'BR-OPN-05');

        $sesi = $this->hitungPutaran($sesi, 1, [
            $this->baris($sesi, $this->baut)->id => 80,
            $this->baris($sesi, $this->genset)->id => 0,
        ]);

        $this->tolakCount(fn () => app(ReconcileStockCount::class)->handle($sesi, $this->kepala), 'BR-OPN-07');
        $this->akar($sesi);

        $sesi = app(ReconcileStockCount::class)->handle($sesi, $this->kepala);

        $this->assertSame(StockCountStatus::Reconciling, $sesi->status);
        $this->assertSame($this->kepala->id, (int) $sesi->submitted_by);

        $adj = StockAdjustment::query()->where('stock_count_id', $sesi->id)->sole();
        $this->assertSame(AdjustmentOrigin::Count, $adj->origin);
        $this->assertSame(StockAdjustmentStatus::Submitted, $adj->status, 'ADJ opname tidak masuk approval sendiri (Katalog §2.12).');
        $this->assertEqualsCanonicalizing([-20.0, -1.0], $adj->lines()->pluck('qty_delta')->map(fn ($v) => (float) $v)->all());
        $this->assertSame($adj->id, (int) \DB::table('stock_count_warehouses')->where('stock_count_id', $sesi->id)->value('stock_adjustment_id'));
        $this->assertNull(ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::StockAdjustment, $adj->id)->first());

        // Tanpa aturan: lapis minimum Kepala Gudang (A-96). Kepala Gudang CKG
        // sendiri yang mengajukan, jadi lapisnya dialihkan ke atasannya (BR-APR-03).
        $this->assertSame([$this->manajemen->id], $this->tugasTerbukaSesi($sesi));
        $this->assertSame(BinStatus::Frozen, $this->binA->refresh()->bin_status);
    }

    #[Test]
    public function tc_opn_11_disetujui_adj_diposting_bin_dibuka_sesi_ditutup(): void
    {
        $this->binA->forceFill(['count_flag' => true])->save();

        // Kepala Gudang lain (semua gudang) menjadi approver karena perekonsiliasi = pengaju (BR-APR-03).
        $sesi = $this->sesiSelisih();
        $this->akar($sesi);
        $sesi = app(ReconcileStockCount::class)->handle($sesi, $this->auditor);

        $this->assertSame([$this->kepala->id], $this->tugasTerbukaSesi($sesi));
        $sesi = app(ApproveStockCount::class)->approve($sesi, $this->kepala);

        $this->assertSame(StockCountStatus::Closed, $sesi->status, 'approved → closed otomatis.');
        $this->assertSame($this->kepala->id, (int) $sesi->approved_by);
        $this->assertNotNull($sesi->closed_at);

        $adj = StockAdjustment::query()->where('stock_count_id', $sesi->id)->sole();
        $this->assertSame(StockAdjustmentStatus::Posted, $adj->status);
        $this->assertSame(80.0, $this->saldoBin($this->binA, $this->baut));
        $this->assertSame(0.0, $this->saldoBin($this->binB, $this->genset));
        $this->assertSame(50.0, $this->saldoBin($this->binB, $this->semen), 'Baris tanpa selisih tidak bergerak.');

        $gerak = StockMovement::query()->where('document_type', 'stock_adjustment')->where('document_id', $adj->id)->get();
        $this->assertCount(2, $gerak);
        $this->assertTrue($gerak->every(fn ($m) => $m->to_bin_id === null), 'Selisih kurang = keluar dari bin.');

        $kejadian = StockEvent::query()->where('source_type', 'stock_adjustment')->where('source_id', $adj->id)->get();
        $this->assertCount(2, $kejadian);
        $this->assertTrue($kejadian->every(fn ($e) => $e->event_type === StockEventType::StockAdjusted));
        $this->assertSame($sesi->number, $kejadian->first()->payload['count_session_ref'], 'Matriks §14: count_session_ref.');

        foreach ([$this->binA, $this->binB] as $bin) {
            $this->assertSame(BinStatus::Active, $bin->refresh()->bin_status, 'Bin dibuka.');
            $this->assertNull($bin->frozen_by_count_id);
        }

        $this->assertFalse($this->binA->count_flag, 'Penanda hitung dilepas setelah dihitung (A-67).');

        // Saldo tetap sama dengan penjumlahan kartu stok (BR-STK-01).
        $bangun = app(StockLedger::class)->rebuildFromLedger($this->baut->id);
        $this->assertSame(80.0, (float) array_sum(array_filter($bangun, fn ($v, $k) => str_starts_with($k, $this->baut->id.'|'.$this->binA->id.'|'), ARRAY_FILTER_USE_BOTH)));
    }

    #[Test]
    public function tc_opn_12_ditolak_tetap_rekonsiliasi_lalu_diajukan_ulang(): void
    {
        $sesi = $this->sesiSelisih();
        $this->akar($sesi);
        $sesi = app(ReconcileStockCount::class)->handle($sesi, $this->auditor);
        $adjLama = StockAdjustment::query()->where('stock_count_id', $sesi->id)->sole();

        // Data yang sedang diputus tidak boleh berubah (BR-APR-01).
        $this->tolakCount(fn () => app(RecordCountRootCause::class)->handle($this->baris($sesi, $this->baut), 'mispick', null, $this->kepala), 'BR-APR-01');

        $this->tolakCount(fn () => app(ApproveStockCount::class)->reject($sesi, null, null, $this->kepala), 'BR-GEN-11');
        $sesi = app(ApproveStockCount::class)->reject($sesi, $this->alasanId(ReasonContext::Reject), 'Hitung ulang rak A', $this->kepala);

        $this->assertSame(StockCountStatus::Reconciling, $sesi->status, 'Katalog §2.13 tanpa status rejected (A-97).');
        $this->assertNotNull($sesi->reject_reason_id);
        $this->assertSame(BinStatus::Frozen, $this->binA->refresh()->bin_status);
        $this->assertSame(StockAdjustmentStatus::Submitted, $adjLama->refresh()->status);
        $this->assertSame(100.0, $this->saldoBin($this->binA, $this->baut), 'Belum ada stok bergerak.');

        app(RecordCountRootCause::class)->handle($this->baris($sesi, $this->baut), 'mispick', 'Salah ambil saat PCK', $this->kepala);
        $sesi = app(ReconcileStockCount::class)->handle($sesi, $this->auditor);

        $this->assertSame(StockAdjustmentStatus::Cancelled, $adjLama->refresh()->status, 'Draf lama diganti.');
        $baru = StockAdjustment::query()->where('stock_count_id', $sesi->id)->where('status', 'submitted')->sole();
        $this->assertNotSame($adjLama->id, $baru->id);
        $this->assertSame(2, ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::StockCount, $sesi->id)->count());

        app(ApproveStockCount::class)->approve($sesi, $this->kepala);
        $this->assertSame(StockAdjustmentStatus::Posted, $baru->refresh()->status);
        $this->assertSame(StockAdjustmentStatus::Cancelled, $adjLama->refresh()->status);
        $this->assertSame(80.0, $this->saldoBin($this->binA, $this->baut));
    }

    #[Test]
    public function tc_opn_13_penghitung_dan_kepala_gudang_cakupan_tidak_menyetujui_sesi_tahunan(): void
    {
        $this->aturan(ApprovalDocumentType::StockCount, [$this->lapisRole('internal_auditor')], ['count_types' => ['annual', 'spot_check']], 10, 'OPN tahunan');

        // Kepala Gudang ikut menghitung sesi bulanan: tidak boleh menyetujui (BR-OPN-09).
        $bulanan = $this->sesiSelisih(['team_user_ids' => [$this->kepala->id]]);
        $this->akar($bulanan);
        $bulanan = app(ReconcileStockCount::class)->handle($bulanan, $this->auditor);
        $this->assertSame([$this->manajemen->id], $this->tugasTerbukaSesi($bulanan), 'Penghitung dilewati ke atasannya.');
        $this->tolakCount(fn () => app(ApproveStockCount::class)->approve($bulanan, $this->kepala), 'BR-OPN-09');

        // Bin cakupan masih beku sampai sesi itu diputus (BR-OPN-02).
        $this->tolakCount(fn () => $this->sesiBerjalan(['count_type' => 'annual']), 'BR-OPN-02');
        app(ApproveStockCount::class)->approve($bulanan, $this->manajemen);
        $this->masuk($this->baut, 20, $this->binA);
        $this->masuk($this->genset, 1, $this->binB, serialId: $this->serial->id);

        // Sesi tahunan: aturan menunjuk Auditor Internal; perekonsiliasi (Kepala Gudang) dilewati.
        $tahunan = $this->sesiSelisih(['count_type' => 'annual']);
        $this->akar($tahunan);
        $tahunan = app(ReconcileStockCount::class)->handle($tahunan, $this->kepala);
        $this->assertSame([$this->auditor->id], $this->tugasTerbukaSesi($tahunan));

        try {
            app(ApproveStockCount::class)->approve($tahunan, $this->kepala);
            $this->fail('Perekonsiliasi tidak boleh menyetujui.');
        } catch (ApprovalRuleException $e) {
            $this->assertSame('BR-APR-03', $e->rule);
        }

        app(ApproveStockCount::class)->approve($tahunan, $this->auditor);
        $this->assertSame(StockCountStatus::Closed, $tahunan->refresh()->status);
        $this->assertNull($tahunan->lock_date_set, 'Hanya sesi bulanan yang memajukan kunci periode.');
    }

    #[Test]
    public function tc_opn_13b_sesi_tahunan_tanpa_aturan_ke_auditor_bukan_kepala_gudang_cakupan(): void
    {
        $tahunan = $this->sesiSelisih(['count_type' => 'annual']);
        $this->akar($tahunan);

        $tahunan = app(ReconcileStockCount::class)->handle($tahunan, $this->auditor);

        // Lapis minimum sesi tahunan = Auditor Internal; satu-satunya auditor adalah pengaju → atasannya (Manajemen).
        $this->assertSame([$this->manajemen->id], $this->tugasTerbukaSesi($tahunan));
        $this->assertNotContains($this->kepala->id, $this->tugasTerbukaSesi($tahunan), 'Kepala Gudang cakupan tidak menyetujui sesi tahunan (BR-OPN-09).');

        $snapshot = ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::StockCount, $tahunan->id)->sole();
        $this->assertContains($this->kepala->id, $snapshot->requesterIds());
        $this->assertContains($this->staf1->id, $snapshot->requesterIds(), 'Penghitung masuk daftar SoD.');
    }

    #[Test]
    public function tc_opn_14_pemeriksaan_mendadak_tanpa_adj_dan_menandai_bin(): void
    {
        $this->aturan(ApprovalDocumentType::StockCount, [$this->lapisRole('internal_auditor')], ['count_types' => ['annual', 'spot_check']], 10, 'OPN audit');
        $auditor2 = $this->makeUser('internal_auditor');

        $spot = $this->sesiSelisih(['count_type' => 'spot_check'], $this->auditor);
        $this->assertTrue($spot->is_audit);
        $this->assertSame(BinStatus::Active, $this->binA->refresh()->bin_status, 'Tanpa pembekuan (BR-OPN-10).');

        $this->akar($spot);
        $spot = app(ReconcileStockCount::class)->handle($spot, $this->auditor);
        $this->assertSame(0, StockAdjustment::query()->where('stock_count_id', $spot->id)->count(), 'Spot check tidak membuat ADJ.');
        $this->assertSame([$auditor2->id], $this->tugasTerbukaSesi($spot));

        app(ApproveStockCount::class)->approve($spot, $auditor2);

        $this->assertSame(StockCountStatus::Closed, $spot->refresh()->status);
        $this->assertSame(100.0, $this->saldoBin($this->binA, $this->baut), 'Stok tidak bergerak.');
        $this->assertTrue($this->binA->refresh()->count_flag, 'Bin berselisih besar ditandai untuk sesi berikutnya (A-101).');
        $this->assertTrue($this->binB->refresh()->count_flag);
    }

    #[Test]
    public function tc_opn_14b_sesi_bulanan_ditutup_memajukan_kunci_periode(): void
    {
        $sesi = $this->sesiSelisih();
        $sesi->forceFill(['started_at' => now()->subDays(2)])->save();
        $this->akar($sesi);
        $sesi = app(ReconcileStockCount::class)->handle($sesi, $this->auditor);
        $sesi = app(ApproveStockCount::class)->approve($sesi, $this->kepala);

        $harapan = now()->subDays(3)->toDateString();
        $this->assertSame($harapan, app(LockStockPeriod::class)->current(), 'BR-STK-15: otomatis saat sesi bulanan ditutup.');
        $this->assertSame($harapan, $sesi->lock_date_set->toDateString());

        // Kunci tidak pernah mundur: sesi bulanan berikutnya yang lebih lama tidak mengubahnya.
        app(LockStockPeriod::class)->handle(now()->subDay()->toDateString(), null, $this->makeUser('company_admin'));
        $lagi = $this->sesiBerjalan(['bin_ids' => [$this->binB->id]]);
        $lagi->forceFill(['started_at' => now()->subDays(5)])->save();
        $lagi = $this->hitungPutaran($lagi, 1);
        $lagi = app(ReconcileStockCount::class)->handle($lagi, $this->auditor);
        $lagi = app(ApproveStockCount::class)->approve($lagi, $this->kepala);

        $this->assertSame(now()->subDay()->toDateString(), app(LockStockPeriod::class)->current());
        $this->assertNull($lagi->lock_date_set);
        $this->assertSame(0, StockAdjustment::query()->where('stock_count_id', $lagi->id)->count(), 'Tanpa selisih tanpa ADJ.');
        $this->assertSame(1, StockBalance::query()->where('bin_id', $this->binB->id)->where('item_id', $this->semen->id)->count());
    }

    #[Test]
    public function tc_opn_16_sesi_audit_ad_hoc_dari_auditor(): void
    {
        $sesi = $this->sesiSelisih(['count_type' => 'adhoc', 'team_user_ids' => [$this->staf1->id]], $this->auditor);
        $this->assertTrue($sesi->is_audit);
        $this->akar($sesi);

        $sesi = app(ReconcileStockCount::class)->handle($sesi, $this->auditor);

        // Sesi audit: Auditor Internal atau Manajemen, bukan Kepala Gudang cakupan (BR-OPN-09).
        $this->assertSame([$this->manajemen->id], $this->tugasTerbukaSesi($sesi));

        app(ApproveStockCount::class)->approve($sesi, $this->manajemen);
        $this->assertSame(StockCountStatus::Closed, $sesi->refresh()->status);
        $this->assertSame(80.0, $this->saldoBin($this->binA, $this->baut));
        $this->assertSame(ApprovalSnapshotStatus::Approved, ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::StockCount, $sesi->id)->sole()->status);
    }

    private function tolakCount(callable $aksi, string $aturan): void
    {
        try {
            $aksi();
            $this->fail('Seharusnya ditolak '.$aturan.'.');
        } catch (CountRuleException $e) {
            $this->assertSame($aturan, $e->rule, $e->getMessage());
        }
    }
}

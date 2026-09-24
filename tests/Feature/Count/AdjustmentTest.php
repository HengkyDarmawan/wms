<?php

declare(strict_types=1);

namespace Tests\Feature\Count;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Adjustment\Actions\ApproveStockAdjustment;
use App\Domain\Adjustment\Actions\CancelStockAdjustment;
use App\Domain\Adjustment\Actions\CreateStockAdjustment;
use App\Domain\Adjustment\Enums\StockAdjustmentStatus;
use App\Domain\Adjustment\Exceptions\AdjustmentRuleException;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApprovalSnapshotStatus;
use App\Domain\Approval\Enums\ApproverType;
use App\Domain\Approval\Enums\DecisionMode;
use App\Domain\Approval\Exceptions\ApprovalRuleException;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Count\Actions\ReconcileStockCount;
use App\Domain\Count\Actions\RecordCountRootCause;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\CompanySetting;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\Serial;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalFixtures;
use Tests\Feature\Count\Concerns\CountFixtures;
use Tests\TenantTestCase;

/**
 * TC-ADJ-01 s.d. TC-ADJ-09 — penyesuaian stok manual (Katalog §2.12): selalu
 * minimal satu lapis approval (A-09), posting lewat buku besar dengan
 * kejadian `stock_adjusted`, tolak/batal, pembalik sekali saja (BR-LED-05),
 * kunci periode (BR-STK-15).
 */
class AdjustmentTest extends TenantTestCase
{
    use ApprovalFixtures;
    use CountFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanOpname();
    }

    #[Test]
    public function tc_adj_01_adj_manual_selalu_masuk_approval_lapis_minimum(): void
    {
        $adj = $this->adjManual([['direction' => 'in', 'bin_id' => $this->binA->id, 'item_id' => $this->baut->id, 'qty' => 5]]);

        $this->assertMatchesRegularExpression('#^ADJ/CKG/\d{4}/0001$#', $adj->number);
        $this->assertSame(StockAdjustmentStatus::PendingApproval, $adj->status, 'Tanpa aturan tetap tidak disetujui otomatis (A-09).');
        $this->assertSame([$this->kepala->id], $this->tugasTerbukaAdj($adj), 'Lapis minimum: Kepala Gudang gudang ADJ.');
        $this->assertSame(5.0, (float) $adj->lines()->sole()->qty_delta);
        $this->assertSame(100.0, $this->saldoBin($this->binA, $this->baut), 'Belum ada stok bergerak sebelum disetujui.');
    }

    #[Test]
    public function tc_adj_02_validasi_baris(): void
    {
        $baris = fn (array $x = []) => [array_merge(['direction' => 'out', 'bin_id' => $this->binA->id, 'item_id' => $this->baut->id, 'qty' => 5], $x)];
        $transit = Bin::query()->where('warehouse_id', $this->gudang->id)->where('bin_type', BinType::InTransit->value)->firstOrFail();

        $this->tolak(fn () => $this->adjManual($baris(), null, null), 'BR-GEN-02');
        $this->tolak(fn () => $this->adjManual($baris(['qty' => 101])), 'BR-STK-06');
        $this->tolak(fn () => $this->adjManual($baris(['qty' => 0])), 'BR-LED-02');
        $this->tolak(fn () => $this->adjManual($baris(['bin_id' => $transit->id])), 'BR-SJ-10');
        $this->tolak(fn () => $this->adjManual($baris(['item_id' => $this->semen->id, 'bin_id' => $this->binB->id])), 'BR-LED-03');
        $this->tolak(fn () => $this->adjManual($baris(['direction' => 'in', 'item_id' => $this->semen->id, 'lot_no' => 'LOT-BARU'])), 'BR-STK-12');
        $this->tolak(fn () => $this->adjManual($baris(['direction' => 'in', 'item_id' => $this->genset->id, 'serial_no' => 'GNS-OPN-1'])), 'BR-LED-04');
        $this->tolak(fn () => $this->adjManual($baris(['direction' => 'in', 'item_id' => $this->pipa->id])), 'BR-STK-09');
        $this->tolak(fn () => $this->adjManual([['direction' => '', 'bin_id' => $this->binA->id, 'item_id' => $this->baut->id, 'qty' => 1]]), 'BR-GEN-11');
        $this->tolak(fn () => $this->adjManual([]), 'BR-GEN-11');

        // Bin beku menolak (BR-OPN-02).
        $this->sesiBerjalan(['bin_ids' => [$this->binA->id]]);
        $this->tolak(fn () => $this->adjManual($baris()), 'BR-OPN-02');
    }

    #[Test]
    public function tc_adj_03_disetujui_diposting_lewat_buku_besar_dengan_kejadian(): void
    {
        $adj = $this->adjManual([
            ['direction' => 'out', 'bin_id' => $this->binA->id, 'item_id' => $this->baut->id, 'qty' => 10],
            ['direction' => 'in', 'bin_id' => $this->binB->id, 'item_id' => $this->semen->id, 'qty' => 5, 'lot_no' => 'lot-baru-9', 'expiry_date' => now()->addYear()->toDateString()],
            ['direction' => 'in', 'bin_id' => $this->binB->id, 'item_id' => $this->genset->id, 'qty' => 1, 'serial_no' => 'GNS-BARU-9'],
            ['direction' => 'in', 'bin_id' => $this->binA->id, 'item_id' => $this->pipa->id, 'piece_length' => 2.5],
            ['direction' => 'out', 'bin_id' => $this->binA->id, 'item_id' => $this->pipa->id, 'piece_no' => 'P-OPN-1'],
            ['direction' => 'in', 'bin_id' => $this->binA->id, 'item_id' => $this->baut->id, 'qty' => 3, 'stock_status' => 'damaged'],
        ]);

        $this->assertNull(Lot::query()->where('lot_no', 'LOT-BARU-9')->first(), 'Lot baru belum dibuat sebelum diposting.');
        $this->assertNull(Serial::query()->where('serial_no', 'GNS-BARU-9')->first());
        $this->assertSame(6.0, (float) $adj->lines()->where('item_id', $this->pipa->id)->where('qty_delta', '<', 0)->value('qty_delta') * -1, 'Potongan keluar utuh.');

        $adj = app(ApproveStockAdjustment::class)->approve($adj, $this->kepala);

        $this->assertSame(StockAdjustmentStatus::Posted, $adj->status);
        $this->assertNotNull($adj->posted_at);
        $this->assertSame(90.0, $this->saldoBin($this->binA, $this->baut));
        $this->assertSame(3.0, $this->saldoBin($this->binA, $this->baut, StockStatus::Damaged));
        $this->assertSame(55.0, $this->saldoBin($this->binB, $this->semen));
        $this->assertSame(2.0, $this->saldoBin($this->binB, $this->genset));
        $this->assertSame(2.5, $this->saldoBin($this->binA, $this->pipa));
        $this->assertNotNull(Lot::query()->where('lot_no', 'LOT-BARU-9')->first());
        $this->assertNotNull(Piece::query()->where('origin_type', 'stock_adjustment')->where('origin_id', $adj->id)->first());

        $this->assertSame(0, $adj->lines()->whereNull('movement_id')->count(), 'Setiap baris menunjuk pergerakannya.');
        $kejadian = StockEvent::query()->where('source_type', 'stock_adjustment')->where('source_id', $adj->id)->get();
        $this->assertCount(6, $kejadian);
        $this->assertTrue($kejadian->every(fn ($e) => $e->event_type === StockEventType::StockAdjusted));
        $this->assertSame('FOUND', $kejadian->first()->payload['reason_code']);
        $this->assertSame('manual', $kejadian->first()->payload['adjustment_origin']);
        $this->assertArrayNotHasKey('count_session_ref', $kejadian->first()->payload);
    }

    #[Test]
    public function tc_adj_04_pengaju_tidak_boleh_memutus(): void
    {
        $adj = $this->adjManual([['direction' => 'in', 'bin_id' => $this->binA->id, 'item_id' => $this->baut->id, 'qty' => 1]], $this->kepala);

        try {
            app(ApproveStockAdjustment::class)->approve($adj, $this->kepala);
            $this->fail('Pengaju tidak boleh menyetujui.');
        } catch (AdjustmentRuleException $e) {
            $this->assertSame('BR-APR-03', $e->rule);
        }

        // Kepala Gudang satu-satunya dilewati; lapis dialihkan ke atasannya (BR-APR-03).
        $this->assertSame([$this->manajemen->id], $this->tugasTerbukaAdj($adj));

        try {
            app(ApproveStockAdjustment::class)->approve($adj, $this->staf2);
            $this->fail('Staf tanpa tugas tidak bisa memutus.');
        } catch (ApprovalRuleException $e) {
            $this->assertSame('BR-APR-01', $e->rule);
        }

        app(ApproveStockAdjustment::class)->approve($adj, $this->manajemen);
        $this->assertSame(StockAdjustmentStatus::Posted, $adj->refresh()->status);
    }

    #[Test]
    public function tc_adj_05_dua_lapis_bila_di_atas_100_unit(): void
    {
        $this->aturan(ApprovalDocumentType::StockAdjustment, [
            $this->lapis(ApproverType::WarehouseHead),
            $this->lapisRole('management'),
        ], ['line_qty_min' => 100.0001], 10, 'ADJ > 100');
        $this->aturan(ApprovalDocumentType::StockAdjustment, [$this->lapis(ApproverType::WarehouseHead)], [], 100, 'ADJ lainnya');

        $kecil = $this->adjManual([['direction' => 'in', 'bin_id' => $this->binA->id, 'item_id' => $this->baut->id, 'qty' => 100]]);
        $this->assertSame('ADJ lainnya', ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::StockAdjustment, $kecil->id)->sole()->rule_name);

        $besar = $this->adjManual([['direction' => 'in', 'bin_id' => $this->binA->id, 'item_id' => $this->baut->id, 'qty' => 150]]);
        $this->assertSame([$this->kepala->id], $this->tugasTerbukaAdj($besar));

        try {
            app(ApproveStockAdjustment::class)->approve($besar, $this->manajemen);
            $this->fail('Lapis 2 belum aktif.');
        } catch (ApprovalRuleException $e) {
            $this->assertSame('BR-APR-01', $e->rule);
        }

        app(ApproveStockAdjustment::class)->approve($besar, $this->kepala);
        $this->assertSame(StockAdjustmentStatus::PendingApproval, $besar->refresh()->status, 'Menunggu lapis 2.');
        $this->assertSame(100.0, $this->saldoBin($this->binA, $this->baut));

        app(ApproveStockAdjustment::class)->approve($besar, $this->manajemen);
        $this->assertSame(StockAdjustmentStatus::Posted, $besar->refresh()->status);
        $this->assertSame(250.0, $this->saldoBin($this->binA, $this->baut));
    }

    #[Test]
    public function tc_adj_06_ditolak_tanpa_pergerakan(): void
    {
        $adj = $this->adjManual([['direction' => 'out', 'bin_id' => $this->binA->id, 'item_id' => $this->baut->id, 'qty' => 10]]);

        $this->tolak(fn () => app(ApproveStockAdjustment::class)->reject($adj, null, null, $this->kepala), 'BR-GEN-11');

        $adj = app(ApproveStockAdjustment::class)->reject($adj, $this->alasanId(ReasonContext::Reject), 'Hitung ulang dulu', $this->kepala);

        $this->assertSame(StockAdjustmentStatus::Rejected, $adj->status);
        $this->assertNotNull($adj->reject_reason_id);
        $this->assertSame(100.0, $this->saldoBin($this->binA, $this->baut));
        $this->assertSame(0, StockMovement::query()->where('document_type', 'stock_adjustment')->count());
    }

    #[Test]
    public function tc_adj_07_batal_sebelum_disetujui_dan_adj_opname_tidak_bisa(): void
    {
        $adj = $this->adjManual([['direction' => 'in', 'bin_id' => $this->binA->id, 'item_id' => $this->baut->id, 'qty' => 1]]);
        $aksi = app(CancelStockAdjustment::class);

        $this->tolak(fn () => $aksi->handle($adj, null, null, $this->staf1), 'BR-GEN-11');
        $adj = $aksi->handle($adj, $this->alasanId(ReasonContext::Cancel), null, $this->staf1);

        $this->assertSame(StockAdjustmentStatus::Cancelled, $adj->status);
        $this->assertSame(ApprovalSnapshotStatus::Cancelled, ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::StockAdjustment, $adj->id)->sole()->status);
        $this->assertSame([], $this->tugasTerbukaAdj($adj));

        $posted = $this->adjManual([['direction' => 'in', 'bin_id' => $this->binA->id, 'item_id' => $this->baut->id, 'qty' => 1]]);
        app(ApproveStockAdjustment::class)->approve($posted, $this->kepala);
        $this->tolak(fn () => $aksi->handle($posted->refresh(), $this->alasanId(ReasonContext::Cancel), null, $this->staf1), 'BR-GEN-03');

        // ADJ hasil opname dikelola sesinya (BR-OPN-06).
        $sesi = $this->sesiBerjalan(['bin_ids' => [$this->binA->id]]);
        $sesi = $this->hitungPutaran($sesi, 1, [$this->baris($sesi, $this->baut)->id => 100.5]);
        $sesi = app(ReconcileStockCount::class)->handle($sesi, $this->auditor);
        $opn = StockAdjustment::query()->where('stock_count_id', $sesi->id)->sole();
        $this->tolak(fn () => $aksi->handle($opn, $this->alasanId(ReasonContext::Cancel), null, $this->kepala), 'BR-OPN-06');
    }

    #[Test]
    public function tc_adj_08_adj_pembalik_membalik_pergerakan_sekali_saja(): void
    {
        $asal = $this->adjManual([
            ['direction' => 'out', 'bin_id' => $this->binA->id, 'item_id' => $this->baut->id, 'qty' => 10],
            ['direction' => 'in', 'bin_id' => $this->binB->id, 'item_id' => $this->semen->id, 'qty' => 4, 'lot_no' => 'LOT-OPN-1'],
        ]);
        $aksi = app(CreateStockAdjustment::class);

        $this->tolak(fn () => $aksi->reverse($asal, $this->alasanId(ReasonContext::Adjustment, 'SYSTEM_FIX'), null, $this->staf1), 'BR-GEN-03');

        $asal = app(ApproveStockAdjustment::class)->approve($asal, $this->kepala);
        $this->assertSame(90.0, $this->saldoBin($this->binA, $this->baut));

        $balik = $aksi->reverse($asal, $this->alasanId(ReasonContext::Adjustment, 'SYSTEM_FIX'), null, $this->staf1);
        $this->assertSame($asal->id, (int) $balik->reversal_of_id);
        $this->assertSame(StockAdjustmentStatus::PendingApproval, $balik->status, 'Pembalik juga butuh approval (A-09).');
        $this->assertEqualsCanonicalizing([10.0, -4.0], $balik->lines()->pluck('qty_delta')->map(fn ($v) => (float) $v)->all());

        $this->tolak(fn () => $aksi->reverse($asal, $this->alasanId(ReasonContext::Adjustment, 'SYSTEM_FIX'), null, $this->staf1), 'BR-LED-05');

        $balik = app(ApproveStockAdjustment::class)->approve($balik, $this->kepala);
        $this->assertSame(StockAdjustmentStatus::Posted, $balik->status);
        $this->assertSame(100.0, $this->saldoBin($this->binA, $this->baut));
        $this->assertSame(50.0, $this->saldoBin($this->binB, $this->semen));

        foreach ($balik->lines as $l) {
            $gerak = StockMovement::query()->findOrFail($l->movement_id);
            $asalGerak = StockMovement::query()->findOrFail($l->reversalOfLine->movement_id);
            $this->assertSame((int) $asalGerak->id, (int) $gerak->reverses_movement_id, 'BR-LED-05: menunjuk pergerakan asal.');

            $kejadian = StockEvent::query()->where('payload->movement_id', $gerak->id)->sole();
            $kejadianAsal = StockEvent::query()->where('payload->movement_id', $asalGerak->id)->sole();
            $this->assertSame(StockEventType::StockAdjusted, $kejadian->event_type);
            $this->assertSame($kejadianAsal->event_id, $kejadian->reverses_event_id, 'Kejadian pembalik (matriks §14).');
        }

        $this->tolak(fn () => $aksi->reverse($balik, $this->alasanId(ReasonContext::Adjustment, 'SYSTEM_FIX'), null, $this->staf1), 'BR-LED-05');
    }

    #[Test]
    public function tc_adj_09_periode_terkunci_menahan_posting(): void
    {
        $adj = $this->adjManual([['direction' => 'in', 'bin_id' => $this->binA->id, 'item_id' => $this->baut->id, 'qty' => 5]]);

        CompanySetting::put('stock_lock_date', now()->toDateString());

        try {
            app(ApproveStockAdjustment::class)->approve($adj, $this->kepala);
            $this->fail('Periode terkunci harus menolak posting.');
        } catch (LedgerException $e) {
            $this->assertSame('BR-STK-15', $e->rule);
        }

        $this->assertSame(StockAdjustmentStatus::PendingApproval, $adj->refresh()->status, 'Keputusan dibatalkan seluruhnya.');
        $this->assertSame([$this->kepala->id], $this->tugasTerbukaAdj($adj));
        $this->assertSame(100.0, $this->saldoBin($this->binA, $this->baut));
    }

    private function tolak(callable $aksi, string $aturan): void
    {
        try {
            $aksi();
            $this->fail('Seharusnya ditolak '.$aturan.'.');
        } catch (AdjustmentRuleException $e) {
            $this->assertSame($aturan, $e->rule, $e->getMessage());
        } catch (\App\Domain\Count\Exceptions\CountRuleException $e) {
            $this->assertSame($aturan, $e->rule, $e->getMessage());
        }
    }
}

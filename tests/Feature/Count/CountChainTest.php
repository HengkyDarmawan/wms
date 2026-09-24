<?php

declare(strict_types=1);

namespace Tests\Feature\Count;

use App\Domain\Access\Models\User;
use App\Domain\Adjustment\Actions\ApproveStockAdjustment;
use App\Domain\Adjustment\Actions\CreateStockAdjustment;
use App\Domain\Adjustment\Enums\StockAdjustmentStatus;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Approval\Actions\DecideApproval;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Exceptions\ApprovalRuleException;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Models\ApprovalTask;
use App\Domain\Count\Actions\CreateStockCount;
use App\Domain\Count\Actions\ReconcileStockCount;
use App\Domain\Count\Actions\RecordCount;
use App\Domain\Count\Actions\RecordCountRootCause;
use App\Domain\Count\Actions\StartStockCount;
use App\Domain\Count\Enums\StockCountStatus;
use App\Domain\Count\Enums\VarianceClass;
use App\Domain\Count\Models\CountAssignment;
use App\Domain\Count\Models\CountLine;
use App\Domain\Count\Models\StockCount;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Serial;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use Database\Seeders\Tenant\DemoSeeder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-OPN-17 dan TC-ADJ-11 — rantai penuh di atas data demo (00-akun-uji):
 * sesi opname tahunan CKG → hitung buta Dedi & Eko → hitung ulang oleh orang
 * lain → akar masalah → rekonsiliasi Andi → approval Kartika (Auditor
 * Internal, aturan §5) → sesi ditutup, saldo terkoreksi lewat kartu stok dan
 * kejadian `stock_adjusted`; plus ADJ manual dua lapis Andi → Budi.
 */
class CountChainTest extends TenantTestCase
{
    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function saldo(string $bin, string $item, ?int $lotId = null, ?int $serialId = null): float
    {
        return round((float) StockBalance::query()
            ->where('bin_id', Bin::query()->where('code', $bin)->value('id'))
            ->where('item_id', Item::query()->where('code', $item)->value('id'))
            ->when($lotId !== null, fn ($q) => $q->where('lot_id', $lotId))
            ->when($serialId !== null, fn ($q) => $q->where('serial_id', $serialId))
            ->sum('qty_base'), 4);
    }

    #[Test]
    public function tc_opn_17_rantai_penuh_opname_tahunan_data_demo(): void
    {
        (new DemoSeeder)->run();

        $andi = $this->user('kagudang.ckg@demo.wms.test');
        $sari = $this->user('kagudang.bks@demo.wms.test');
        $dedi = $this->user('staf1.ckg@demo.wms.test');
        $eko = $this->user('staf2.ckg@demo.wms.test');
        $kartika = $this->user('auditor@demo.wms.test');
        $ckg = Warehouse::query()->where('code', 'CKG')->firstOrFail();

        $lot2609 = Lot::query()->where('lot_no', 'LOT-SMN-2609')->firstOrFail();
        $genset2 = Serial::query()->where('serial_no', 'GNS-5K-0002')->firstOrFail();

        // 1. Andi merencanakan dan memulai sesi tahunan seluruh gudang CKG.
        $sesi = app(CreateStockCount::class)->handle([
            'count_type' => 'annual',
            'warehouse_ids' => [$ckg->id],
            'freeze_bins' => true,
            'team_user_ids' => [$dedi->id, $eko->id],
        ], $andi);
        $sesi = app(StartStockCount::class)->handle($sesi, $andi);

        $this->assertSame(StockCountStatus::InProgress, $sesi->status);
        $this->assertSame(BinStatus::Frozen, Bin::query()->where('code', 'CKG-A-R01-L1-B01')->first()->bin_status);

        $bautLine = $this->line($sesi, 'CKG-A-R01-L1-B01', 'BAUT-M12');
        $semenLine = CountLine::query()->where('stock_count_id', $sesi->id)->where('lot_id', $lot2609->id)->firstOrFail();
        $gensetLine = CountLine::query()->where('stock_count_id', $sesi->id)->where('serial_id', $genset2->id)->firstOrFail();

        // 2. Hitung buta putaran 1: baut kurang 30 (3 % → sedang), semen lot 2609 kurang 200 (10 % → besar), genset 0002 tidak ada.
        $isian = [$bautLine->id => 970, $semenLine->id => 1800, $gensetLine->id => 0];
        $pertamaBaut = CountAssignment::query()->where('stock_count_id', $sesi->id)->where('bin_id', $bautLine->bin_id)->where('round', 1)->sole();
        $this->hitung($sesi, 1, $isian);

        $sesi->refresh();
        $this->assertSame(StockCountStatus::Recount, $sesi->status);
        $this->assertSame(VarianceClass::Moderate, $bautLine->refresh()->variance_class);
        $this->assertSame(VarianceClass::Major, $semenLine->refresh()->variance_class);
        $this->assertSame(VarianceClass::Major, $gensetLine->refresh()->variance_class);

        // 3. Hitung ulang oleh penghitung lain (BR-OPN-05): baut ternyata lengkap.
        $ulang = CountAssignment::query()->where('stock_count_id', $sesi->id)->where('round', 2)->sole();
        $this->assertNotSame((int) $pertamaBaut->counter_user_id, (int) $ulang->counter_user_id);
        $this->assertContains((int) $ulang->counter_user_id, [$dedi->id, $eko->id]);
        $this->hitung($sesi, 2, [$bautLine->id => 1000]);
        $this->assertNull($bautLine->refresh()->variance_class, 'Hasil hitung ulang menggantikan hitungan pertama.');

        // 4. Andi mengisi akar masalah lalu merekonsiliasi.
        app(RecordCountRootCause::class)->handle($semenLine, 'unrecorded_txn', 'Pemakaian proyek belum dicatat', $andi);
        app(RecordCountRootCause::class)->handle($gensetLine, 'damaged_lost', 'Hilang', $andi);
        $sesi = app(ReconcileStockCount::class)->handle($sesi, $andi);

        $snapshot = ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::StockCount, $sesi->id)->sole();
        $this->assertSame('OPN tahunan & pemeriksaan mendadak', $snapshot->rule_name, 'Aturan demo 00-akun-uji §5.');
        $tugas = ApprovalTask::query()->open()->where('approval_snapshot_id', $snapshot->id)->pluck('approver_user_id')->map(fn ($v) => (int) $v)->all();
        $this->assertSame([$kartika->id], $tugas, 'Sesi tahunan disetujui Auditor Internal (BR-OPN-09).');

        // 5. Penghitung, Kepala Gudang cakupan, dan Kepala Gudang lain tidak bisa memutus.
        foreach ([[$andi, 'BR-APR-03'], [$dedi, 'BR-APR-03'], [$sari, 'BR-APR-01']] as [$orang, $aturan]) {
            try {
                app(DecideApproval::class)->approveDocument(ApprovalDocumentType::StockCount, $sesi->id, $orang);
                $this->fail($orang->name.' tidak boleh menyetujui.');
            } catch (ApprovalRuleException $e) {
                $this->assertSame($aturan, $e->rule);
            }
        }

        $this->assertSame(1800.0 + 200.0, $this->saldo('CKG-A-R01-L1-B02', 'SEMEN-PCC-50', $lot2609->id), 'Belum ada stok bergerak sebelum disetujui.');

        // 6. Kartika menyetujui dari kotak tugas → sesi ditutup, ADJ diposting.
        app(DecideApproval::class)->approveDocument(ApprovalDocumentType::StockCount, $sesi->id, $kartika);
        $sesi->refresh();

        $this->assertSame(StockCountStatus::Closed, $sesi->status);
        $this->assertSame($kartika->id, (int) $sesi->approved_by);
        $this->assertNull($sesi->lock_date_set, 'Kunci periode otomatis hanya untuk sesi bulanan (BR-STK-15).');

        $adj = StockAdjustment::query()->where('stock_count_id', $sesi->id)->sole();
        $this->assertSame(StockAdjustmentStatus::Posted, $adj->status);
        $this->assertSame($ckg->id, (int) $adj->warehouse_id);
        $this->assertSame(2, $adj->lines()->count(), 'Baut tidak disesuaikan: hitung ulang cocok.');

        $this->assertSame(1800.0, $this->saldo('CKG-A-R01-L1-B02', 'SEMEN-PCC-50', $lot2609->id));
        $this->assertSame(0.0, $this->saldo('CKG-B-R01-L2-B01', 'GENSET-5KVA', serialId: $genset2->id));
        $this->assertSame(1000.0, $this->saldo('CKG-A-R01-L1-B01', 'BAUT-M12'));

        $kejadian = StockEvent::query()->where('source_type', 'stock_adjustment')->where('source_id', $adj->id)->get();
        $this->assertCount(2, $kejadian);
        $this->assertTrue($kejadian->every(fn ($e) => $e->event_type === StockEventType::StockAdjusted
            && $e->payload['count_session_ref'] === $sesi->number));

        $this->assertSame(0, Bin::query()->where('frozen_by_count_id', $sesi->id)->count(), 'Semua bin dibuka.');
    }

    #[Test]
    public function tc_adj_11_adj_demo_dua_lapis_andi_lalu_budi(): void
    {
        (new DemoSeeder)->run();

        $dedi = $this->user('staf1.ckg@demo.wms.test');
        $andi = $this->user('kagudang.ckg@demo.wms.test');
        $budi = $this->user('manajemen@demo.wms.test');
        $sari = $this->user('kagudang.bks@demo.wms.test');

        $adj = app(CreateStockAdjustment::class)->handle([
            'warehouse_id' => Warehouse::query()->where('code', 'CKG')->value('id'),
            'reason_code_id' => ReasonCode::query()->where('context', ReasonContext::Adjustment->value)->where('code', 'SYSTEM_FIX')->value('id'),
        ], [[
            'direction' => 'out',
            'bin_id' => Bin::query()->where('code', 'CKG-A-R01-L1-B01')->value('id'),
            'item_id' => Item::query()->where('code', 'BAUT-M12')->value('id'),
            'qty' => 150,
        ]], $dedi);

        $snapshot = ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::StockAdjustment, $adj->id)->sole();
        $this->assertSame('ADJ di atas 100 unit', $snapshot->rule_name);
        $this->assertCount(2, $snapshot->steps);

        try {
            app(ApproveStockAdjustment::class)->approve($adj, $sari);
            $this->fail('Kepala Gudang BKS tidak ditugaskan untuk ADJ CKG.');
        } catch (ApprovalRuleException $e) {
            $this->assertSame('BR-APR-01', $e->rule);
        }

        app(ApproveStockAdjustment::class)->approve($adj, $andi);
        $this->assertSame(StockAdjustmentStatus::PendingApproval, $adj->refresh()->status);
        $this->assertSame(1000.0, $this->saldo('CKG-A-R01-L1-B01', 'BAUT-M12'));

        app(ApproveStockAdjustment::class)->approve($adj, $budi);
        $this->assertSame(StockAdjustmentStatus::Posted, $adj->refresh()->status);
        $this->assertSame(850.0, $this->saldo('CKG-A-R01-L1-B01', 'BAUT-M12'));

        // ADJ kecil: satu lapis Kepala Gudang saja.
        $kecil = app(CreateStockAdjustment::class)->handle([
            'warehouse_id' => Warehouse::query()->where('code', 'CKG')->value('id'),
            'reason_code_id' => ReasonCode::query()->where('context', ReasonContext::Adjustment->value)->where('code', 'FOUND')->value('id'),
        ], [[
            'direction' => 'in',
            'bin_id' => Bin::query()->where('code', 'CKG-A-R01-L1-B01')->value('id'),
            'item_id' => Item::query()->where('code', 'BAUT-M12')->value('id'),
            'qty' => 20,
        ]], $dedi);
        $this->assertSame('ADJ lainnya', ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::StockAdjustment, $kecil->id)->sole()->rule_name);
        app(ApproveStockAdjustment::class)->approve($kecil, $andi);
        $this->assertSame(870.0, $this->saldo('CKG-A-R01-L1-B01', 'BAUT-M12'));
    }

    private function line(StockCount $sesi, string $bin, string $item): CountLine
    {
        return CountLine::query()->where('stock_count_id', $sesi->id)
            ->where('bin_id', Bin::query()->where('code', $bin)->value('id'))
            ->where('item_id', Item::query()->where('code', $item)->value('id'))
            ->firstOrFail();
    }

    /** @param  array<int, float>  $isian */
    private function hitung(StockCount $sesi, int $round, array $isian): void
    {
        foreach (CountAssignment::query()->where('stock_count_id', $sesi->id)->where('round', $round)->pending()->get() as $t) {
            $orang = User::query()->findOrFail($t->counter_user_id);
            $qty = [];

            foreach ($t->linesQuery()->get() as $l) {
                $qty[$l->id] = $isian[$l->id] ?? (float) $l->system_qty;
            }

            app(RecordCount::class)->save($t, $qty, $orang);
            app(RecordCount::class)->finish($t->refresh(), $orang);
        }
    }
}

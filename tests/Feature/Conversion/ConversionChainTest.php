<?php

declare(strict_types=1);

namespace Tests\Feature\Conversion;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Livewire\TaskInbox;
use App\Domain\Approval\Models\ApprovalRule;
use App\Domain\Approval\Models\ApprovalTask;
use App\Domain\Conversion\Actions\CreateConversion;
use App\Domain\Conversion\Actions\SubmitConversion;
use App\Domain\Conversion\Enums\ConversionStatus;
use App\Domain\Issue\Support\ProjectMaterialSummary;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Waste\Actions\CloseWasteDisposal;
use App\Domain\Waste\Enums\WasteDisposalStatus;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalFixtures;
use Tests\Feature\Conversion\Concerns\ConversionFixtures;
use Tests\TenantTestCase;

/**
 * TC-CNV-14 — rantai lintas modul: SJ → retur rusak dipilah waste (RET) →
 * CNV potong pipa lewat aturan approval yang diputus dari "Tugas approval
 * saya" → CNV kedua dibalik → BA waste menutup isi bin Waste dari kedua sumber
 * → laporan Material per proyek (A-161) dan saldo cocok dengan kartu stok.
 */
class ConversionChainTest extends TenantTestCase
{
    use ApprovalFixtures;
    use ConversionFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanKonversi();
    }

    #[Test]
    public function tc_cnv_14_retur_waste_konversi_approval_pembalik_ba_waste_dan_laporan(): void
    {
        $staf = $this->staf();
        $binWaste = $this->binSistem($this->gudang, BinType::Waste);

        // 1. Retur 2 baut rusak dari Gudang Site proyek dipilah ke bin Waste (alur 5).
        $this->stok($this->binKrw1, $this->baut, 20);
        $ret = $this->ret([['key' => $this->kunciSite($this->binKrw1, $this->baut), 'qty_base' => 2]]);
        $this->grnRetur($ret);
        $this->pilah($ret, [$ret->lines()->sole()->id => [['sorting' => 'waste', 'qty' => 2, 'reason_code_id' => $this->alasan(ReasonContext::Waste)]]]);
        $this->assertSame(2.0, $this->saldo($binWaste, $this->baut, StockStatus::Damaged));

        // 2. CNV potong pipa terkena aturan; diputus dari kotak tugas approval.
        $kepala = $this->makeUser('warehouse_head');
        $this->aturan(ApprovalDocumentType::Conversion, [$this->lapisUser($kepala)], [], 10, 'CNV lewat Kepala Gudang');
        $cnv = $this->cnv(
            [['key' => $this->kunciBatang(), 'qty_base' => 6]],
            [['kind' => 'output', 'item_id' => $this->pipa->id, 'qty_base' => 5.695], ['kind' => 'offcut', 'qty_base' => 0.3], ['kind' => 'kerf', 'qty_base' => 0.005]],
            [],
            $staf,
        );
        $cnv = app(SubmitConversion::class)->handle($cnv, $staf);
        $tugas = ApprovalTask::query()->where('approver_user_id', $kepala->id)->sole();

        Livewire::actingAs($kepala)->test(TaskInbox::class)
            ->assertSee($cnv->number)
            ->call('setujui', $tugas->id)
            ->assertSet('ruleError', '');

        $this->assertSame(ConversionStatus::Completed, $cnv->refresh()->status);
        $this->assertSame(0.3, $this->saldo($binWaste, $this->pipa, StockStatus::Damaged));

        // 3. CNV kedua (tanpa aturan setelah aturan dinonaktifkan) lalu dibalik: laporan netral.
        ApprovalRule::query()->update(['is_active' => false]);
        $kedua = $this->selesai($this->cnv(
            [['key' => $this->kunciBatang($this->potongan($this->binA, 4.0)), 'qty_base' => 4]],
            [['kind' => 'output', 'item_id' => $this->pipa->id, 'qty_base' => 3.99], ['kind' => 'kerf', 'qty_base' => 0.01]],
        ), $staf);
        $balik = app(CreateConversion::class)->reverse($kedua, $this->alasan(ReasonContext::Cancel), null, $staf);
        $this->selesai($balik, $staf);

        // 4. BA waste menutup waste retur dan waste konversi.
        $sisa = $cnv->outputs()->where('auto_waste', true)->sole();
        $wst = $this->wst([
            ['key' => $this->kunciWaste($binWaste, $this->baut), 'qty_base' => 2],
            ['key' => $this->kunciWaste($binWaste, $this->pipa, 'damaged', ['piece_id' => $sisa->new_piece_id]), 'qty_base' => 0.3],
        ], ['disposition' => 'disposed'], $staf);
        $this->assertSame(WasteDisposalStatus::Approved, $wst->status);
        $wst = app(CloseWasteDisposal::class)->handle($wst, null, 'BA/WST/RANTAI', $staf);
        $this->assertSame(WasteDisposalStatus::Closed, $wst->status);
        $this->assertSame(0.0, $this->saldo($binWaste, $this->baut, StockStatus::Damaged));
        $this->assertSame(0.0, $this->saldo($binWaste, $this->pipa, StockStatus::Damaged));

        // 5. Laporan Material per proyek (A-161).
        $baris = app(ProjectMaterialSummary::class)->rows([(int) $this->proyek->id])->keyBy('item_code');
        $this->assertSame(6.0, $baris['PIPA-PVC']['dikonversi'], 'CNV kedua dibalik: hanya 6 m yang terhitung.');
        $this->assertSame(5.695, $baris['PIPA-PVC']['hasil_konversi']);
        $this->assertSame(0.3, $baris['PIPA-PVC']['waste']);
        $this->assertSame(0.3, $baris['PIPA-PVC']['waste_didisposisi']);
        $this->assertSame(2.0, $baris['BAUT-M12']['waste'], 'Waste hasil pilah retur ikut.');
        $this->assertSame(2.0, $baris['BAUT-M12']['waste_didisposisi']);

        // Saldo selalu sama dengan kartu stok (BR-STK-01).
        $ledger = app(StockLedger::class)->rebuildFromLedger($this->pipa->id);
        $saldo = StockBalance::query()->where('item_id', $this->pipa->id)->get()
            ->mapWithKeys(fn ($s) => [implode('|', [$s->item_id, $s->bin_id, $s->lot_id ?? 0, $s->serial_id ?? 0, $s->piece_id ?? 0, $s->stock_status->value]) => round((float) $s->qty_base, 4)])
            ->all();
        foreach ($ledger as $kunci => $jumlah) {
            $this->assertSame($jumlah, $saldo[$kunci] ?? 0.0, 'Saldo '.$kunci.' = kartu stok.');
        }
    }
}

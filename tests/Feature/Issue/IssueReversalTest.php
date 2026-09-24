<?php

declare(strict_types=1);

namespace Tests\Feature\Issue;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApprovalSnapshotStatus;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Approval\Support\ApprovalRegistry;
use App\Domain\Issue\Actions\ApproveMaterialIssue;
use App\Domain\Issue\Actions\CancelMaterialIssue;
use App\Domain\Issue\Actions\CreateMaterialIssue;
use App\Domain\Issue\Enums\MaterialIssueStatus;
use App\Domain\Issue\Models\MaterialIssue;
use App\Domain\Issue\Policies\MaterialIssuePolicy;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\Lot;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Stock\Models\StockMovement;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalFixtures;
use Tests\Feature\Issue\Concerns\IssueFixtures;
use Tests\TenantTestCase;

/**
 * TC-ISU-09 s.d. TC-ISU-11 — ISU pembalik: jumlah negatif, Alasan `*`,
 * approval minimal satu lapis, tetap `draft` selama menunggu, pembalikan di
 * kartu stok dengan `reverses_event_id`, sekali per baris (BR-GEN-03/04,
 * BR-LED-05, BR-APR-03, A-150).
 */
class IssueReversalTest extends TenantTestCase
{
    use ApprovalFixtures;
    use IssueFixtures;

    private Lot $lot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPemakaian();
        $this->stok($this->binKrw1, $this->baut, 20);

        $this->lot = Lot::create(['item_id' => $this->semen->id, 'lot_no' => 'LOT-R1', 'expiry_date' => now()->addMonths(6)->toDateString(), 'received_at' => now()->toDateString()]);
        $this->stok($this->binKrw1, $this->semen, 50, ['lot_id' => $this->lot->id]);
    }

    private function isuDikonfirmasi(): MaterialIssue
    {
        return $this->konfirmasi($this->isu([
            ['key' => $this->kunciIsu($this->binKrw1, $this->baut), 'qty_base' => 12, 'work_note' => 'Salah ketik'],
            ['key' => $this->kunciIsu($this->binKrw1, $this->semen, ['lot_id' => $this->lot->id]), 'qty_base' => 5],
        ]));
    }

    #[Test]
    public function tc_isu_09_pembalik_diajukan_disetujui_dan_stok_kembali(): void
    {
        $this->assertTrue(app(ApprovalRegistry::class)->has(ApprovalDocumentType::MaterialIssue), 'ISU pembalik tersambung ke mesin approval.');

        $manajemen = $this->makeUser('management');
        $staf = $this->stafSite();
        $asal = $this->isuDikonfirmasi();
        $barisBaut = $asal->lines()->where('item_id', $this->baut->id)->sole();
        $buat = app(CreateMaterialIssue::class);

        $this->gagalIsu(fn () => $buat->reverse($asal, [$barisBaut->id], null, null, $staf), 'BR-GEN-11');
        $this->gagalIsu(fn () => $buat->reverse($asal, [$barisBaut->id], $this->alasan(ReasonContext::Reject), null, $staf), 'BR-GEN-11');

        $balik = $buat->reverse($asal, [$barisBaut->id], $this->alasan(ReasonContext::Cancel), 'Seharusnya 2', $staf);

        $this->assertSame(MaterialIssueStatus::Draft, $balik->status);
        $this->assertSame((int) $asal->id, (int) $balik->reversal_of_id);
        $this->assertMatchesRegularExpression('#^ISU/KRW1/#', $balik->number);
        $baris = $balik->lines()->sole();
        $this->assertSame(-12.0, (float) $baris->qty_base, 'Jumlah negatif.');
        $this->assertSame((int) $barisBaut->id, (int) $baris->reversal_of_line_id);
        $this->assertFalse(app(MaterialIssuePolicy::class)->update($staf, $balik), 'Pembalik tidak diubah lewat form.');

        // Baris yang sama tidak bisa dibalik dua kali (BR-LED-05).
        $this->gagalIsu(fn () => $buat->reverse($asal, [$barisBaut->id], $this->alasan(ReasonContext::Cancel), null, $staf), 'BR-LED-05');
        $this->gagalIsu(fn () => $buat->reverse($balik, [], $this->alasan(ReasonContext::Cancel), null, $staf), 'BR-GEN-03');

        // Konfirmasi pembalik = diajukan ke approval; tetap Draf, stok belum kembali.
        $pengaju = $this->stafSite();
        $balik = $this->konfirmasi($balik, $pengaju);
        $this->assertSame(MaterialIssueStatus::Draft, $balik->status);
        $this->assertTrue($balik->isAwaitingApproval());
        $this->assertSame(8.0, $this->saldo($this->binKrw1, $this->baut));
        $this->gagalIsu(fn () => $this->konfirmasi($balik, $pengaju), 'BR-APR-01');

        // Tanpa aturan: lapis minimum Kepala Gudang Site → cadangan Manajemen (A-150).
        $snapshot = ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::MaterialIssue, $balik->id)->sole();
        $this->assertNull($snapshot->rule_id);
        $this->assertSame([(int) $manajemen->id], $snapshot->steps[0]['approver_user_ids']);

        // BR-APR-03: pembuat dan pengaju tidak memutus.
        $aksi = app(ApproveMaterialIssue::class);
        $this->gagalIsu(fn () => $aksi->approve($balik, $staf), 'BR-APR-03');
        $this->gagalIsu(fn () => $aksi->approve($balik, $pengaju), 'BR-APR-03');
        $this->assertTrue(app(MaterialIssuePolicy::class)->approve($manajemen, $balik));
        $this->assertFalse(app(MaterialIssuePolicy::class)->approve($pengaju, $balik));

        $balik = $aksi->approve($balik, $manajemen);

        $this->assertSame(MaterialIssueStatus::Confirmed, $balik->status);
        $this->assertSame((int) $manajemen->id, (int) $balik->approved_by);
        $this->assertSame((int) $pengaju->id, (int) $balik->confirmed_by);
        $this->assertSame(20.0, $this->saldo($this->binKrw1, $this->baut), 'Pemakaian asal dibalik ke bin asalnya.');
        $this->assertSame(45.0, $this->saldo($this->binKrw1, $this->semen), 'Baris lain tidak ikut dibalik.');

        $gerak = StockMovement::query()->findOrFail($balik->lines()->sole()->movement_id);
        $this->assertSame((int) $barisBaut->movement_id, (int) $gerak->reverses_movement_id, 'BR-LED-05.');
        $this->assertSame((int) $this->binKrw1->id, (int) $gerak->to_bin_id);

        $kejadianAsal = StockEvent::query()->where('source_type', 'material_issue')->where('source_id', $asal->id)
            ->where('payload->movement_id', $barisBaut->movement_id)->sole();
        $kejadianBalik = StockEvent::query()->where('source_type', 'material_issue')->where('source_id', $balik->id)->sole();
        $this->assertSame(StockEventType::MaterialConsumed, $kejadianBalik->event_type, 'Matriks §14: jenis sama dengan asal.');
        $this->assertSame($kejadianAsal->event_id, $kejadianBalik->reverses_event_id);

        // Baris semen masih bisa dibalik; baut tidak lagi.
        $this->assertTrue(app(MaterialIssuePolicy::class)->reverse($staf, $asal->refresh()));
        $this->gagalIsu(fn () => $buat->reverse($asal, [$barisBaut->id], $this->alasan(ReasonContext::Cancel), null, $staf), 'BR-LED-05');
        $sisa = $buat->reverse($asal, [], $this->alasan(ReasonContext::Cancel), null, $staf);
        $this->assertSame(-5.0, (float) $sisa->lines()->sole()->qty_base);
        $this->assertFalse(app(MaterialIssuePolicy::class)->reverse($staf, $asal->refresh()), 'Semua baris sudah dibalik/diajukan.');
    }

    #[Test]
    public function tc_isu_10_pembalik_ditolak_tetap_draf_lalu_diajukan_ulang_atau_dibatalkan(): void
    {
        $manajemen = $this->makeUser('management');
        $staf = $this->stafSite();
        $asal = $this->isuDikonfirmasi();
        $balik = app(CreateMaterialIssue::class)->reverse($asal, [], $this->alasan(ReasonContext::Cancel), null, $staf);
        $balik = $this->konfirmasi($balik, $staf);
        $aksi = app(ApproveMaterialIssue::class);

        $this->gagalIsu(fn () => $aksi->reject($balik, null, null, $manajemen), 'BR-GEN-11');

        $balik = $aksi->reject($balik, $this->alasan(ReasonContext::Reject), 'Cek ulang', $manajemen);
        $this->assertSame(MaterialIssueStatus::Draft, $balik->status, 'A-150: tanpa status baru.');
        $this->assertFalse($balik->isAwaitingApproval());
        $this->assertNotNull($balik->reject_reason_id);
        $this->assertSame(8.0, $this->saldo($this->binKrw1, $this->baut));
        $this->assertTrue(app(MaterialIssuePolicy::class)->confirm($staf, $balik), 'Boleh diajukan ulang.');

        $balik = $this->konfirmasi($balik, $staf);
        $this->assertTrue($balik->isAwaitingApproval());
        $this->assertNull($balik->reject_reason_id);

        $balik = app(CancelMaterialIssue::class)->handle($balik, $this->alasan(ReasonContext::Cancel), null, $staf);
        $this->assertSame(MaterialIssueStatus::Cancelled, $balik->status);
        $this->assertNull(app(ApprovalEngine::class)->pendingSnapshot(ApprovalDocumentType::MaterialIssue, $balik->id));
        $this->assertSame(ApprovalSnapshotStatus::Cancelled, ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::MaterialIssue, $balik->id)->latest('id')->first()->status);

        // Pembalik yang dibatalkan melepas barisnya: bisa dibalik lagi.
        $this->assertTrue(app(MaterialIssuePolicy::class)->reverse($staf, $asal->refresh()));
        $this->assertSame(8.0, $this->saldo($this->binKrw1, $this->baut));
    }

    #[Test]
    public function tc_isu_11_aturan_approval_isu_menggantikan_lapis_minimum(): void
    {
        $kepala = $this->makeUser('warehouse_head', ScopeType::Warehouse, $this->krw1->id);
        $manajemen = $this->makeUser('management');
        $this->aturan(ApprovalDocumentType::MaterialIssue, [$this->lapisUser($kepala), $this->lapisUser($manajemen)], [], 10, 'ISU pembalik dua lapis');

        $staf = $this->stafSite();
        $balik = app(CreateMaterialIssue::class)->reverse($this->isuDikonfirmasi(), [], $this->alasan(ReasonContext::Cancel), null, $staf);
        $balik = $this->konfirmasi($balik, $staf);

        $snapshot = ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::MaterialIssue, $balik->id)->sole();
        $this->assertSame('ISU pembalik dua lapis', $snapshot->rule_name);
        $this->assertFalse(app(MaterialIssuePolicy::class)->approve($manajemen, $balik), 'Lapis 2 belum aktif.');

        $aksi = app(ApproveMaterialIssue::class);
        $balik = $aksi->approve($balik, $kepala);
        $this->assertSame(MaterialIssueStatus::Draft, $balik->status, 'Menunggu lapis berikutnya.');
        $this->assertSame(8.0, $this->saldo($this->binKrw1, $this->baut));

        $balik = $aksi->approve($balik, $manajemen);
        $this->assertSame(MaterialIssueStatus::Confirmed, $balik->status);
        $this->assertSame(20.0, $this->saldo($this->binKrw1, $this->baut));
        $this->assertSame(50.0, $this->saldo($this->binKrw1, $this->semen));

        // Tanpa lapis minimum pun ISU biasa tidak pernah lewat approval.
        $this->assertSame(1, ApprovalSnapshot::query()->where('document_type', ApprovalDocumentType::MaterialIssue->value)->count());
    }
}

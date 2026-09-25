<?php

declare(strict_types=1);

namespace Tests\Feature\Waste;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApprovalSnapshotStatus;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Support\ApprovalRegistry;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Piece;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Waste\Actions\ApproveWasteDisposal;
use App\Domain\Waste\Actions\CancelWasteDisposal;
use App\Domain\Waste\Actions\CloseWasteDisposal;
use App\Domain\Waste\Enums\WasteDisposalStatus;
use App\Domain\Waste\Policies\WasteDisposalPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalFixtures;
use Tests\Feature\Conversion\Concerns\ConversionFixtures;
use Tests\TenantTestCase;

/**
 * TC-WST-01 s.d. TC-WST-05 — Berita Acara Waste (Katalog §2.14, A-159,
 * A-160): baris dari isi bin Waste, disetujui otomatis tanpa aturan, ditutup
 * dengan bukti (foto atau nomor BA), dipakai ulang kembali ke stok, approval,
 * tolak, dan batal.
 */
class WasteDisposalTest extends TenantTestCase
{
    use ApprovalFixtures;
    use ConversionFixtures;

    private Bin $binWaste;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanKonversi();
        $this->binWaste = $this->binSistem($this->gudang, BinType::Waste);
        $this->wasteMasuk($this->baut, 10);
    }

    private function wasteMasuk(Item $item, float $qty, array $turunan = []): void
    {
        app(StockLedger::class)->post(new MovementRequest(
            item: $item,
            qtyBase: $qty,
            toBinId: $this->binWaste->id,
            stockStatus: StockStatus::Damaged,
            pieceId: $turunan['piece_id'] ?? null,
        ));
    }

    private function tutup($wst, ?string $catatan = 'BA/WST/001', ?UploadedFile $foto = null)
    {
        return app(CloseWasteDisposal::class)->handle($wst->refresh(), $foto, $catatan, $this->staf());
    }

    #[Test]
    public function tc_wst_01_tanpa_aturan_disetujui_otomatis_lalu_ditutup_dengan_bukti(): void
    {
        $this->assertTrue(app(ApprovalRegistry::class)->has(ApprovalDocumentType::WasteDisposal));

        $staf = $this->staf();
        $wst = $this->wst([['key' => $this->kunciWaste($this->binWaste, $this->baut), 'qty_base' => 6, 'reason_code_id' => $this->alasan(ReasonContext::Waste)]], ['disposition' => 'sold_scrap'], $staf);

        $this->assertSame(WasteDisposalStatus::Approved, $wst->status, 'A-08: tanpa aturan disetujui otomatis.');
        $this->assertMatchesRegularExpression('#^WST/CKG/#', $wst->number);
        $this->assertSame((int) $staf->id, (int) $wst->submitted_by);
        $this->assertSame(10.0, $this->saldo($this->binWaste, $this->baut, StockStatus::Damaged), 'Stok baru bergerak saat ditutup.');
        $this->assertTrue(app(WasteDisposalPolicy::class)->close($staf, $wst));

        $this->gagalCnv(fn () => $this->tutup($wst, null), 'BR-GEN-01');

        $wst = $this->tutup($wst, 'BA/WST/2026/017 ditandatangani pengepul');
        $this->assertSame(WasteDisposalStatus::Closed, $wst->status);
        $this->assertSame('BA/WST/2026/017 ditandatangani pengepul', $wst->evidence_note);
        $this->assertNotNull($wst->closed_at);
        $this->assertSame(4.0, $this->saldo($this->binWaste, $this->baut, StockStatus::Damaged));

        $kejadian = StockEvent::query()->where('source_type', 'waste_disposal')->where('source_id', $wst->id)->sole();
        $this->assertSame(StockEventType::WasteDisposed, $kejadian->event_type);
        $this->assertSame('sold_scrap', $kejadian->payload['disposition']);
        $this->assertSame((int) $this->proyek->id, (int) $kejadian->project_id);
        $this->gagalCnv(fn () => $this->tutup($wst), 'BR-GEN-01');
    }

    #[Test]
    public function tc_wst_02_dipakai_ulang_kembali_ke_bin_penyimpanan_tersedia(): void
    {
        $this->gagalCnv(fn () => $this->wst([['key' => $this->kunciWaste($this->binWaste, $this->baut), 'qty_base' => 4]], ['disposition' => 'reused']), 'BR-STK-02');
        $this->gagalCnv(fn () => $this->wst([['key' => $this->kunciWaste($this->binWaste, $this->baut), 'qty_base' => 4]], ['disposition' => 'reused', 'target_bin_id' => $this->binBks->id]), 'BR-STK-02');

        $wst = $this->wst([['key' => $this->kunciWaste($this->binWaste, $this->baut), 'qty_base' => 4]], ['disposition' => 'reused', 'target_bin_id' => $this->binB->id]);
        $wst = $this->tutup($wst);

        $this->assertSame(6.0, $this->saldo($this->binWaste, $this->baut, StockStatus::Damaged));
        $this->assertSame(4.0, $this->saldo($this->binB, $this->baut), 'Kembali ke stok berkondisi Tersedia.');
        $this->assertSame('reused', StockEvent::query()->where('source_type', 'waste_disposal')->where('source_id', $wst->id)->sole()->payload['disposition']);
    }

    #[Test]
    public function tc_wst_03_guard_isi_bin_waste_potongan_utuh_dan_dipegang_ba_lain(): void
    {
        $this->gagalCnv(fn () => $this->wst([['key' => $this->kunciWaste($this->binWaste, $this->baut), 'qty_base' => 11]]), 'BR-STK-06');
        $this->gagalCnv(fn () => $this->wst([['key' => $this->kunciWaste($this->binA, $this->baut, 'available'), 'qty_base' => 1]]), 'BR-STK-02');
        $this->gagalCnv(fn () => $this->wst([]), 'BR-STK-02');
        $this->gagalCnv(fn () => $this->wst([['key' => $this->kunciWaste($this->binWaste, $this->baut), 'qty_base' => 1]], ['project_id' => null]), 'BR-CNV-01');
        $this->gagalCnv(fn () => $this->wst([['key' => $this->kunciWaste($this->binWaste, $this->baut), 'qty_base' => 1]], ['disposition' => 'dibakar']), 'BR-GEN-01');
        $this->gagalCnv(fn () => $this->wst([['key' => $this->kunciWaste($this->binWaste, $this->baut), 'qty_base' => 1, 'reason_code_id' => $this->alasan(ReasonContext::Cancel)]]), 'BR-GEN-02');

        // Potongan waste ditutup utuh.
        $sisa = Piece::create(['item_id' => $this->pipa->id, 'piece_no' => Piece::nextPieceNo(), 'length' => 0.3]);
        $this->wasteMasuk($this->pipa, 0.3, ['piece_id' => $sisa->id]);
        $kunciSisa = $this->kunciWaste($this->binWaste, $this->pipa, 'damaged', ['piece_id' => $sisa->id]);
        $this->gagalCnv(fn () => $this->wst([['key' => $kunciSisa, 'qty_base' => 0.1]]), 'BR-STK-09');

        // Jumlah yang dipegang BA lain tidak bisa diajukan dua kali.
        $pertama = $this->wst([['key' => $this->kunciWaste($this->binWaste, $this->baut), 'qty_base' => 7], ['key' => $kunciSisa, 'qty_base' => 0.3]]);
        $this->gagalCnv(fn () => $this->wst([['key' => $this->kunciWaste($this->binWaste, $this->baut), 'qty_base' => 4]]), 'BR-STK-06');
        $kedua = $this->wst([['key' => $this->kunciWaste($this->binWaste, $this->baut), 'qty_base' => 3]]);

        $this->tutup($pertama);
        $this->assertTrue($sisa->refresh()->is_consumed, 'Potongan yang dibuang tidak tersedia lagi.');
        $this->tutup($kedua);
        $this->assertSame(0.0, $this->saldo($this->binWaste, $this->baut, StockStatus::Damaged));
    }

    #[Test]
    public function tc_wst_04_approval_tolak_setujui_dan_batal(): void
    {
        $staf = $this->staf();
        $manajemen = $this->makeUser('management');
        $this->aturan(ApprovalDocumentType::WasteDisposal, [$this->lapisUser($manajemen)], [], 10, 'WST semua');

        $wst = $this->wst([['key' => $this->kunciWaste($this->binWaste, $this->baut), 'qty_base' => 5]], [], $staf);
        $this->assertSame(WasteDisposalStatus::PendingApproval, $wst->status);
        $this->assertTrue($wst->isAwaitingApproval());
        $this->assertFalse(app(WasteDisposalPolicy::class)->close($staf, $wst));
        $this->gagalCnv(fn () => $this->tutup($wst), 'BR-GEN-01');

        $aksi = app(ApproveWasteDisposal::class);
        $this->gagalCnv(fn () => $aksi->approve($wst, $staf), 'BR-APR-03');
        $this->gagalCnv(fn () => $aksi->reject($wst, null, null, $manajemen), 'BR-GEN-11');

        $ditolak = $aksi->reject($wst, $this->alasan(ReasonContext::Reject), 'Masih bisa dipakai', $manajemen);
        $this->assertSame(WasteDisposalStatus::Rejected, $ditolak->status);
        $this->assertFalse(app(WasteDisposalPolicy::class)->cancel($staf, $ditolak));

        // Ditolak melepas jumlahnya: bisa diajukan lagi.
        $baru = $this->wst([['key' => $this->kunciWaste($this->binWaste, $this->baut), 'qty_base' => 10]], [], $staf);
        $baru = $aksi->approve($baru, $manajemen);
        $this->assertSame(WasteDisposalStatus::Approved, $baru->status);
        $this->assertSame((int) $manajemen->id, (int) $baru->approved_by);
        $this->gagalCnv(fn () => app(CancelWasteDisposal::class)->handle($baru, $this->alasan(ReasonContext::Cancel), null, $staf), 'BR-GEN-01');

        // Batal saat menunggu: snapshot ditarik, isi bin dilepas.
        $this->wasteMasuk($this->baut, 2);
        $tunggu = $this->wst([['key' => $this->kunciWaste($this->binWaste, $this->baut), 'qty_base' => 2]], [], $staf);
        $this->gagalCnv(fn () => app(CancelWasteDisposal::class)->handle($tunggu, null, null, $staf), 'BR-GEN-11');
        $tunggu = app(CancelWasteDisposal::class)->handle($tunggu, $this->alasan(ReasonContext::Cancel), null, $staf);
        $this->assertSame(WasteDisposalStatus::Cancelled, $tunggu->status);
        $this->assertSame(ApprovalSnapshotStatus::Cancelled, ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::WasteDisposal, $tunggu->id)->sole()->status);
    }

    #[Test]
    public function tc_wst_05_bukti_foto_disimpan_di_disk_tenant(): void
    {
        Storage::fake('local');

        $wst = $this->wst([['key' => $this->kunciWaste($this->binWaste, $this->baut), 'qty_base' => 10]]);
        $wst = $this->tutup($wst, null, UploadedFile::fake()->image('ba.jpg'));

        $this->assertSame('waste-disposals/'.$wst->id.'.jpg', $wst->evidence_path);
        Storage::disk('local')->assertExists($wst->evidence_path);
        $this->assertTrue($wst->hasEvidence());
        $this->assertSame(0.0, $this->saldo($this->binWaste, $this->baut, StockStatus::Damaged));
    }
}

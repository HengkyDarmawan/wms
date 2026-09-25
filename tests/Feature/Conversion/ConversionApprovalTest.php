<?php

declare(strict_types=1);

namespace Tests\Feature\Conversion;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApprovalSnapshotStatus;
use App\Domain\Approval\Models\ApprovalRule;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Approval\Support\ApprovalRegistry;
use App\Domain\Conversion\Actions\ApproveConversion;
use App\Domain\Conversion\Actions\CancelConversion;
use App\Domain\Conversion\Actions\SubmitConversion;
use App\Domain\Conversion\Enums\ConversionStatus;
use App\Domain\Conversion\Policies\ConversionPolicy;
use App\Domain\Master\Enums\ReasonContext;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalFixtures;
use Tests\Feature\Conversion\Concerns\ConversionFixtures;
use Tests\TenantTestCase;

/**
 * TC-CNV-07 dan TC-CNV-08 — approval CNV opsional (A-153): ajukan hanya bila
 * ada aturan, selesaikan langsung hanya bila tidak ada; disetujui = diposting,
 * ditolak = kembali Draf; batal dari Draf/Menunggu (Katalog §2.10, BR-APR-03,
 * BR-GEN-04, BR-GEN-11).
 */
class ConversionApprovalTest extends TenantTestCase
{
    use ApprovalFixtures;
    use ConversionFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanKonversi();
    }

    #[Test]
    public function tc_cnv_07_ada_aturan_ajukan_setujui_atau_tolak(): void
    {
        $this->assertTrue(app(ApprovalRegistry::class)->has(ApprovalDocumentType::Conversion), 'CNV tersambung ke mesin approval.');

        $staf = $this->staf();
        $manajemen = $this->makeUser('management');
        $cnv = $this->cnvPotong($staf);

        // Tanpa aturan: tidak bisa diajukan (BR-APR-02).
        $this->gagalCnv(fn () => app(SubmitConversion::class)->handle($cnv, $staf), 'BR-APR-02');

        $this->aturan(ApprovalDocumentType::Conversion, [$this->lapisUser($manajemen)], ['warehouse_ids' => [$this->gudang->id]], 10, 'CNV CKG');
        $this->assertTrue(app(ConversionPolicy::class)->submit($staf, $cnv->refresh()));
        $this->assertFalse(app(ConversionPolicy::class)->complete($staf, $cnv), 'Ada aturan: tidak bisa diselesaikan langsung.');
        $this->gagalCnv(fn () => $this->selesai($cnv, $staf), 'BR-APR-01');

        $pengaju = $this->staf();
        $cnv = app(SubmitConversion::class)->handle($cnv, $pengaju);
        $this->assertSame(ConversionStatus::PendingApproval, $cnv->status);
        $this->assertTrue($cnv->isAwaitingApproval());
        $this->assertSame(6.0, $this->saldo($this->binA, $this->pipa), 'Belum diposting selama menunggu.');
        $this->gagalCnv(fn () => app(SubmitConversion::class)->handle($cnv, $pengaju), 'BR-GEN-01');

        $aksi = app(ApproveConversion::class);
        $this->gagalCnv(fn () => $aksi->approve($cnv, $staf), 'BR-APR-03');
        $this->gagalCnv(fn () => $aksi->approve($cnv, $pengaju), 'BR-APR-03');
        $this->gagalCnv(fn () => $aksi->reject($cnv, null, null, $manajemen), 'BR-GEN-11');
        $this->assertTrue(app(ConversionPolicy::class)->approve($manajemen, $cnv));

        // Ditolak: kembali Draf dengan alasan, stok utuh (A-153).
        $cnv = $aksi->reject($cnv, $this->alasan(ReasonContext::Reject), 'Ukuran salah', $manajemen);
        $this->assertSame(ConversionStatus::Draft, $cnv->status);
        $this->assertNotNull($cnv->reject_reason_id);
        $this->assertFalse($cnv->isAwaitingApproval());
        $this->assertSame(6.0, $this->saldo($this->binA, $this->pipa));
        $this->assertTrue(app(ConversionPolicy::class)->update($staf, $cnv), 'Draf yang ditolak bisa diperbaiki.');

        // Diajukan ulang lalu disetujui: diposting, Selesai oleh pengaju.
        $cnv = app(SubmitConversion::class)->handle($cnv, $pengaju);
        $this->assertNull($cnv->reject_reason_id);
        $cnv = $aksi->approve($cnv, $manajemen);

        $this->assertSame(ConversionStatus::Completed, $cnv->status);
        $this->assertSame((int) $manajemen->id, (int) $cnv->approved_by);
        $this->assertSame((int) $pengaju->id, (int) $cnv->completed_by);
        $this->assertSame(5.99, $this->saldo($this->binA, $this->pipa));
        $this->assertSame(2, ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::Conversion, $cnv->id)->count());
    }

    #[Test]
    public function tc_cnv_08_batal_dari_draf_atau_menunggu_dan_selesai_tidak_bisa_dibatalkan(): void
    {
        $staf = $this->staf();
        $batal = app(CancelConversion::class);

        $draf = $this->cnvPotong($staf);
        $this->gagalCnv(fn () => $batal->handle($draf, null, null, $staf), 'BR-GEN-11');
        $this->gagalCnv(fn () => $batal->handle($draf, $this->alasan(ReasonContext::Reject), null, $staf), 'BR-GEN-11');
        $this->assertFalse(app(ConversionPolicy::class)->cancel($this->staf(), $draf), 'Staf lain bukan pembuat.');
        $this->assertTrue(app(ConversionPolicy::class)->cancel($this->makeUser('warehouse_head'), $draf), 'Pemegang conversion.approve boleh (A-158).');

        $draf = $batal->handle($draf, $this->alasan(ReasonContext::Cancel), 'Salah input', $staf);
        $this->assertSame(ConversionStatus::Cancelled, $draf->status);
        $this->assertNotNull($draf->cancel_reason_id);

        // Menunggu approval: snapshot ditarik.
        $this->aturan(ApprovalDocumentType::Conversion, [$this->lapisUser($this->makeUser('management'))], [], 10, 'Semua CNV');
        $menunggu = app(SubmitConversion::class)->handle($this->cnvPotong($staf), $staf);
        $menunggu = $batal->handle($menunggu, $this->alasan(ReasonContext::Cancel), null, $staf);
        $this->assertSame(ConversionStatus::Cancelled, $menunggu->status);
        $this->assertNull(app(ApprovalEngine::class)->pendingSnapshot(ApprovalDocumentType::Conversion, $menunggu->id));
        $this->assertSame(ApprovalSnapshotStatus::Cancelled, ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::Conversion, $menunggu->id)->sole()->status);

        // Selesai: hanya lewat pembalik (BR-GEN-04).
        ApprovalRule::query()->update(['is_active' => false]);
        $selesai = $this->selesai($this->cnvPotong($staf), $staf);
        $this->gagalCnv(fn () => $batal->handle($selesai, $this->alasan(ReasonContext::Cancel), null, $staf), 'BR-GEN-04');
        $this->assertFalse(app(ConversionPolicy::class)->cancel($staf, $selesai));
    }
}

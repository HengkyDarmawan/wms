<?php

declare(strict_types=1);

namespace Tests\Feature\Approval;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Approval\Actions\DecideApproval;
use App\Domain\Approval\Actions\SaveApprovalRule;
use App\Domain\Approval\Enums\ApprovalDecisionType;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApprovalSnapshotStatus;
use App\Domain\Approval\Enums\ApprovalTaskStatus;
use App\Domain\Approval\Enums\ApproverType;
use App\Domain\Approval\Enums\DecisionMode;
use App\Domain\Approval\Exceptions\ApprovalRuleException;
use App\Domain\Approval\Models\ApprovalDecision;
use App\Domain\Approval\Models\ApprovalTask;
use App\Domain\Approval\Support\ApprovalContext;
use App\Domain\Approval\Support\ConditionMatcher;
use App\Domain\Request\Actions\ApproveRequest;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Stock\Models\StockReservation;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Approval\Concerns\ApprovalScenario;
use Tests\TenantTestCase;

/**
 * TC-APR-01 s.d. TC-APR-09 — inti mesin approval dengan REQ sebagai dokumen
 * (BR-APR-01–04, BR-APR-07, BR-APR-09, BR-GEN-11, A-08, D-17).
 */
class ApprovalEngineTest extends TenantTestCase
{
    use ApprovalScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanSkenario();
    }

    private function gagal(callable $aksi, string $aturan): void
    {
        try {
            $aksi();
            $this->fail('Seharusnya ditolak '.$aturan.'.');
        } catch (ApprovalRuleException|RequestRuleException $e) {
            $this->assertSame($aturan, $e->rule, $e->getMessage());
        }
    }

    private function setuju(\App\Domain\Request\Models\MaterialRequest $req, \App\Domain\Access\Models\User $u): void
    {
        app(DecideApproval::class)->approveDocument(ApprovalDocumentType::MaterialRequest, (int) $req->id, $u);
    }

    #[Test]
    public function tc_apr_01_tanpa_aturan_langsung_disetujui_dan_direservasi(): void
    {
        $req = $this->ajukanReq($this->pemohon());

        $this->assertSame(MaterialRequestStatus::Approved, $req->status, 'A-08: tanpa aturan langsung disetujui.');
        $this->assertNull($req->approved_by, 'Disetujui sistem, bukan orang.');
        $this->assertSame(1, StockReservation::query()->active()->forDocument('material_request', (int) $req->id)->count());

        $s = $this->snapshotReq($req);
        $this->assertSame(ApprovalSnapshotStatus::Approved, $s->status);
        $this->assertTrue($s->wasAutoApproved());
        $this->assertNull($s->rule_id);
        $this->assertSame((int) $s->id, (int) $req->approval_snapshot_id);
        $this->assertTrue(Activity::query()->where('subject_id', $req->id)->where('description', 'like', 'Disetujui otomatis%')->exists());
    }

    #[Test]
    public function tc_apr_02_aturan_pertama_yang_cocok_menurut_prioritas(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $direktur = $this->makeUser('management');
        $pemohon = $this->pemohon();

        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($direktur)], ['line_count_min' => 3], 10, 'Besar');
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($kepala)], [], 100, 'Lainnya');
        $mati = $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($direktur)], [], 1, 'Nonaktif');
        app(SaveApprovalRule::class)->setActive($mati, false);

        $kecil = $this->ajukanReq($pemohon);
        $this->assertSame('Lainnya', $this->snapshotReq($kecil)->rule_name);
        $this->assertSame([(int) $kepala->id], $this->approverTerbuka($kecil));

        $besar = $this->ajukanReq($pemohon, [['qty' => 1], ['qty' => 1], ['qty' => 1]]);
        $this->assertSame('Besar', $this->snapshotReq($besar)->rule_name);
        $this->assertSame([(int) $direktur->id], $this->approverTerbuka($besar));

        // "Salah satu kondisi" (A-87): cukup jumlah per baris ≥ 200.
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($direktur)], ['match' => 'any', 'line_count_min' => 5, 'line_qty_min' => 200], 5, 'Salah satu');
        $this->assertSame('Salah satu', $this->snapshotReq($this->ajukanReq($pemohon, [['qty' => 250]]))->rule_name);
        $this->assertSame('Lainnya', $this->snapshotReq($this->ajukanReq($pemohon, [['qty' => 20]]))->rule_name);
    }

    #[Test]
    public function tc_apr_03_kondisi_tanpa_nilai_uang(): void
    {
        $ctx = new ApprovalContext(
            documentType: ApprovalDocumentType::MaterialRequest,
            warehouseIds: [(int) $this->ckg->id],
            projectId: (int) $this->proyek->id,
            categoryIds: ApprovalContext::withAncestors([(int) $this->pipa->id]),
            ownershipModels: ['consumable'],
            lineCount: 21,
            maxLineQty: 100.5,
            fromClient: true,
        );
        $m = app(ConditionMatcher::class);
        $cocok = fn (array $k) => $m->evaluate($k, $ctx)['matched'];

        $this->assertTrue($cocok(['category_ids' => [$this->material->id]]), 'Kategori induk ikut mengena anaknya.');
        $this->assertTrue($cocok(['line_count_min' => 21]));
        $this->assertFalse($cocok(['line_count_min' => 22]));
        $this->assertTrue($cocok(['line_qty_min' => 100.5]), 'Jumlah per baris dalam satuan dasar (BR-APR-07).');
        $this->assertFalse($cocok(['line_qty_min' => 101]));
        $this->assertFalse($cocok(['ownership_models' => ['asset']]));
        $this->assertTrue($cocok(['match' => 'any', 'ownership_models' => ['asset'], 'line_count_min' => 21]));
        $this->assertFalse($cocok(['match' => 'all', 'ownership_models' => ['asset'], 'line_count_min' => 21]));
        $this->assertTrue($cocok(['from_client' => true]));
        $this->assertFalse($cocok(['from_client' => false]));
        $this->assertFalse($cocok(['warehouse_ids' => [$this->bks->id]]));
        $this->assertTrue($cocok([]), 'Tanpa kondisi = berlaku untuk semua.');

        // D-07: tidak ada kondisi nilai uang; kunci yang tidak dikenal dibuang.
        $this->assertSame(['match' => 'all'], ConditionMatcher::normalize(['amount_min' => 1_000_000, 'total_value' => 5, 'line_count_min' => 'abc']));

        $rule = $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($this->makeUser('warehouse_head'))], [
            'amount_min' => 1_000_000, 'vendor_types' => ['shop'], 'line_count_min' => 2,
        ]);
        $this->assertSame(['match' => 'all', 'line_count_min' => 2], $rule->conditions, 'Kondisi di luar daftar jenis dokumen dibuang.');
    }

    #[Test]
    public function tc_apr_04_perubahan_aturan_tidak_mengubah_dokumen_yang_menunggu(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $direktur = $this->makeUser('management');
        $rule = $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($kepala)]);

        $req = $this->ajukanReq($this->pemohon());

        app(SaveApprovalRule::class)->handle($rule, [
            'document_type' => 'material_request', 'name' => 'Diubah', 'priority' => 100, 'is_active' => true, 'conditions' => [],
        ], [$this->lapisUser($direktur), $this->lapisUser($kepala)]);

        $s = $this->snapshotReq($req);
        $this->assertCount(1, $s->steps, 'BR-APR-01: snapshot memegang lapis saat diajukan.');
        $this->assertSame('Aturan uji', $s->rule_name);
        $this->assertSame([(int) $kepala->id], $this->approverTerbuka($req));

        $this->setuju($req, $kepala);
        $this->assertSame(MaterialRequestStatus::Approved, $req->refresh()->status);

        // Aturan dinonaktifkan: dokumen baru disetujui otomatis.
        app(SaveApprovalRule::class)->setActive($rule->refresh(), false);
        $this->assertSame(MaterialRequestStatus::Approved, $this->ajukanReq($this->pemohon())->status);
    }

    #[Test]
    public function tc_apr_05_pengaju_dilewati_dan_tidak_boleh_memutus(): void
    {
        $atasan = $this->makeUser('management');
        $pemohon = $this->pemohon(['manager_id' => $atasan->id]);
        $this->assignRole($pemohon, 'warehouse_head');
        $kepalaLain = $this->makeUser('warehouse_head');

        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisRole('warehouse_head')]);
        $req = $this->ajukanReq($pemohon);

        $this->assertSame([(int) $kepalaLain->id], $this->approverTerbuka($req), 'BR-APR-03: pemohon dilewati.');
        $this->assertStringContainsString('Pengaju/pemohon dilewati', implode(' ', $this->snapshotReq($req)->steps[0]['notes']), 'Catatan BR-APR-03 tanpa kode internal.');

        $this->gagal(fn () => $this->setuju($req, $pemohon), 'BR-APR-03');
        $this->gagal(fn () => app(ApproveRequest::class)->handle($req, $pemohon), 'BR-REQ-07');
    }

    #[Test]
    public function tc_apr_05b_lapis_yang_hanya_menunjuk_pengaju_dialihkan_ke_atasannya(): void
    {
        $atasan = $this->makeUser('management');
        $pemohon = $this->pemohon(['manager_id' => $atasan->id]);
        $this->assignRole($pemohon, 'warehouse_head');

        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($pemohon)]);
        $req = $this->ajukanReq($pemohon);

        $this->assertSame([(int) $atasan->id], $this->approverTerbuka($req));
        $this->assertStringContainsString('atasan pengaju', implode(' ', $this->snapshotReq($req)->steps[0]['notes']));

        $this->setuju($req, $atasan);
        $this->assertSame(MaterialRequestStatus::Approved, $req->refresh()->status);
    }

    #[Test]
    public function tc_apr_06_orang_sama_di_dua_lapis_berurutan_cukup_sekali(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $direktur = $this->makeUser('management');
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($kepala), $this->lapisUser($kepala), $this->lapisUser($direktur)]);

        $req = $this->ajukanReq($this->pemohon());
        $this->setuju($req, $kepala);

        $s = $this->snapshotReq($req);
        $this->assertSame(3, (int) $s->current_step, 'Lapis 2 terbawa dari lapis 1 (BR-APR-04).');
        $this->assertSame([(int) $direktur->id], $this->approverTerbuka($req));

        $otomatis = ApprovalDecision::query()->whereHas('task', fn ($q) => $q->where('approval_snapshot_id', $s->id)->where('step_no', 2))->sole();
        $this->assertSame(ApprovalDecisionType::Approved, $otomatis->decision);
        $this->assertStringContainsString('sudah menyetujui lapis 1', (string) $otomatis->comment, 'Catatan BR-APR-04 tanpa kode internal.');

        $this->setuju($req, $direktur);
        $this->assertSame(MaterialRequestStatus::Approved, $req->refresh()->status);
    }

    #[Test]
    public function tc_apr_07_lapis_diputus_berurutan_dan_reservasi_di_akhir(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $direktur = $this->makeUser('management');
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($kepala), $this->lapisUser($direktur)]);

        $req = $this->ajukanReq($this->pemohon());
        $this->assertSame(MaterialRequestStatus::PendingApproval, $req->status);
        $this->assertSame([(int) $kepala->id], $this->approverTerbuka($req));

        $this->gagal(fn () => $this->setuju($req, $direktur), 'BR-APR-01');

        $req = app(ApproveRequest::class)->handle($req, $kepala);
        $this->assertSame(MaterialRequestStatus::PendingApproval, $req->status, 'Masih ada lapis 2.');
        $this->assertSame(0, StockReservation::query()->forDocument('material_request', (int) $req->id)->count());
        $this->assertSame([(int) $direktur->id], $this->approverTerbuka($req));

        $req = app(ApproveRequest::class)->handle($req, $direktur);
        $this->assertSame(MaterialRequestStatus::Approved, $req->status);
        $this->assertSame((int) $direktur->id, (int) $req->approved_by);
        $this->assertSame(1, StockReservation::query()->active()->forDocument('material_request', (int) $req->id)->count());
        $this->assertSame(ApprovalSnapshotStatus::Approved, $this->snapshotReq($req)->status);
    }

    #[Test]
    public function tc_apr_08_cukup_salah_satu_keputusan_pertama_menang(): void
    {
        $k1 = $this->makeUser('warehouse_head');
        $k2 = $this->makeUser('warehouse_head');
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisRole('warehouse_head', DecisionMode::Any)]);

        $req = $this->ajukanReq($this->pemohon());
        $this->assertSame([(int) $k1->id, (int) $k2->id], $this->approverTerbuka($req));

        $tugasK2 = $this->tugasTerbuka($req, $k2);
        $this->setuju($req, $k1);

        $this->assertSame(MaterialRequestStatus::Approved, $req->refresh()->status);
        $this->assertSame(ApprovalTaskStatus::Superseded, $tugasK2->refresh()->status);
        $this->gagal(fn () => app(DecideApproval::class)->approve($tugasK2, $k2), 'BR-APR-09');
    }

    #[Test]
    public function tc_apr_08b_semua_harus_setuju(): void
    {
        $k1 = $this->makeUser('warehouse_head');
        $k2 = $this->makeUser('warehouse_head');
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisRole('warehouse_head', DecisionMode::All)]);

        $req = $this->ajukanReq($this->pemohon());
        $this->setuju($req, $k1);
        $this->assertSame(MaterialRequestStatus::PendingApproval, $req->refresh()->status);
        $this->assertSame([(int) $k2->id], $this->approverTerbuka($req));

        $this->setuju($req, $k2);
        $this->assertSame(MaterialRequestStatus::Approved, $req->refresh()->status);
    }

    #[Test]
    public function tc_apr_08c_berurutan_satu_per_satu(): void
    {
        $k1 = $this->makeUser('warehouse_head');
        $k2 = $this->makeUser('warehouse_head');
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisRole('warehouse_head', DecisionMode::Sequential)]);

        $req = $this->ajukanReq($this->pemohon());
        $this->assertSame([(int) $k1->id], $this->approverTerbuka($req), 'Hanya approver pertama yang mendapat tugas.');
        $this->gagal(fn () => $this->setuju($req, $k2), 'BR-APR-01');

        $this->setuju($req, $k1);
        $this->assertSame([(int) $k2->id], $this->approverTerbuka($req));

        $this->setuju($req, $k2);
        $this->assertSame(MaterialRequestStatus::Approved, $req->refresh()->status);
    }

    #[Test]
    public function tc_apr_09_tolak_wajib_alasan_dan_menutup_tugas_lain(): void
    {
        $k1 = $this->makeUser('warehouse_head');
        $k2 = $this->makeUser('warehouse_head', ScopeType::Warehouse, (int) $this->ckg->id);
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapis(ApproverType::WarehouseHead)]);

        $req = $this->ajukanReq($this->pemohon());
        $this->assertSame([(int) $k2->id], $this->approverTerbuka($req), 'Kepala gudang yang ditugaskan ke gudang sumber didahulukan (A-88).');

        $this->gagal(fn () => app(DecideApproval::class)->rejectDocument(ApprovalDocumentType::MaterialRequest, (int) $req->id, $k2, null), 'BR-GEN-11');

        app(DecideApproval::class)->rejectDocument(ApprovalDocumentType::MaterialRequest, (int) $req->id, $k2, $this->alasanTolak(), 'Proyek ditunda');

        $req->refresh();
        $this->assertSame(MaterialRequestStatus::Rejected, $req->status);
        $this->assertSame($this->alasanTolak(), (int) $req->cancel_reason_id);
        $this->assertSame(ApprovalSnapshotStatus::Rejected, $this->snapshotReq($req)->status);
        $this->assertSame(0, StockReservation::query()->forDocument('material_request', (int) $req->id)->count());
        $this->assertSame(0, ApprovalTask::query()->open()->count());
        $this->assertNotNull($k1);
    }
}

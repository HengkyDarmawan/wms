<?php

declare(strict_types=1);

namespace Tests\Feature\Approval;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Approval\Actions\DecideApproval;
use App\Domain\Approval\Actions\SaveApprovalRule;
use App\Domain\Approval\Actions\SimulateApproval;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApprovalSnapshotStatus;
use App\Domain\Approval\Enums\ApprovalTaskStatus;
use App\Domain\Approval\Enums\ApproverType;
use App\Domain\Approval\Enums\DecisionMode;
use App\Domain\Approval\Exceptions\ApprovalRuleException;
use App\Domain\Approval\Models\ApprovalRule;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Models\ApprovalStep;
use App\Domain\Approval\Models\ApprovalTask;
use App\Domain\Approval\Support\ApprovalContext;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Request\Actions\CancelRequest;
use App\Domain\Request\Enums\MaterialRequestStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalScenario;
use Tests\TenantTestCase;

/**
 * TC-APR-15 s.d. TC-APR-17 — dokumen ditarik saat menunggu, simulasi
 * (BR-APR-11), dan pengelolaan aturan (D-17, P-03, BR-GEN-10).
 */
class RuleSimulationTest extends TenantTestCase
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
        } catch (ApprovalRuleException $e) {
            $this->assertSame($aturan, $e->rule, $e->getMessage());
        }
    }

    #[Test]
    public function tc_apr_15_req_dibatalkan_saat_menunggu_menghentikan_approval(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $pemohon = $this->pemohon();
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($kepala)]);

        $req = $this->ajukanReq($pemohon);
        $tugas = $this->tugasTerbuka($req, $kepala);

        $alasan = (int) ReasonCode::query()->where('context', ReasonContext::Cancel->value)->value('id');
        $req = app(CancelRequest::class)->handle($req, $alasan, null, $pemohon);

        $this->assertSame(MaterialRequestStatus::Cancelled, $req->status);
        $this->assertSame(ApprovalSnapshotStatus::Cancelled, $this->snapshotReq($req)->status);
        $this->assertSame(ApprovalTaskStatus::Superseded, $tugas->refresh()->status);
        $this->gagal(fn () => app(DecideApproval::class)->approve($tugas, $kepala), 'BR-APR-09');
    }

    #[Test]
    public function tc_apr_16_simulasi_menjawab_siapa_yang_akan_menyetujui(): void
    {
        $kepala = $this->makeUser('warehouse_head', ScopeType::Warehouse, (int) $this->ckg->id);
        $direktur = $this->makeUser('management');
        $this->aturan(ApprovalDocumentType::MaterialRequest, [
            $this->lapis(ApproverType::WarehouseHead, DecisionMode::All),
            $this->lapisRole('management'),
        ], ['line_count_min' => 2], 10, 'Besar');
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapis(ApproverType::WarehouseHead)], [], 100, 'Lainnya');

        $req = $this->ajukanReq($this->pemohon(), [['qty' => 1], ['qty' => 2]]);
        $sebelum = [ApprovalSnapshot::count(), ApprovalTask::count()];

        $sim = app(SimulateApproval::class);
        $hasil = $sim->run($sim->contextForDocument(ApprovalDocumentType::MaterialRequest, $req->number));

        $this->assertSame('Besar', $hasil['rule']['name']);
        $this->assertSame([(int) $kepala->id], $hasil['layers'][0]['approver_user_ids']);
        $this->assertSame([(int) $direktur->id], $hasil['layers'][1]['approver_user_ids']);
        $this->assertSame(
            array_column($this->snapshotReq($req)->steps, 'approver_user_ids'),
            array_column($hasil['layers'], 'approver_user_ids'),
            'Simulasi sama dengan hasil pengajuan sungguhan.',
        );
        $this->assertSame([true, false], array_column($hasil['evaluations'], 'chosen'));

        // Konteks rekaan: satu baris di CKG → aturan "Lainnya".
        $manual = $sim->run(new ApprovalContext(ApprovalDocumentType::MaterialRequest, warehouseIds: [(int) $this->ckg->id], lineCount: 1, maxLineQty: 5));
        $this->assertSame('Lainnya', $manual['rule']['name']);

        // Jenis tanpa aturan → disetujui otomatis.
        $this->assertTrue($sim->run(new ApprovalContext(ApprovalDocumentType::VendorReturn, warehouseIds: [(int) $this->ckg->id]))['auto_approved']);

        // Draf aturan sebelum disimpan (BR-APR-11).
        $ctx = $sim->contextForDocument(ApprovalDocumentType::MaterialRequest, $req->number);
        $this->assertFalse($sim->run($ctx, ['conditions' => ['line_count_min' => 5], 'steps' => [$this->lapisUser($direktur)]])['matched']);
        $draf = $sim->run($ctx, ['conditions' => [], 'steps' => [$this->lapisUser($direktur)]]);
        $this->assertTrue($draf['matched']);
        $this->assertSame([(int) $direktur->id], $draf['layers'][0]['approver_user_ids']);

        $this->gagal(fn () => $sim->contextForDocument(ApprovalDocumentType::MaterialRequest, 'REQ/TIDAK/ADA'), 'BR-APR-11');
        $this->assertSame($sebelum, [ApprovalSnapshot::count(), ApprovalTask::count()], 'Simulasi tidak menulis apa pun.');
    }

    #[Test]
    public function tc_apr_17_aturan_divalidasi_dan_dinonaktifkan_bukan_dihapus(): void
    {
        $save = app(SaveApprovalRule::class);
        $kepala = $this->makeUser('warehouse_head');
        $data = ['document_type' => 'material_request', 'name' => 'Aturan A', 'priority' => 50, 'is_active' => true, 'conditions' => []];

        $this->gagal(fn () => $save->handle(null, ['document_type' => 'waste_disposal'] + $data, [$this->lapisUser($kepala)]), 'BR-GEN-10');
        $this->gagal(fn () => $save->handle(null, $data, []), 'D-17');
        $this->gagal(fn () => $save->handle(null, $data, [['approver_type' => 'user', 'decision_mode' => 'any', 'timeout_hours' => 24]]), 'BR-GEN-11');
        $this->gagal(fn () => $save->handle(null, $data, [$this->lapisUser($kepala, DecisionMode::Any, ['timeout_hours' => 0])]), 'BR-APR-08');
        $this->gagal(fn () => $save->handle(null, ['priority' => 0] + $data, [$this->lapisUser($kepala)]), 'BR-APR-01');
        $this->gagal(fn () => $save->handle(null, ['name' => ' '] + $data, [$this->lapisUser($kepala)]), 'BR-GEN-11');

        $rule = $save->handle(null, $data, [$this->lapisUser($kepala), $this->lapis(ApproverType::DirectManager)]);
        $this->assertSame([1, 2], $rule->steps->pluck('step_no')->all());
        $this->assertSame('web', $rule->steps->first()->channel->value, 'WhatsApp = Fase 2a (BR-GEN-10).');

        $rule = $save->handle($rule, ['name' => 'Aturan B'] + $data, [$this->lapis(ApproverType::ProjectPic)]);
        $this->assertSame('Aturan B', $rule->name);
        $this->assertSame(1, ApprovalStep::query()->where('approval_rule_id', $rule->id)->count());
        $this->gagal(fn () => $save->handle($rule, ['document_type' => 'vendor_return'] + $data, [$this->lapisUser($kepala)]), 'BR-APR-01');

        $save->setActive($rule, false);
        $this->assertFalse($rule->refresh()->is_active);
        $this->assertSame(1, ApprovalRule::count(), 'P-03: aturan tidak dihapus.');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Approval;

use App\Domain\Approval\Actions\DecideApproval;
use App\Domain\Approval\Actions\EscalateApprovalTask;
use App\Domain\Approval\Actions\SaveDelegation;
use App\Domain\Approval\Enums\ApprovalDecisionType;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApprovalTaskStatus;
use App\Domain\Approval\Enums\ApproverType;
use App\Domain\Approval\Enums\DecisionMode;
use App\Domain\Approval\Exceptions\ApprovalRuleException;
use App\Domain\Approval\Models\ApprovalDelegation;
use App\Domain\Approval\Models\ApprovalTask;
use App\Domain\Request\Enums\MaterialRequestStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalScenario;
use Tests\TenantTestCase;

/**
 * TC-APR-10 s.d. TC-APR-14 — delegasi (BR-APR-05), eskalasi batas waktu dan
 * approver nonaktif (BR-APR-06, BR-APR-08, A-18), eskalasi manual (A-90).
 */
class DelegationEscalationTest extends TenantTestCase
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

    /** @return array<string, mixed> */
    private function periode(array $extra = []): array
    {
        return $extra + [
            'starts_at' => now()->subHour()->toDateTimeString(),
            'ends_at' => now()->addDays(2)->toDateTimeString(),
        ];
    }

    #[Test]
    public function tc_apr_10_tugas_baru_diterima_delegat_selama_periode(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $wakil = $this->makeUser('warehouse_head');
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($kepala)]);

        app(SaveDelegation::class)->create($this->periode(['to_user_id' => $wakil->id, 'notes' => 'Cuti']), $kepala);

        $req = $this->ajukanReq($this->pemohon());
        $this->assertSame([(int) $wakil->id], $this->approverTerbuka($req));

        $tugas = $this->tugasTerbuka($req, $wakil);
        $this->assertSame((int) $kepala->id, (int) $tugas->delegated_from_user_id);
        $this->assertSame(ApprovalDecisionType::Delegated, $tugas->decisions()->sole()->decision);

        $this->gagal(fn () => app(DecideApproval::class)->approveDocument(ApprovalDocumentType::MaterialRequest, (int) $req->id, $kepala), 'BR-APR-01');
        app(DecideApproval::class)->approve($tugas, $wakil);
        $this->assertSame(MaterialRequestStatus::Approved, $req->refresh()->status);

        // Setelah periode berakhir tugas kembali ke pemberi delegasi.
        $this->travel(3)->days();
        $this->assertSame([(int) $kepala->id], $this->approverTerbuka($this->ajukanReq($this->pemohon())));
    }

    #[Test]
    public function tc_apr_10b_tugas_terbuka_pindah_saat_delegasi_dibuat(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $wakil = $this->makeUser('warehouse_head');
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($kepala)]);

        $req = $this->ajukanReq($this->pemohon());
        $lama = $this->tugasTerbuka($req, $kepala);

        app(SaveDelegation::class)->create($this->periode(['to_user_id' => $wakil->id]), $kepala);

        $this->assertSame([(int) $wakil->id], $this->approverTerbuka($req));
        $this->assertSame(ApprovalTaskStatus::Superseded, $lama->refresh()->status);
    }

    #[Test]
    public function tc_apr_11_delegasi_tidak_berantai_dan_divalidasi(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $wakil = $this->makeUser('warehouse_head');
        $lain = $this->makeUser('warehouse_head');
        $staf = $this->makeUser('warehouse_staff');
        $simpan = app(SaveDelegation::class);

        $this->gagal(fn () => $simpan->create($this->periode(['to_user_id' => $kepala->id]), $kepala), 'BR-APR-05');
        $this->gagal(fn () => $simpan->create($this->periode(['to_user_id' => $staf->id, 'document_types' => ['material_request']]), $kepala), 'BR-APR-05');
        $this->gagal(fn () => $simpan->create(['to_user_id' => $wakil->id, 'starts_at' => now()->subDays(3)->toDateTimeString(), 'ends_at' => now()->subDay()->toDateTimeString()], $kepala), 'BR-APR-05');
        $this->gagal(fn () => $simpan->create($this->periode(['to_user_id' => $wakil->id, 'from_user_id' => $lain->id]), $kepala), 'BR-GEN-09');

        $d = $simpan->create($this->periode(['to_user_id' => $wakil->id]), $kepala);

        $this->gagal(fn () => $simpan->create($this->periode(['to_user_id' => $lain->id]), $wakil), 'BR-APR-05'); // delegat mendelegasikan ulang
        $this->gagal(fn () => $simpan->create($this->periode(['to_user_id' => $kepala->id]), $lain), 'BR-APR-05'); // ke orang yang sedang mendelegasikan
        $this->gagal(fn () => $simpan->create($this->periode(['to_user_id' => $lain->id]), $kepala), 'BR-APR-05'); // tumpang tindih

        // Diakhiri, tidak dihapus (P-03).
        $simpan->end($d, $kepala);
        $this->assertFalse($d->refresh()->is_active);
        $this->assertSame(1, ApprovalDelegation::query()->count());
        $this->gagal(fn () => $simpan->end($d, $kepala), 'BR-APR-05');

        // Mesin hanya mengikuti satu lompatan walau data berantai dipaksa masuk.
        ApprovalDelegation::create(['from_user_id' => $kepala->id, 'to_user_id' => $wakil->id, 'starts_at' => now()->subHour(), 'ends_at' => now()->addDay()]);
        ApprovalDelegation::create(['from_user_id' => $wakil->id, 'to_user_id' => $lain->id, 'starts_at' => now()->subHour(), 'ends_at' => now()->addDay()]);
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($kepala)]);
        $this->assertSame([(int) $wakil->id], $this->approverTerbuka($this->ajukanReq($this->pemohon())));
    }

    #[Test]
    public function tc_apr_12_lewat_batas_waktu_dieskalasi_ke_cadangan_atasan_lalu_admin(): void
    {
        $atasan = $this->makeUser('management');
        $kepala = $this->makeUser('warehouse_head', attributes: ['manager_id' => $atasan->id]);
        $cadangan = $this->makeUser('warehouse_head');
        $admin = $this->makeUser('company_admin');
        $tanpaAtasan = $this->makeUser('warehouse_head');

        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($kepala, DecisionMode::Any, [
            'backup_approver_type' => ApproverType::User->value, 'backup_ref_id' => $cadangan->id, 'timeout_hours' => 24,
        ])], ['line_count_min' => 2], 10, 'Dengan cadangan');
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($kepala)], ['line_qty_min' => 300], 20, 'Tanpa cadangan');
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($tanpaAtasan)], [], 30, 'Tanpa atasan');

        $reqA = $this->ajukanReq($this->pemohon(), [['qty' => 1], ['qty' => 1]]);
        $reqB = $this->ajukanReq($this->pemohon(), [['qty' => 300]]);
        $reqC = $this->ajukanReq($this->pemohon());
        $lamaA = $this->tugasTerbuka($reqA, $kepala);

        // Belum lewat batas: tidak ada yang berubah.
        $this->travel(23)->hours();
        $this->artisan('approval:escalate')->assertSuccessful();
        $this->assertSame([(int) $kepala->id], $this->approverTerbuka($reqA));

        $this->travel(2)->hours();
        $this->artisan('approval:escalate')->assertSuccessful();

        $this->assertSame([(int) $cadangan->id], $this->approverTerbuka($reqA), 'Cadangan lapis lebih dulu.');
        $this->assertSame([(int) $atasan->id], $this->approverTerbuka($reqB), 'Tanpa cadangan: atasan approver.');
        $this->assertSame([(int) $admin->id], $this->approverTerbuka($reqC), 'Tanpa atasan: Admin Company.');

        $this->assertSame(ApprovalTaskStatus::Expired, $lamaA->refresh()->status);
        $keputusan = $lamaA->decisions()->sole();
        $this->assertSame(ApprovalDecisionType::Escalated, $keputusan->decision);
        $this->assertStringContainsString('lewat batas waktu', (string) $keputusan->comment);
        $this->assertStringContainsString('peringatan', (string) $this->tugasTerbuka($reqC, $admin)->escalatedFrom->decisions()->sole()->comment);

        app(DecideApproval::class)->approve($this->tugasTerbuka($reqA, $cadangan), $cadangan);
        $this->assertSame(MaterialRequestStatus::Approved, $reqA->refresh()->status);
    }

    #[Test]
    public function tc_apr_13_approver_nonaktif_dialihkan(): void
    {
        $atasan = $this->makeUser('management');
        $keluar = $this->makeUser('warehouse_head', attributes: ['manager_id' => $atasan->id, 'is_active' => false]);
        $kepala = $this->makeUser('warehouse_head', attributes: ['manager_id' => $atasan->id]);

        // Nonaktif saat diajukan: langsung ke atasannya (BR-APR-06).
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($keluar)], ['line_count_min' => 2], 10, 'Nonaktif');
        $req = $this->ajukanReq($this->pemohon(), [['qty' => 1], ['qty' => 1]]);
        $this->assertSame([(int) $atasan->id], $this->approverTerbuka($req));
        $this->assertStringContainsString('BR-APR-06', implode(' ', $this->snapshotReq($req)->steps[0]['notes']));

        // Nonaktif sesudah diajukan: dialihkan penjadwal tanpa menunggu batas waktu.
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($kepala)], [], 20, 'Aktif');
        $req2 = $this->ajukanReq($this->pemohon());
        $kepala->forceFill(['is_active' => false])->save();

        $hasil = app(EscalateApprovalTask::class)->runScheduled();
        $this->assertSame(1, $hasil['escalated']);
        $this->assertSame([(int) $atasan->id], $this->approverTerbuka($req2));
    }

    #[Test]
    public function tc_apr_14_eskalasi_manual_hanya_pemegang_izin(): void
    {
        $atasan = $this->makeUser('management');
        $kepala = $this->makeUser('warehouse_head', attributes: ['manager_id' => $atasan->id]);
        $admin = $this->makeUser('company_admin');
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($kepala)], ['line_count_min' => 2], 10, 'Kepala');
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($admin)], [], 20, 'Admin');

        $req = $this->ajukanReq($this->pemohon(), [['qty' => 1], ['qty' => 1]]);
        $tugas = $this->tugasTerbuka($req, $kepala);

        $this->gagal(fn () => app(EscalateApprovalTask::class)->handle($tugas, $this->makeUser('warehouse_staff')), 'BR-GEN-09');

        $baru = app(EscalateApprovalTask::class)->handle($tugas, $admin);
        $this->assertSame((int) $atasan->id, (int) $baru->approver_user_id);
        $this->assertSame((int) $tugas->id, (int) $baru->escalated_from_task_id);
        $this->assertSame((int) $admin->id, (int) $tugas->refresh()->decisions()->sole()->decided_by);

        // Tidak ada tujuan: approver satu-satunya Admin Company tanpa atasan.
        $req2 = $this->ajukanReq($this->pemohon());
        $this->gagal(fn () => app(EscalateApprovalTask::class)->handle($this->tugasTerbuka($req2, $admin), $admin), 'BR-APR-06');
        $this->assertSame(1, ApprovalTask::query()->open()->where('approver_user_id', $admin->id)->count());
    }
}

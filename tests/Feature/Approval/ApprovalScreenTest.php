<?php

declare(strict_types=1);

namespace Tests\Feature\Approval;

use App\Domain\Access\Models\Role;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApproverType;
use App\Domain\Approval\Livewire\ApprovalSimulation;
use App\Domain\Approval\Livewire\DelegationManager;
use App\Domain\Approval\Livewire\RuleForm;
use App\Domain\Approval\Livewire\RuleList;
use App\Domain\Approval\Livewire\TaskInbox;
use App\Domain\Approval\Models\ApprovalDelegation;
use App\Domain\Approval\Models\ApprovalRule;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Request\Enums\MaterialRequestStatus;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalScenario;
use Tests\TenantTestCase;

/**
 * TC-APR-20 — layar approval: izin (BR-GEN-09), kotak tugas, form aturan
 * dengan simulasi, delegasi, simulasi, panel riwayat. TC-APR-22 — permission
 * dan pemetaan role (A-86).
 */
class ApprovalScreenTest extends TenantTestCase
{
    use ApprovalScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanSkenario();
    }

    #[Test]
    public function tc_apr_20_izin_halaman_approval(): void
    {
        $rule = $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapis(ApproverType::WarehouseHead)]);
        $halaman = ['approvals', 'approval-rules', 'approval-rules/create', 'approval-rules/'.$rule->id.'/edit', 'approval-delegations', 'approval-simulation'];

        $admin = $this->makeUser('company_admin');
        foreach ($halaman as $url) {
            $this->actingAs($admin)->get($this->tenantUrl($url))->assertOk();
        }

        $harapan = [
            'warehouse_staff' => [403, 403, 403, 403, 403, 403],
            'driver' => [403, 403, 403, 403, 403, 403],
            'warehouse_head' => [200, 200, 403, 403, 200, 403],
            'management' => [200, 200, 403, 403, 200, 200],
            // Sejak modul Count, Auditor Internal memegang `count.approve` (BR-OPN-09).
            'internal_auditor' => [200, 200, 403, 403, 403, 403],
        ];

        foreach ($harapan as $role => $kode) {
            $u = $this->makeUser($role);
            foreach ($halaman as $i => $url) {
                $this->actingAs($u)->get($this->tenantUrl($url))->assertStatus($kode[$i]);
            }
        }

        // Menu & angka tugas di sidebar untuk approver.
        $kepala = $this->makeUser('warehouse_head');
        $this->ajukanReq($this->pemohon());
        $this->actingAs($kepala)->get($this->tenantUrl('/'))->assertOk()
            ->assertSee(__('Tugas approval saya'))->assertSee(__('Delegasi approval'));
    }

    #[Test]
    public function tc_apr_20b_kotak_tugas_setujui_dan_tolak(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($kepala)]);
        $setuju = $this->ajukanReq($this->pemohon());
        $tolak = $this->ajukanReq($this->pemohon());

        $komponen = Livewire::actingAs($kepala)->test(TaskInbox::class)
            ->assertOk()
            ->assertSee($setuju->number)
            ->assertSee($tolak->number)
            ->call('setujui', $this->tugasTerbuka($setuju, $kepala)->id)
            ->assertSet('ruleError', '');

        $this->assertSame(MaterialRequestStatus::Approved, $setuju->refresh()->status);

        $komponen->call('mintaTolak', $this->tugasTerbuka($tolak, $kepala)->id)
            ->call('tolak')
            ->assertHasErrors('form.reason')
            ->set('form.reason', (string) ReasonCode::query()->where('context', ReasonContext::Reject->value)->value('code'))
            ->call('tolak')
            ->assertSet('ruleError', '');

        $this->assertSame(MaterialRequestStatus::Rejected, $tolak->refresh()->status);
        $komponen->call('pilihTab', 'riwayat')->assertSee($setuju->number);

        // Orang lain tidak bisa memutus tugas ini.
        $req3 = $this->ajukanReq($this->pemohon());
        Livewire::actingAs($this->makeUser('warehouse_head'))->test(TaskInbox::class)
            ->assertDontSee($req3->number)
            ->call('setujui', $this->tugasTerbuka($req3, $kepala)->id)
            ->assertForbidden();

        // Admin melihat semua tugas terbuka dan bisa mengeskalasi.
        $atasan = $this->makeUser('management');
        $kepala->forceFill(['manager_id' => $atasan->id])->save();
        Livewire::actingAs($this->makeUser('company_admin'))->test(TaskInbox::class)
            ->call('pilihTab', 'semua')
            ->assertSee($req3->number)
            ->call('eskalasi', $this->tugasTerbuka($req3, $kepala)->id)
            ->assertSet('ruleError', '');
        $this->assertSame([(int) $atasan->id], $this->approverTerbuka($req3));
    }

    #[Test]
    public function tc_apr_20c_form_aturan_dengan_simulasi_lalu_simpan(): void
    {
        $admin = $this->makeUser('company_admin');
        $direktur = $this->makeUser('management');
        $contoh = $this->ajukanReq($this->pemohon(), [['qty' => 1], ['qty' => 1]]);

        Livewire::actingAs($admin)->test(RuleForm::class)
            ->assertOk()
            ->set('form.name', 'REQ dua baris')
            ->set('form.priority', '10')
            ->set('conditions.line_count_min', '2')
            ->set('steps.0.approver_type', 'role')
            ->set('steps.0.approver_ref_id', (string) Role::findByCode('management')->id)
            ->set('sampleNumber', $contoh->number)
            ->call('simulasikan')
            ->assertSet('ruleError', '')
            ->assertSee($direktur->name)
            ->call('simpan')
            ->assertRedirect();

        $rule = ApprovalRule::query()->where('name', 'REQ dua baris')->sole();
        $this->assertSame(['match' => 'all', 'line_count_min' => 2], $rule->conditions);

        Livewire::actingAs($admin)->test(RuleList::class)->assertSee('REQ dua baris')
            ->call('setAktif', $rule->id, false);
        $this->assertFalse($rule->refresh()->is_active);

        Livewire::actingAs($admin)->test(RuleForm::class, ['rule' => $rule])->assertSet('form.name', 'REQ dua baris');
        Livewire::actingAs($this->makeUser('warehouse_head'))->test(RuleForm::class)->assertForbidden();
    }

    #[Test]
    public function tc_apr_20d_layar_delegasi_dan_simulasi(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $wakil = $this->makeUser('warehouse_head');

        Livewire::actingAs($kepala)->test(DelegationManager::class)
            ->call('buat')
            ->set('form.to_user_id', (string) $wakil->id)
            ->call('simpan')
            ->assertSet('ruleError', '')
            ->assertSee($wakil->name);

        $d = ApprovalDelegation::query()->sole();
        $this->assertSame((int) $kepala->id, (int) $d->from_user_id);

        Livewire::actingAs($kepala)->test(DelegationManager::class)->call('akhiri', $d->id);
        $this->assertFalse($d->refresh()->is_active);

        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($kepala)]);
        $req = $this->ajukanReq($this->pemohon());

        Livewire::actingAs($this->makeUser('company_admin'))->test(ApprovalSimulation::class)
            ->set('documentType', 'material_request')
            ->set('number', $req->number)
            ->call('simulasikan')
            ->assertSet('ruleError', '')
            ->assertSee($kepala->name)
            ->set('source', 'manual')
            ->set('manual.line_count', '3')
            ->call('simulasikan')
            ->assertSee('Aturan uji');

        // Panel riwayat approval di detail REQ.
        $this->actingAs($kepala)->get($this->tenantUrl('requests/'.$req->id))
            ->assertOk()->assertSee(__('Riwayat approval'))->assertSee('Aturan uji');
    }

    #[Test]
    public function tc_apr_22_permission_dan_approver_tanpa_izin(): void
    {
        $this->assertTrue($this->makeUser('warehouse_head')->hasPermission('request.approve'));
        $manajemen = $this->makeUser('management');
        $this->assertTrue($manajemen->hasPermission('request.approve'));
        $this->assertTrue($manajemen->hasPermission('vendor_return.approve'));
        $this->assertFalse($this->makeUser('internal_requester')->hasPermission('request.approve'));
        $this->assertFalse($this->makeUser('warehouse_staff')->hasPermission('approval.delegate'));
        $this->assertTrue($this->makeUser('company_admin')->hasPermission('approval.escalate'));

        // A-86: user yang ditunjuk aturan tetapi tidak memegang request.approve
        // tidak memenuhi syarat; lapisnya dialihkan ke atasannya.
        $kepala = $this->makeUser('warehouse_head');
        $staf = $this->makeUser('warehouse_staff', attributes: ['manager_id' => $kepala->id]);
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($staf)]);

        $req = $this->ajukanReq($this->pemohon());
        $this->assertSame([(int) $kepala->id], $this->approverTerbuka($req));
    }
}

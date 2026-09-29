<?php

declare(strict_types=1);

namespace Tests\Feature\Approval;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\OrgUnit;
use App\Domain\Access\Models\Position;
use App\Domain\Approval\Actions\InstallBasicApprovalRules;
use App\Domain\Approval\Actions\SimulateApproval;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApproverType;
use App\Domain\Approval\Livewire\ApprovalMap;
use App\Domain\Approval\Livewire\DelegationManager;
use App\Domain\Approval\Livewire\RuleForm;
use App\Domain\Approval\Livewire\RuleList;
use App\Domain\Approval\Models\ApprovalRule;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Approval\Support\ApprovalRegistry;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalScenario;
use Tests\TenantTestCase;

/**
 * TC-APR-24 s.d. TC-APR-29 — approval sederhana berbasis peta jabatan
 * (A-347–A-350): aturan dasar satu klik, Peta approval, form mode sederhana
 * dengan prioritas otomatis, dan kotak pilihan bergaya tag.
 */
class SimpleApprovalTest extends TenantTestCase
{
    use ApprovalScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanSkenario();
    }

    #[Test]
    public function tc_apr_24_aturan_dasar_idempoten_tidak_menimpa_dan_melewati_lapis_minimum(): void
    {
        $admin = $this->makeUser('company_admin');
        $poLama = $this->aturan(ApprovalDocumentType::PurchaseOrder, [$this->lapisRole('management')], [], 10, 'PO lama');

        Livewire::actingAs($admin)->test(RuleList::class)
            ->call('bukaAturanDasar')
            ->assertSee(__('dilewati: sudah dijaga lapis minimum sistem'))
            ->assertSee(__('dilewati: sudah punya aturan'))
            ->call('pasangAturanDasar')
            ->assertHasNoErrors()
            ->assertSet('dialogDasar', false);

        $harap = collect(app(ApprovalRegistry::class)->types())
            ->reject(fn (ApprovalDocumentType $t) => in_array($t, InstallBasicApprovalRules::DILEWATI, true) || $t === ApprovalDocumentType::PurchaseOrder)
            ->map->value->sort()->values()->all();

        $dasar = ApprovalRule::query()->where('is_basic', true)->with('steps')->get();

        $this->assertSame($harap, $dasar->map(fn ($r) => $r->document_type->value)->sort()->values()->all());
        $this->assertFalse($dasar->contains(fn ($r) => in_array($r->document_type, InstallBasicApprovalRules::DILEWATI, true)), 'ADJ, OPN, ISU tetap lapis minimum.');
        $this->assertTrue($dasar->every(fn ($r) => $r->priority === 900 && $r->is_active && $r->steps->count() === 1 && $r->steps->first()->timeout_hours === 24));

        $req = $dasar->firstWhere('document_type', ApprovalDocumentType::MaterialRequest);
        $this->assertSame(ApproverType::WarehouseHead, $req->steps->first()->approver_type);

        $trf = $dasar->firstWhere('document_type', ApprovalDocumentType::Transfer);
        $this->assertSame(ApproverType::DirectManager, $trf->steps->first()->approver_type);
        $this->assertSame(1, $trf->steps->first()->manager_levels);

        // Aturan PO yang sudah ada tidak disentuh.
        $this->assertSame('PO lama', $poLama->refresh()->name);
        $this->assertFalse($poLama->is_basic);

        // Klik kedua tidak menggandakan apa pun.
        $jumlah = ApprovalRule::query()->count();
        $this->assertSame([], app(InstallBasicApprovalRules::class)->handle($admin));
        $this->assertSame($jumlah, ApprovalRule::query()->count());

        // Aturan dasar tetap aturan biasa: bisa dinonaktifkan; lalu tidak dipasang ulang.
        Livewire::actingAs($admin)->test(RuleList::class)->call('setAktif', $trf->id, false);
        $this->assertSame([], app(InstallBasicApprovalRules::class)->handle($admin));

        // Tanpa approval_rule.manage → ditolak.
        Livewire::actingAs($this->makeUser('warehouse_head'))->test(RuleList::class)
            ->call('pasangAturanDasar')->assertForbidden();
    }

    #[Test]
    public function tc_apr_25_permintaan_klien_ke_kepala_gudang_terkait(): void
    {
        app(InstallBasicApprovalRules::class)->handle();

        $kepalaCkg = $this->makeUser('warehouse_head', ScopeType::Warehouse, (int) $this->ckg->id);
        $this->makeUser('warehouse_head', ScopeType::Warehouse, (int) $this->bks->id);
        $klien = $this->makeClient();
        $portal = $this->makeUser('', attributes: ['client_id' => $klien->id]);

        $sim = app(SimulateApproval::class);
        $ctx = $sim->contextForPerson(ApprovalDocumentType::MaterialRequest, (int) $portal->id, (int) $this->ckg->id);

        $this->assertTrue($ctx->fromClient, 'Pemohon klien dihitung permintaan dari klien.');

        $hasil = $sim->run($ctx);

        $this->assertSame('Aturan dasar — Permintaan Material', $hasil['rule']['name']);
        $this->assertSame([(int) $kepalaCkg->id], $hasil['layers'][0]['approver_user_ids'], 'Kepala gudang CKG saja, bukan BKS.');
    }

    #[Test]
    public function tc_apr_26_peta_approval_sama_dengan_simulasi_dan_menjelaskan_lapis_minimum(): void
    {
        $admin = $this->makeUser('company_admin');
        $unit = OrgUnit::create(['code' => 'GDG', 'name' => 'Gudang']);
        $jKepala = Position::create(['org_unit_id' => $unit->id, 'code' => 'KAG', 'name' => 'Kepala Gudang', 'level' => 1, 'is_active' => true]);
        $jStaf = Position::create(['org_unit_id' => $unit->id, 'code' => 'STF', 'name' => 'Staf Gudang', 'level' => 2, 'is_active' => true, 'reports_to_position_id' => $jKepala->id]);
        $andi = $this->makeUser('warehouse_head', attributes: ['name' => 'Andi Kepala', 'position_id' => $jKepala->id]);
        $dedi = $this->makeUser('warehouse_staff', attributes: ['name' => 'Dedi Staf', 'position_id' => $jStaf->id]);

        app(InstallBasicApprovalRules::class)->handle($admin);

        $komponen = Livewire::actingAs($admin)->test(ApprovalMap::class)
            ->assertSee('Transfer disetujui atasan langsung pemohon.')
            ->assertSee(__('tidak ada aturan; dijaga lapis minimum sistem:'))
            ->assertSee('Staf Gudang')
            ->assertSee('Andi Kepala')
            ->set('pemohon', (string) $dedi->id)
            ->set('jenis', ApprovalDocumentType::Transfer->value)
            ->set('gudang', (string) $this->ckg->id)
            ->call('cek')
            ->assertHasNoErrors();

        $harap = app(SimulateApproval::class)->run(
            app(SimulateApproval::class)->contextForPerson(ApprovalDocumentType::Transfer, (int) $dedi->id, (int) $this->ckg->id),
        );

        $this->assertSame($harap['layers'], $komponen->get('hasil')['layers']);
        $this->assertSame([(int) $andi->id], $komponen->get('hasil')['layers'][0]['approver_user_ids']);

        // Penyesuaian stok tanpa aturan: bukan "disetujui otomatis".
        $komponen->set('jenis', ApprovalDocumentType::StockAdjustment->value)->call('cek');
        $this->assertFalse($komponen->get('hasil')['auto_approved']);
        $this->assertNotNull($komponen->get('hasil')['minimum']);

        // Halaman berizin approval_rule.view.
        $this->actingAs($admin)->get($this->tenantUrl('approval-rules/map'))->assertOk()->assertSee(__('Peta approval'));
        $this->actingAs($this->makeUser('warehouse_staff'))->get($this->tenantUrl('approval-rules/map'))->assertForbidden();
    }

    #[Test]
    public function tc_apr_27_mode_sederhana_menyimpan_data_yang_sama_dengan_mode_lanjutan(): void
    {
        $admin = $this->makeUser('company_admin');

        Livewire::actingAs($admin)->test(RuleForm::class)
            ->assertSet('mode', 'sederhana')
            ->call('pilihJenis', ApprovalDocumentType::Transfer->value)
            ->call('pilihApprover', 0, 'direct_manager')
            ->set('steps.0.manager_levels', '2')
            ->call('tambahLapis')
            ->call('pilihBerlaku', 'bila')
            ->set('conditions.warehouse_ids', [(string) $this->ckg->id])
            ->assertSee('Transfer di gudang CKG disetujui atasan pemohon 2 tingkat di atas, lalu Manajemen.')
            ->call('simpan')
            ->assertHasNoErrors();

        $sederhana = ApprovalRule::query()->with('steps')->latest('id')->firstOrFail();

        Livewire::actingAs($admin)->test(RuleForm::class)
            ->call('keMode', 'lanjutan')
            ->set('form.document_type', ApprovalDocumentType::Transfer->value)
            ->set('form.name', 'Lanjutan')
            ->set('steps.0.approver_type', ApproverType::DirectManager->value)
            ->set('steps.0.manager_levels', '2')
            ->call('tambahLapis')
            ->set('conditions.warehouse_ids', [(string) $this->ckg->id])
            ->call('simpan')
            ->assertHasNoErrors();

        $lanjutan = ApprovalRule::query()->with('steps')->latest('id')->firstOrFail();

        $bentuk = fn (ApprovalRule $r) => [
            'conditions' => $r->conditions,
            'priority' => $r->priority,
            'steps' => $r->steps->map(fn ($s) => collect($s->toPlanInput())->except('step_no')->all() + ['no' => $s->step_no])->all(),
        ];

        $this->assertNotSame($sederhana->id, $lanjutan->id);
        $this->assertSame($bentuk($lanjutan), $bentuk($sederhana));
        $this->assertSame('Transfer — di gudang CKG', $sederhana->name, 'Nama otomatis dari pilihan.');

        // Aturan tersimpan yang memakai isian lanjutan dibuka di mode lanjutan.
        $rumit = $this->aturan(ApprovalDocumentType::Transfer, [$this->lapisUser($admin, extra: ['timeout_hours' => 48])]);
        Livewire::actingAs($admin)->test(RuleForm::class, ['rule' => $rumit])->assertSet('mode', 'lanjutan');
        Livewire::actingAs($admin)->test(RuleForm::class, ['rule' => $sederhana])->assertSet('mode', 'sederhana')->assertSet('berlaku', 'bila');
    }

    #[Test]
    public function tc_apr_28_prioritas_otomatis_aturan_spesifik_diperiksa_sebelum_umum(): void
    {
        $admin = $this->makeUser('company_admin');
        $kepala = $this->makeUser('warehouse_head');
        $manajer = $this->makeUser('management');

        // Aturan umum dibuat dulu, aturan bersyarat belakangan.
        Livewire::actingAs($admin)->test(RuleForm::class)
            ->call('pilihJenis', ApprovalDocumentType::MaterialRequest->value)
            ->call('pilihApprover', 0, 'user')
            ->set('steps.0.approver_ref_id', (string) $kepala->id)
            ->call('simpan')->assertHasNoErrors();

        Livewire::actingAs($admin)->test(RuleForm::class)
            ->call('pilihJenis', ApprovalDocumentType::MaterialRequest->value)
            ->call('pilihApprover', 0, 'user')
            ->set('steps.0.approver_ref_id', (string) $manajer->id)
            ->call('pilihBerlaku', 'bila')
            ->set('conditions.warehouse_ids', [(string) $this->ckg->id])
            ->set('conditions.line_count_min', '1')
            ->call('simpan')->assertHasNoErrors();

        $umum = ApprovalRule::query()->where('document_type', 'material_request')->orderBy('id')->firstOrFail();
        $khusus = ApprovalRule::query()->where('document_type', 'material_request')->latest('id')->firstOrFail();

        $this->assertSame(100, $umum->priority);
        $this->assertSame(80, $khusus->priority, '100 − 10 × 2 jenis kondisi.');

        // REQ di CKG memakai aturan khusus walau dibuat belakangan; di BKS aturan umum.
        $this->assertSame([(int) $manajer->id], $this->approverTerbuka($this->ajukanReq($this->pemohon())));
        $this->assertSame([(int) $kepala->id], $this->approverTerbuka($this->ajukanReq($this->pemohon(), [['qty' => 5, 'gudang' => $this->bks]])));

        $this->assertSame($khusus->id, app(ApprovalEngine::class)->matchRule(
            ApprovalDocumentType::MaterialRequest,
            app(SimulateApproval::class)->contextForPerson(ApprovalDocumentType::MaterialRequest, (int) $admin->id, (int) $this->ckg->id),
        )['rule']->id);
    }

    #[Test]
    public function tc_apr_29_kotak_tag_menyimpan_pilihan_ganda(): void
    {
        $admin = $this->makeUser('company_admin');

        $komponen = Livewire::actingAs($admin)->test(RuleForm::class)
            ->call('keMode', 'lanjutan')
            ->assertSeeHtml('class="nx-pilih-tag"')
            ->assertSeeHtml('id="kondisi-gudang"')
            ->assertSee('CKG — Gudang Utama Cakung')
            ->assertDontSeeHtml('multiple size=');

        $komponen->set('form.name', 'Dua gudang')
            ->set('conditions.warehouse_ids', [(string) $this->ckg->id, (string) $this->bks->id])
            ->set('steps.0.approver_type', ApproverType::WarehouseHead->value)
            ->call('simpan')->assertHasNoErrors();

        $rule = ApprovalRule::query()->where('name', 'Dua gudang')->firstOrFail();
        $this->assertSame([(int) $this->ckg->id, (int) $this->bks->id], $rule->conditions['warehouse_ids']);

        // Menghapus satu tag lalu menyimpan ulang.
        Livewire::actingAs($admin)->test(RuleForm::class, ['rule' => $rule])
            ->set('conditions.warehouse_ids', [(string) $this->bks->id])
            ->call('simpan')->assertHasNoErrors();

        $this->assertSame([(int) $this->bks->id], $rule->refresh()->conditions['warehouse_ids']);

        // Layar lain yang dulu memakai <select multiple> kini memakai kotak tag.
        Livewire::actingAs($admin)->test(DelegationManager::class)->call('buat')->assertSeeHtml('class="nx-pilih-tag"');
    }
}

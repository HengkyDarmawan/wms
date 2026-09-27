<?php

declare(strict_types=1);

namespace Tests\Feature\Approval;

use App\Domain\Access\Models\OrgUnit;
use App\Domain\Access\Models\Position;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApproverType;
use App\Domain\Approval\Enums\DecisionMode;
use App\Domain\Approval\Livewire\RuleForm;
use App\Domain\Approval\Models\ApprovalStep;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalScenario;
use Tests\TenantTestCase;

/**
 * TC-APR-23 — approver Role/Jabatan hanya dari divisi pemohon (atau divisi
 * induknya) bila lapis dicentang *Hanya dari divisi pemohon* (A-269).
 */
class SameDivisionApproverTest extends TenantTestCase
{
    use ApprovalScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanSkenario();
    }

    #[Test]
    public function tc_apr_23_approver_hanya_dari_divisi_pemohon(): void
    {
        $dir = OrgUnit::create(['code' => 'DIRU', 'name' => 'Direksi Uji']);
        $divA = OrgUnit::create(['code' => 'DIVA', 'name' => 'Divisi A', 'parent_id' => $dir->id]);
        $divB = OrgUnit::create(['code' => 'DIVB', 'name' => 'Divisi B', 'parent_id' => $dir->id]);

        $manajerA = $this->makeUser('management', attributes: ['org_unit_id' => $divA->id]);
        $manajerB = $this->makeUser('management', attributes: ['org_unit_id' => $divB->id]);
        $direktur = $this->makeUser('management', attributes: ['org_unit_id' => $dir->id]);

        // Bawaan: lapis Role dibatasi divisi pemohon.
        $aturan = $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisRole('management')]);
        $this->assertTrue((bool) $aturan->steps()->sole()->same_org_unit);

        $pemohonA = $this->pemohon(['org_unit_id' => $divA->id]);
        $this->assertSame([(int) $manajerA->id, (int) $direktur->id], $this->approverTerbuka($this->ajukanReq($pemohonA)), 'Manajer divisi B tidak ikut; Direksi (induk) ikut.');

        $pemohonB = $this->pemohon(['org_unit_id' => $divB->id]);
        $this->assertSame([(int) $manajerB->id, (int) $direktur->id], $this->approverTerbuka($this->ajukanReq($pemohonB)));

        // Pemohon tanpa divisi: batas tidak diterapkan (dicatat di rencana).
        $tanpa = $this->ajukanReq($this->pemohon());
        $this->assertSame([(int) $manajerA->id, (int) $manajerB->id, (int) $direktur->id], $this->approverTerbuka($tanpa));
        $this->assertStringContainsString('Pemohon tanpa divisi', json_encode($this->snapshotReq($tanpa)->steps, JSON_UNESCAPED_UNICODE));

        // Centang dimatikan: lintas divisi.
        $step = $aturan->steps()->sole();
        $step->forceFill(['same_org_unit' => false])->save();
        $this->assertSame([(int) $manajerA->id, (int) $manajerB->id, (int) $direktur->id], $this->approverTerbuka($this->ajukanReq($pemohonA)));

        // Jabatan: hanya pemegang jabatan di divisi pemohon atau induknya.
        $step->forceFill(['same_org_unit' => true])->save();
        $jabatan = Position::create(['org_unit_id' => $divA->id, 'code' => 'MGR-UJI', 'name' => 'Manajer Uji', 'level' => 2]);
        $manajerA->forceFill(['position_id' => $jabatan->id])->save();
        $lain = $this->makeUser('management', attributes: ['org_unit_id' => $divB->id, 'position_id' => $jabatan->id]);
        $this->aturan(ApprovalDocumentType::MaterialRequest, [[
            'approver_type' => ApproverType::Position->value, 'approver_ref_id' => $jabatan->id,
            'decision_mode' => DecisionMode::Any->value, 'timeout_hours' => 24,
        ]], [], 5, 'Jabatan');
        $this->assertSame([(int) $manajerA->id], $this->approverTerbuka($this->ajukanReq($pemohonA)), 'Pemegang jabatan di divisi B ('.$lain->id.') tidak ikut.');

        // Jenis lain (user tertentu) tidak punya batas divisi.
        $user = $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($manajerB)], [], 1, 'User');
        $this->assertFalse((bool) $user->steps()->sole()->same_org_unit);
        $this->assertSame([(int) $manajerB->id], $this->approverTerbuka($this->ajukanReq($pemohonA)));
    }

    #[Test]
    public function tc_apr_23b_form_aturan_menampilkan_centang_divisi(): void
    {
        Livewire::actingAs($this->makeUser('company_admin'))
            ->test(RuleForm::class)
            ->set('steps.0.approver_type', ApproverType::Role->value)
            ->assertSee(__('Hanya dari divisi pemohon'))
            ->assertSet('steps.0.same_org_unit', true)
            ->set('steps.0.approver_type', ApproverType::WarehouseHead->value)
            ->assertDontSee(__('Hanya dari divisi pemohon'));

        $this->assertSame(0, ApprovalStep::query()->count());
    }
}

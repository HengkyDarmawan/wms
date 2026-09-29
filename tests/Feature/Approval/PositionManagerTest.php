<?php

declare(strict_types=1);

namespace Tests\Feature\Approval;

use App\Domain\Access\Models\OrgUnit;
use App\Domain\Access\Models\Position;
use App\Domain\Access\Support\Atasan;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApproverType;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalScenario;
use Tests\TenantTestCase;

/**
 * TC-ACC-45 — atasan ditentukan jabatan (A-344, A-345, A-346): pemegang
 * jabatan atasan menjadi atasan langsung bawaan, isian manual di user
 * menimpanya, dan lapis "Atasan langsung" bisa 1 atau 2 tingkat.
 */
class PositionManagerTest extends TenantTestCase
{
    use ApprovalScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanSkenario();
    }

    #[Test]
    public function tc_acc_45_atasan_langsung_mengikuti_peta_jabatan_dan_bisa_ditimpa_manual(): void
    {
        $unit = OrgUnit::create(['code' => 'GDG', 'name' => 'Gudang']);
        $direktur = Position::create(['org_unit_id' => $unit->id, 'code' => 'DIR', 'name' => 'Direktur', 'level' => 1, 'is_active' => true]);
        $kepala = Position::create(['org_unit_id' => $unit->id, 'code' => 'KAG', 'name' => 'Kepala Gudang', 'level' => 2, 'is_active' => true, 'reports_to_position_id' => $direktur->id]);
        $staf = Position::create(['org_unit_id' => $unit->id, 'code' => 'STF', 'name' => 'Staf Gudang', 'level' => 3, 'is_active' => true, 'reports_to_position_id' => $kepala->id]);

        $budi = $this->makeUser('management', attributes: ['position_id' => $direktur->id]);
        $andi = $this->makeUser('warehouse_head', attributes: ['position_id' => $kepala->id]);
        $sari = $this->makeUser('warehouse_head', attributes: ['position_id' => $kepala->id]);
        $dedi = $this->pemohon(['position_id' => $staf->id]);

        $atasan = app(Atasan::class);

        // Dua pemegang jabatan atasan: keduanya calon.
        $this->assertSame([(int) $andi->id, (int) $sari->id], $atasan->dari((int) $dedi->id));
        $this->assertSame(Atasan::JABATAN, $atasan->sumber($dedi));
        $this->assertSame([(int) $budi->id], app(Atasan::class)->dari((int) $dedi->id, 2), 'Tingkat 2 = atasan dari atasan.');

        // Lapis Atasan langsung 1 tingkat → Andi & Sari.
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapis(ApproverType::DirectManager)]);
        $this->assertSame([(int) $andi->id, (int) $sari->id], $this->approverTerbuka($this->ajukanReq($dedi)));

        // Lapis 2 tingkat → Budi.
        $aturan2 = $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapis(ApproverType::DirectManager, extra: ['manager_levels' => 2])], [], 5, '2 tingkat');
        $this->assertSame(2, (int) $aturan2->steps()->sole()->manager_levels);
        $req = $this->ajukanReq($dedi);
        $this->assertSame([(int) $budi->id], $this->approverTerbuka($req));
        $this->assertStringContainsString('2 tingkat', (string) $this->snapshotReq($req)->steps[0]['approver_label']);

        // Isian manual di user menimpa jabatan.
        $aturan2->forceFill(['is_active' => false])->save();
        $dedi->forceFill(['manager_id' => $budi->id])->save();
        $this->assertSame(Atasan::MANUAL, app(Atasan::class)->sumber($dedi->refresh()));
        $this->assertSame([(int) $budi->id], $this->approverTerbuka($this->ajukanReq($dedi)));
    }

    #[Test]
    public function tc_acc_45b_pengajunya_sendiri_approver_dialihkan_ke_atasan_jabatannya(): void
    {
        $unit = OrgUnit::create(['code' => 'GDG', 'name' => 'Gudang']);
        $direktur = Position::create(['org_unit_id' => $unit->id, 'code' => 'DIR', 'name' => 'Direktur', 'level' => 1, 'is_active' => true]);
        $kepala = Position::create(['org_unit_id' => $unit->id, 'code' => 'KAG', 'name' => 'Kepala Gudang', 'level' => 2, 'is_active' => true, 'reports_to_position_id' => $direktur->id]);

        $budi = $this->makeUser('management', attributes: ['position_id' => $direktur->id]);
        $andi = $this->makeUser('warehouse_head', attributes: ['position_id' => $kepala->id]);

        // BR-APR-03: Kepala gudang mengajukan REQ yang lapisnya menunjuk dirinya
        // sendiri → dialihkan ke atasan efektifnya (dari jabatan, tanpa isian manual).
        $this->aturan(ApprovalDocumentType::MaterialRequest, [$this->lapisUser($andi)]);

        $this->assertSame([(int) $budi->id], $this->approverTerbuka($this->ajukanReq($andi)));
    }
}

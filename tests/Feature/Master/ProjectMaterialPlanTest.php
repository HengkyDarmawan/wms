<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\User;
use App\Domain\Approval\Support\ConditionMatcher;
use App\Domain\Master\Actions\ImportProjectMaterialPlan;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Livewire\ProjectMaterialPlanTab;
use App\Domain\Master\Models\ProjectMaterialPlan;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Request\Support\RequestApprovalHandler;
use App\Domain\Shared\Reports\ReportRegistry;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Transfer\Concerns\TransferFixtures;
use Tests\TenantTestCase;

/**
 * TC-BOQ-01 s.d. TC-BOQ-08 — rencana material proyek (BR-PRJ-09, A-428,
 * A-429, 34-rencana-material).
 */
class ProjectMaterialPlanTest extends TenantTestCase
{
    use TransferFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanTransfer();
    }

    /** @param  array<int, array<int, mixed>>  $baris */
    private function berkas(array $baris, string $nama = 'boq.xlsx'): UploadedFile
    {
        $buku = new Spreadsheet;
        $buku->getActiveSheet()->fromArray(array_merge([array_values(ImportProjectMaterialPlan::COLUMNS)], $baris));
        $path = tempnam(sys_get_temp_dir(), 'boq').'.xlsx';
        (new Xlsx($buku))->save($path);

        return new UploadedFile($path, $nama, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function unggah(array $baris, ?User $oleh = null): int
    {
        return app(ImportProjectMaterialPlan::class)->handle($this->proyek, $this->berkas($baris), $oleh ?? $this->makeUser('company_admin'));
    }

    private function req(float $qty, bool $ajukan = true): MaterialRequest
    {
        $pemohon = $this->makeUser('internal_requester');
        $req = app(SaveRequest::class)->handle(null, ['project_id' => $this->proyek->id, 'required_date' => now()->addDays(3)->toDateString()],
            [['item_id' => $this->baut->id, 'qty_base' => $qty]], $pemohon);

        return $ajukan ? app(SubmitRequest::class)->handle($req->refresh(), $pemohon) : $req->refresh();
    }

    #[Test]
    public function tc_boq_01_02_unggah_versi_dan_versi_lama_tetap(): void
    {
        $this->assertSame(2, $this->unggah([[$this->baut->code, 100], [$this->semen->code, '12,5']]));

        $v1 = ProjectMaterialPlan::query()->where('project_id', $this->proyek->id)->get();
        $this->assertCount(2, $v1);
        $this->assertTrue($v1->every(fn ($p) => $p->version === 1 && $p->is_current));
        $this->assertSame(12.5, (float) $v1->firstWhere('item_id', $this->semen->id)->planned_qty_base, 'Koma desimal diterima.');

        $this->unggah([[$this->baut->code, 150]]);

        $this->assertSame(3, ProjectMaterialPlan::query()->where('project_id', $this->proyek->id)->count(), 'Versi lama tidak dihapus (P-03).');
        $berlaku = ProjectMaterialPlan::query()->current()->where('project_id', $this->proyek->id)->get();
        $this->assertCount(1, $berlaku);
        $this->assertSame(2, $berlaku->first()->version);
        $this->assertSame(150.0, (float) $berlaku->first()->planned_qty_base);
    }

    #[Test]
    public function tc_boq_03_baris_salah_semua_batal(): void
    {
        $this->unggah([[$this->baut->code, 100]]);

        try {
            $this->unggah([[$this->baut->code, 10], ['TIDAK-ADA', 5], [$this->semen->code, 0], [$this->baut->code, 3]]);
            $this->fail('Seharusnya ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertSame('BR-GEN-11', $e->rule);
            $baris = $e->fieldErrors['rows'];
            $this->assertStringContainsString('"TIDAK-ADA" tidak dikenal', $baris);
            $this->assertStringContainsString('lebih dari 0', $baris);
            $this->assertStringContainsString('sudah ada di baris', $baris);
        }

        $berlaku = ProjectMaterialPlan::query()->current()->where('project_id', $this->proyek->id)->sole();
        $this->assertSame(1, $berlaku->version, 'Versi lama tetap berlaku bila unggahan gagal.');
    }

    #[Test]
    public function tc_boq_04_tanpa_izin_atau_di_luar_cakupan(): void
    {
        foreach ([$this->makeUser('warehouse_staff'), $this->makeUser('internal_requester', ScopeType::Project, (int) $this->makeProject()->id)] as $u) {
            try {
                $this->unggah([[$this->baut->code, 1]], $u);
                $this->fail('Seharusnya ditolak.');
            } catch (MasterRuleException $e) {
                $this->assertSame('BR-GEN-09', $e->rule);
            }
        }

        $this->assertSame(1, $this->unggah([[$this->baut->code, 1]], $this->makeUser('internal_requester', ScopeType::Project, (int) $this->proyek->id)));
    }

    #[Test]
    public function tc_boq_05_06_melebihi_rencana_untuk_approval(): void
    {
        $handler = app(RequestApprovalHandler::class);
        $matcher = app(ConditionMatcher::class);

        // Tanpa rencana: tidak pernah melebihi (A-429).
        $this->assertFalse($handler->context($this->req(1000, false))->exceedsPlan);

        $this->unggah([[$this->baut->code, 100]]);
        $this->req(60);

        $lebih = $handler->context($this->req(50, false));
        $this->assertTrue($lebih->exceedsPlan, '60 + 50 > 100.');
        $this->assertTrue($matcher->evaluate(['match' => 'all', 'exceeds_plan' => true], $lebih)['matched']);

        $pas = $handler->context($this->req(30, false));
        $this->assertFalse($pas->exceedsPlan, 'REQ draf lain tidak dihitung; 60 + 30 ≤ 100.');
        $this->assertFalse($matcher->evaluate(['match' => 'all', 'exceeds_plan' => true], $pas)['matched']);
        $this->assertSame(['match' => 'all', 'exceeds_plan' => true], ConditionMatcher::normalize(['exceeds_plan' => '1']));
    }

    #[Test]
    public function tc_boq_07_laporan_memuat_rencana_tanpa_pergerakan(): void
    {
        $this->unggah([[$this->kabel->code, 40]]);

        $baris = app(ReportRegistry::class)->find('material-per-proyek')
            ->rows(['project_id' => (string) $this->proyek->id])->firstWhere('kode_item', $this->kabel->code);

        $this->assertNotNull($baris);
        $this->assertSame(40.0, (float) $baris['rencana']);
        $this->assertSame(0.0, (float) $baris['diminta']);
    }

    #[Test]
    public function tc_boq_08_tab_rencana_material(): void
    {
        $this->unggah([[$this->baut->code, 100], [$this->kabel->code, 5]]);
        $this->req(120);

        Livewire::actingAs($this->makeUser('company_admin'))->test(ProjectMaterialPlanTab::class, ['project' => $this->proyek])
            ->assertSee('Versi 1')
            ->assertSee('Melebihi')
            ->assertSee('−20')
            ->assertSee('Simpan sebagai versi baru')
            ->call('unduhTemplat')->assertFileDownloaded('rencana-material-'.$this->proyek->code.'.xlsx');

        Livewire::actingAs($this->makeUser('warehouse_staff'))->test(ProjectMaterialPlanTab::class, ['project' => $this->proyek])
            ->assertDontSee('Simpan sebagai versi baru');
    }

    #[Test]
    public function tc_boq_09_klien_unggah_lewat_portal(): void
    {
        $klien = $this->makeUser('client_user', ScopeType::Project, (int) $this->proyek->id, ['client_id' => $this->proyek->client_id]);
        $lain = $this->makeProject();
        $klienLain = $this->makeUser('client_user', ScopeType::Project, (int) $lain->id, ['client_id' => $lain->client_id]);

        $this->actingAs($klien)->get($this->tenantUrl('portal/projects/'.$this->proyek->id.'/plan'))->assertOk()->assertSee('Rencana material');
        $this->actingAs($klienLain)->get($this->tenantUrl('portal/projects/'.$this->proyek->id.'/plan'))->assertForbidden();

        $this->assertSame(1, $this->unggah([[$this->baut->code, 80]], $klien));
        try {
            $this->unggah([[$this->baut->code, 1]], $klienLain);
            $this->fail('Klien proyek lain ditolak.');
        } catch (MasterRuleException $e) {
            $this->assertSame('BR-GEN-09', $e->rule);
        }
    }
}

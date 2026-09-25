<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Master\Enums\ProjectStatus;
use App\Domain\Master\Livewire\ProjectDetail;
use App\Domain\Request\Livewire\RequestForm;
use App\Domain\Return\Livewire\ReturnForm;
use App\Domain\Transfer\Livewire\TransferForm;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Issue\Concerns\IssueFixtures;
use Tests\TenantTestCase;

/**
 * TC-MST-25 — hub proyek (Blueprint §6.9, A-228): kartu ringkas & tab terisi
 * dari dokumen proyek, tombol aksi mengisi proyek di form tujuan, cakupan
 * proyek (BR-ACC-05), dan penutupan proyek dari detail (BR-PRJ-02).
 */
class ProjectDetailTest extends TenantTestCase
{
    use IssueFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPemakaian();
    }

    #[Test]
    public function tc_mst_25_hub_proyek_ringkas_tab_dan_aksi(): void
    {
        $req = $this->kirimKeSite($this->baut, 40);
        $isu = $this->konfirmasi($this->isu([['key' => $this->kunciIsu($this->binKrw1, $this->baut), 'qty_base' => 5]]));
        $admin = $this->makeUser('company_admin');

        $this->actingAs($admin)->get($this->tenantUrl('projects'))->assertOk()->assertSee(route('projects.show', $this->proyek));
        $this->actingAs($admin)->get($this->tenantUrl('projects/'.$this->proyek->id))->assertOk()
            ->assertSee($this->proyek->name)
            ->assertSee('KRW1')
            ->assertSee(route('requests.create', ['project' => $this->proyek->id]))
            ->assertSee(route('issues.create', ['project' => $this->proyek->id]))
            ->assertSee(route('transfers.create', ['from_warehouse' => $this->krw1->id]));

        Livewire::actingAs($admin)
            ->test(ProjectDetail::class, ['project' => $this->proyek])
            ->assertSet('tab', 'permintaan')
            ->assertSee($req->number)
            ->assertSee('35,00')
            ->call('pilihTab', 'pengiriman')->assertSee('SJ/')
            ->call('pilihTab', 'stok')->assertSee('Di Gudang Site')->assertSee('BAUT-M12')->assertSee('35,0000')
            ->call('pilihTab', 'pemakaian')->assertSee($isu->number)
            ->call('pilihTab', 'konversi')->assertSee(__('Belum ada konversi di proyek ini.'))
            ->call('pilihTab', 'retur')->assertSee(__('Belum ada retur.'))
            ->call('pilihTab', 'aset')->assertSee(__('Belum ada aset dipinjamkan ke proyek ini.'))
            ->call('pilihTab', 'approval')->assertSee(__('Tidak ada dokumen proyek ini yang menunggu approval.'))
            ->call('pilihTab', 'riwayat')->assertOk()
            ->call('pilihTab', 'ngawur')->assertSet('tab', 'permintaan');

        // Tombol aksi mengisi proyek/gudang asal di form tujuan.
        $pemohon = $this->makeUser('internal_requester', ScopeType::Project, $this->proyek->id);
        Livewire::withQueryParams(['project' => $this->proyek->id])->actingAs($pemohon)->test(RequestForm::class)
            ->assertSet('form.project_id', (string) $this->proyek->id);
        Livewire::withQueryParams(['project' => 999999])->actingAs($pemohon)->test(RequestForm::class)
            ->assertSet('form.project_id', '');
        Livewire::withQueryParams(['project' => $this->proyek->id])->actingAs($this->stafSite())->test(ReturnForm::class)
            ->assertSet('form.project_id', (string) $this->proyek->id);
        Livewire::withQueryParams(['from_warehouse' => $this->krw1->id])->actingAs($this->stafSite())->test(TransferForm::class)
            ->assertSet('form.from_warehouse_id', (string) $this->krw1->id);

        // Cakupan (BR-ACC-05): pemohon proyek lain tidak melihat hub ini (404); klien tidak masuk area internal.
        $lain = $this->makeProject();
        $this->actingAs($this->makeUser('internal_requester', ScopeType::Project, $lain->id))->get($this->tenantUrl('projects/'.$this->proyek->id))->assertNotFound();
        $this->actingAs($this->makeUser('client_user'))->get($this->tenantUrl('projects/'.$this->proyek->id))->assertStatus(403);

        // Halaman gudang menautkan hub proyek dan tautan cepat.
        $this->actingAs($admin)->get($this->tenantUrl('warehouses/'.$this->krw1->id))->assertOk()
            ->assertSee(route('projects.show', $this->proyek->id))
            ->assertSee(route('stock.index', ['warehouseFilter' => $this->krw1->id]));
    }

    #[Test]
    public function tc_mst_25b_tutup_proyek_dari_hub_mengikuti_checklist(): void
    {
        $this->kirimKeSite($this->baut, 10);
        $admin = $this->makeUser('company_admin');

        $layar = Livewire::actingAs($admin)
            ->test(ProjectDetail::class, ['project' => $this->proyek])
            ->assertSee(__('Tutup / batalkan proyek'))
            ->call('mintaUbahStatus')
            ->assertSet('dialogStatus', true)
            ->assertSee('Gudang Site KRW1 masih berisi stok')
            ->set('targetStatus', ProjectStatus::Closed->value)
            ->set('reasonCode', 'NOT_NEEDED')
            ->call('ubahStatus')
            ->assertSet('dialogStatus', true);

        $this->assertSame(ProjectStatus::Active, $this->proyek->refresh()->status, 'Checklist belum bersih (BR-PRJ-02).');
        $this->assertStringContainsString('belum bisa ditutup', $layar->get('ruleError'));

        // Habiskan stok site lewat ISU, lalu tutup.
        $this->konfirmasi($this->isu([['key' => $this->kunciIsu($this->binKrw1, $this->baut), 'qty_base' => 10]]));

        Livewire::actingAs($admin)
            ->test(ProjectDetail::class, ['project' => $this->proyek])
            ->call('mintaUbahStatus')
            ->assertSee(__('Proyek boleh ditutup.'))
            ->set('targetStatus', ProjectStatus::Closed->value)
            ->set('reasonCode', 'NOT_NEEDED')
            ->call('ubahStatus')
            ->assertSet('dialogStatus', false)
            ->assertSet('ruleError', '');

        $this->assertSame(ProjectStatus::Closed, $this->proyek->refresh()->status);
        $this->actingAs($admin)->get($this->tenantUrl('projects/'.$this->proyek->id))->assertOk()
            ->assertDontSee(route('requests.create', ['project' => $this->proyek->id]));
    }
}

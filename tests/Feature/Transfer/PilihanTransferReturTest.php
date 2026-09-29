<?php

declare(strict_types=1);

namespace Tests\Feature\Transfer;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\ProjectStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Uom;
use App\Domain\Return\Livewire\ReturnForm;
use App\Domain\Transfer\Livewire\ProjectMove;
use App\Domain\Transfer\Livewire\TransferForm;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Transfer\Concerns\TransferFixtures;
use Tests\TenantTestCase;

/**
 * TC-TRF-22, TC-TRF-22b, TC-RET-26 — `<x-pilih>` di Transfer & Retur dari
 * proyek (menu Barang masuk, A-391): item TRF, proyek tujuan pindahan, dan
 * proyek RET dicari ke server dengan daftar & cakupan yang sama seperti dulu;
 * id di luar daftar ditolak di isiannya; asal TRF tetap tidak dibatasi (A-106).
 */
class PilihanTransferReturTest extends TenantTestCase
{
    use TransferFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanTransfer();
    }

    /** @return list<mixed> */
    private function nilai($komponen): array
    {
        return array_map('intval', array_column($komponen->effects['returns'][0] ?? [], 'value'));
    }

    #[Test]
    public function tc_trf_22_item_transfer_dicari_ke_server(): void
    {
        $usang = $this->buatItem('BAUT-M10', TrackingMode::None, Uom::query()->where('code', 'PCS')->value('id'), ['status' => ItemStatus::Inactive]);
        $staf = $this->makeUser('warehouse_staff');

        $form = Livewire::actingAs($staf)->test(TransferForm::class)
            ->assertSeeHtml('data-server="1"')->assertSeeHtml('id="trf-asal"')
            ->call('cariPilihan', 'rows.0.item_id', 'baut');
        $this->assertSame([(int) $this->baut->id], $this->nilai($form));

        $form->call('cariPilihan', 'form.from_warehouse_id', 'bks');
        $this->assertSame([], $this->nilai($form), 'Gudang dimuat sekaligus, tidak dicari.');

        $form->set('form.from_warehouse_id', (string) $this->gudang->id)->set('form.to_warehouse_id', (string) $this->bks->id)
            ->set('rows.0.item_id', (string) $usang->id)->set('rows.0.qty_base', '5')
            ->call('simpan')->assertHasErrors('rows.0.item_id')
            ->set('rows.0.item_id', (string) $this->baut->id)->call('simpan')->assertHasNoErrors()->assertRedirect();

        $this->actingAs($this->makeUser('internal_auditor'));
        $form->call('cariPilihan', 'rows.0.item_id', 'baut');
        $this->assertSame([], $this->nilai($form));
    }

    #[Test]
    public function tc_trf_22b_proyek_tujuan_pindahan_dicari_ke_server(): void
    {
        $tujuan = $this->makeProject();
        $tutup = $this->makeProject();
        $tutup->forceFill(['status' => ProjectStatus::Closed])->save();

        $move = Livewire::actingAs($this->makeUser('warehouse_head'))->test(ProjectMove::class, ['project' => $this->proyek])
            ->call('cariPilihan', 'form.to_project_id', 'PR');
        $hasil = $this->nilai($move);
        $this->assertContains((int) $tujuan->id, $hasil);
        $this->assertNotContains((int) $this->proyek->id, $hasil, 'Proyek asal tidak ditawarkan.');
        $this->assertNotContains((int) $tutup->id, $hasil, 'Proyek ditutup tidak ditawarkan.');

        $move->set('form.to_project_id', (string) $tutup->id)->call('simpan')->assertHasErrors('form.to_project_id');
    }

    #[Test]
    public function tc_ret_26_proyek_retur_dicari_ke_server_sesuai_cakupan(): void
    {
        $lain = $this->makeProject();
        $pemohon = $this->makeUser('internal_requester', ScopeType::Project, $this->proyek->id);

        $form = Livewire::actingAs($pemohon)->test(ReturnForm::class)
            ->assertSet('form.project_id', (string) $this->proyek->id)
            ->assertSeeHtml('id="ret-proyek"')
            ->call('cariPilihan', 'form.project_id', 'PR');
        $this->assertSame([(int) $this->proyek->id], $this->nilai($form));

        // Proyek di luar cakupan dikirim dari browser → ditolak di isian Proyek.
        $form->set('form.project_id', (string) $lain->id)->set('form.to_warehouse_id', (string) $this->gudang->id)
            ->call('simpan')->assertHasErrors('form.project_id');
    }
}

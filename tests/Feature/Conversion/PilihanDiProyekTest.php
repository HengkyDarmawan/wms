<?php

declare(strict_types=1);

namespace Tests\Feature\Conversion;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Asset\Livewire\AssetList;
use App\Domain\Asset\Livewire\HandoverList;
use App\Domain\Conversion\Livewire\ConversionForm;
use App\Domain\Conversion\Livewire\ConversionList;
use App\Domain\Issue\Livewire\IssueForm;
use App\Domain\Issue\Livewire\IssueList;
use App\Domain\Waste\Livewire\WasteDisposalForm;
use App\Domain\Waste\Livewire\WasteDisposalList;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Conversion\Concerns\ConversionFixtures;
use Tests\TenantTestCase;

/**
 * TC-ISU-22, TC-CNV-19, TC-WST-07, TC-AST-17 — `<x-pilih>` di menu Di proyek
 * (A-395): proyek ISU/CNV/WST, item hasil & bin tujuan CNV, bin tujuan WST,
 * dan saringan proyek daftar ISU/CNV/WST/Aset/Serah terima dicari ke server
 * dengan daftar & cakupan yang sama seperti dulu; id di luar daftar ditolak
 * di isiannya; pengguna tanpa izin layar tidak mendapat hasil.
 */
class PilihanDiProyekTest extends TenantTestCase
{
    use ConversionFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanKonversi();
    }

    /** @return list<int> */
    private function nilai($komponen): array
    {
        return array_map('intval', array_column($komponen->effects['returns'][0] ?? [], 'value'));
    }

    #[Test]
    public function tc_isu_22_proyek_isu_dan_saringan_daftar_dicari_ke_server(): void
    {
        $lain = $this->makeProject();
        $pemohon = $this->makeUser('internal_requester', ScopeType::Project, $this->proyek->id);

        $form = Livewire::actingAs($pemohon)->test(IssueForm::class)->assertSeeHtml('id="isu-proyek"')
            ->call('cariPilihan', 'form.project_id', 'PR');
        $this->assertSame([(int) $this->proyek->id], $this->nilai($form), 'Proyek hanya dalam cakupan.');

        $form->set('form.project_id', (string) $lain->id)->set('form.warehouse_id', (string) $this->krw1->id)
            ->call('simpan')->assertHasErrors('form.project_id');

        $daftar = Livewire::actingAs($pemohon)->test(IssueList::class)->call('cariPilihan', 'projectFilter', 'PR');
        $this->assertSame([(int) $this->proyek->id], $this->nilai($daftar));

        $this->actingAs($this->makeUser('driver'));
        $form->call('cariPilihan', 'form.project_id', 'PR');
        $this->assertSame([], $this->nilai($form));
    }

    #[Test]
    public function tc_cnv_19_item_hasil_dan_bin_tujuan_cnv_dicari_ke_server(): void
    {
        $staf = $this->staf();
        $form = Livewire::actingAs($staf)->test(ConversionForm::class)->assertSeeHtml('id="cnv-proyek"')
            ->set('form.project_id', (string) $this->proyek->id)
            ->set('form.warehouse_id', (string) $this->gudang->id);

        // Tanpa batang: item habis pakai aktif, bukan serial/aset.
        $form->call('cariPilihan', 'hasil.0.item_id', 'BAUT');
        $this->assertSame([(int) $this->baut->id], $this->nilai($form));
        $form->call('cariPilihan', 'hasil.0.item_id', 'GENSET');
        $this->assertSame([], $this->nilai($form), 'Aset tidak menjadi hasil konversi.');

        // Batang dipilih: hanya item bersatuan dasar sama dengan batang (neraca ukuran).
        $form->set('batang', str_replace(':', '_', $this->kunciBatang()))->call('cariPilihan', 'potong.0.item_id', 'PIPA');
        $this->assertSame([(int) $this->pipa->id], $this->nilai($form));
        $form->call('cariPilihan', 'potong.0.item_id', 'BAUT');
        $this->assertSame([], $this->nilai($form));

        // Bin hasil: bin penyimpanan aktif gudang terpilih saja.
        $form->call('cariPilihan', 'form.bin_id', 'R01');
        $this->assertContains((int) $this->binA->id, $this->nilai($form));
        $this->assertNotContains((int) $this->binBks->id, $this->nilai($form));
        $form->set('potong.0.length', '2')->set('form.bin_id', (string) $this->binBks->id)
            ->call('simpan')->assertHasErrors('form.bin_id');

        $daftar = Livewire::actingAs($staf)->test(ConversionList::class)->call('cariPilihan', 'projectFilter', 'PR');
        $this->assertContains((int) $this->proyek->id, $this->nilai($daftar));

        $this->actingAs($this->makeUser('driver'));
        $form->call('cariPilihan', 'hasil.0.item_id', 'BAUT');
        $this->assertSame([], $this->nilai($form));
    }

    #[Test]
    public function tc_wst_07_proyek_dan_bin_tujuan_wst_ikut_gudang(): void
    {
        $lain = $this->makeProject();
        $staf = $this->staf();

        $form = Livewire::actingAs($staf)->test(WasteDisposalForm::class)
            ->set('form.warehouse_id', (string) $this->gudang->id)->set('form.project_id', (string) $lain->id)
            ->call('cariPilihan', 'form.project_id', 'PR');
        $this->assertContains((int) $lain->id, $this->nilai($form));

        // Gudang Site: hanya proyek pemiliknya; proyek lain yang terpilih dikosongkan.
        $form->set('form.warehouse_id', (string) $this->krw1->id)->assertSet('form.project_id', '')
            ->call('cariPilihan', 'form.project_id', 'PR');
        $this->assertSame([(int) $this->proyek->id], $this->nilai($form));

        $form->set('form.disposition', 'reused')->call('cariPilihan', 'form.target_bin_id', 'KRW');
        $this->assertSame([(int) $this->binKrw1->id], $this->nilai($form));
        $form->set('form.project_id', (string) $lain->id)->set('form.target_bin_id', (string) $this->binA->id)
            ->call('simpan')->assertHasErrors(['form.project_id', 'form.target_bin_id']);

        $daftar = Livewire::actingAs($staf)->test(WasteDisposalList::class)->call('cariPilihan', 'projectFilter', 'PR');
        $this->assertContains((int) $this->proyek->id, $this->nilai($daftar));
    }

    #[Test]
    public function tc_ast_17_saringan_proyek_aset_dan_serah_terima(): void
    {
        $lain = $this->makeProject();
        $kepala = $this->makeUser('warehouse_head', ScopeType::Project, $this->proyek->id);

        foreach ([AssetList::class, HandoverList::class] as $layar) {
            $komponen = Livewire::actingAs($kepala)->test($layar)->call('cariPilihan', 'projectFilter', 'PR');
            $this->assertSame([(int) $this->proyek->id], $this->nilai($komponen), $layar.': hanya proyek dalam cakupan.');
            $this->assertNotContains((int) $lain->id, $this->nilai($komponen));
        }
    }
}

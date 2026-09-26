<?php

declare(strict_types=1);

namespace Tests\Feature\Conversion;

use App\Domain\Conversion\Actions\CompleteConversion;
use App\Domain\Conversion\Enums\ConversionOutputKind;
use App\Domain\Conversion\Enums\ConversionStatus;
use App\Domain\Conversion\Livewire\ConversionForm;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Conversion\Support\ConversionPlanner;
use App\Domain\Master\Models\Piece;
use App\Domain\Stock\Models\StockBalance;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Conversion\Concerns\ConversionFixtures;
use Tests\TenantTestCase;

/**
 * TC-CNV-15, TC-CNV-16 — satu CNV potong untuk beberapa batang, pola sama
 * (salin, dipilih FIFO) atau berbeda per batang (A-253; memperluas A-229).
 */
class ConversionMultiRodTest extends TenantTestCase
{
    use ConversionFixtures;

    private Piece $batang2;

    private Piece $batang3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanKonversi();
        $this->batang2 = $this->potongan($this->binA, 6.0);
        $this->batang3 = $this->potongan($this->binA, 6.0);
    }

    private function form(): Testable
    {
        return Livewire::actingAs($this->staf())->test(ConversionForm::class)
            ->set('form.project_id', (string) $this->proyek->id)
            ->set('form.warehouse_id', (string) $this->gudang->id);
    }

    #[Test]
    public function tc_cnv_15_tiga_batang_satu_dokumen_sisa_per_batang(): void
    {
        $form = $this->form()
            ->set('batang', str_replace(':', '_', $this->kunciBatang()))
            ->set('potong.0.length', '1.5')->set('potong.0.count', '3')
            // Pola beda di batang 2: 2 × 2 m + 1 × 1,5 m (sisa 0,485 < 0,5 → waste).
            ->call('tambahBatang')
            ->set('tambahan.0.key', str_replace(':', '_', $this->kunciBatang($this->batang2)))
            ->set('tambahan.0.potong.0.length', '2')->set('tambahan.0.potong.0.count', '2');

        $form->call('tambahPotongBatang', $form->get('tambahan')[0]['id'])
            ->set('tambahan.0.potong.1.length', '1.5')->set('tambahan.0.potong.1.count', '1');

        $form->assertSee(__('Batang :n', ['n' => 2]))
            ->assertSee('2 batang (12 M) → 4 × 1,5 M + 2 × 2 M + 1,485 M offcut + 0,485 M waste + kerf 0,03 M')
            ->assertSee(__('Neraca seimbang; siap disimpan.'))
            ->call('simpan')->assertSet('ruleError', '')->assertRedirect();

        $cnv = Conversion::query()->latest('id')->firstOrFail();
        $input = $cnv->inputs()->orderBy('id')->get();
        $this->assertCount(2, $input, 'Satu CNV, dua batang.');

        $b1 = $cnv->outputs()->where('parent_input_id', $input[0]->id)->get();
        $b2 = $cnv->outputs()->where('parent_input_id', $input[1]->id)->get();
        $this->assertSame(3, $b1->where('output_kind', ConversionOutputKind::Output)->count());
        $this->assertSame(0.015, (float) $b1->where('output_kind', ConversionOutputKind::Kerf)->sole()->qty_base, 'Kerf per batang: 3 × 0,005.');
        $this->assertSame(1.485, (float) $b1->where('output_kind', ConversionOutputKind::Offcut)->sole()->qty_base, '6 − 4,5 − 0,015 = sisa batang 1 → offcut.');
        $this->assertSame(3, $b2->where('output_kind', ConversionOutputKind::Output)->count());
        $this->assertSame(0.485, (float) $b2->where('output_kind', ConversionOutputKind::Waste)->sole()->qty_base, 'BR-CNV-03: sisa batang 2 < 0,5 m → waste.');

        // Ubah draf: batang & pola tiap batang terisi kembali dari output ber-induk.
        Livewire::actingAs($this->staf())->test(ConversionForm::class, ['conversion' => $cnv])
            ->assertCount('tambahan', 1)
            ->assertSet('tambahan.0.key', str_replace(':', '_', $this->kunciBatang($this->batang2)))
            ->assertCount('tambahan.0.potong', 2)
            ->assertSet('potong.0.count', '3');

        app(CompleteConversion::class)->handle($cnv, $this->staf());
        $this->assertSame(ConversionStatus::Completed, $cnv->refresh()->status);
        $this->assertSame(0.0, (float) StockBalance::query()->whereIn('piece_id', [$this->batang->id, $this->batang2->id])->sum('qty_base'), 'Kedua batang habis dipotong.');

        $kalimat = ConversionPlanner::kalimatDokumen($cnv->inputs()->with('item.baseUom')->get(), $cnv->outputs()->with('item.baseUom')->orderBy('id')->get());
        $this->assertStringStartsWith('2 × 6 M', $kalimat, 'Batang sama panjang dikelompokkan.');
    }

    #[Test]
    public function tc_cnv_16_salin_pola_ke_batang_fifo_dan_guard(): void
    {
        $form = $this->form()
            ->call('salinPola')->assertHasErrors('jumlahSalin')
            ->set('batang', str_replace(':', '_', $this->kunciBatang()))
            ->set('potong.0.length', '2')->set('potong.0.count', '3')
            ->set('jumlahSalin', '5')->call('salinPola')->assertHasErrors('jumlahSalin'); // hanya 2 batang lain

        // Dua batang lain dipilih FIFO (potongan tertua dulu) dengan pola yang sama.
        $form->assertCount('tambahan', 2)
            ->assertSet('tambahan.0.key', str_replace(':', '_', $this->kunciBatang($this->batang2)))
            ->assertSet('tambahan.1.key', str_replace(':', '_', $this->kunciBatang($this->batang3)))
            ->assertSet('tambahan.1.potong.0.length', '2')
            ->assertSee('3 batang (18 M) → 9 × 2 M');

        // Pola batang 3 diubah menjadi berlebih → galat khusus batang itu.
        $form->set('tambahan.1.potong.0.count', '4')
            ->call('simpan')
            ->assertHasErrors('rencana.potong@'.$this->kunciBatang($this->batang3));

        // Batang yang sama dua kali ditolak.
        $form->set('tambahan.1.potong.0.count', '3')
            ->set('tambahan.1.key', str_replace(':', '_', $this->kunciBatang($this->batang2)))
            ->call('simpan')
            ->assertHasErrors('rencana.batang@'.$this->kunciBatang($this->batang2));
    }
}

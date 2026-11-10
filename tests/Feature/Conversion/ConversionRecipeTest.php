<?php

declare(strict_types=1);

namespace Tests\Feature\Conversion;

use App\Domain\Conversion\Actions\SaveConversionRecipe;
use App\Domain\Conversion\Exceptions\ConversionRuleException;
use App\Domain\Conversion\Livewire\ConversionForm;
use App\Domain\Conversion\Livewire\RecipeManager;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Conversion\Models\ConversionRecipe;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Conversion\Concerns\ConversionFixtures;
use Tests\TenantTestCase;

/**
 * TC-RCP-01 s.d. TC-RCP-05 — resep konversi (BR-CNV-06, A-431): templat yang
 * mengisi form, tidak mengunci hasil nyata.
 */
class ConversionRecipeTest extends TenantTestCase
{
    use ConversionFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanKonversi();
    }

    private function resepPotong(): ConversionRecipe
    {
        return app(SaveConversionRecipe::class)->handle(null, [
            'code' => 'pipa6-4x15', 'name' => 'Pipa 6 m jadi 4 × 1,5 m', 'conversion_type' => 'cut', 'input_item_id' => $this->pipa->id,
            'potong' => [['item_id' => '', 'length' => '1,5', 'count' => '4']],
        ], $this->makeUser('warehouse_head'));
    }

    #[Test]
    public function tc_rcp_01_simpan_resep_potong_dan_ringkasan(): void
    {
        $r = $this->resepPotong();

        $this->assertSame('PIPA6-4X15', $r->code, 'Kode jadi huruf besar.');
        $this->assertSame('Potong: 4 × 1,5', $r->ringkasan());
        $this->assertSame((int) $this->pipa->id, (int) $r->inputItem()?->id);
        $this->assertTrue($r->is_active);
    }

    #[Test]
    public function tc_rcp_02_validasi_dan_izin(): void
    {
        $simpan = app(SaveConversionRecipe::class);

        try {
            $simpan->handle(null, ['code' => 'PIPA6-4X15', 'name' => '', 'conversion_type' => 'cut', 'potong' => [['length' => '0', 'count' => '1']]], $this->makeUser('warehouse_head'));
            $this->fail('Seharusnya ditolak.');
        } catch (ConversionRuleException $e) {
            $this->assertSame('BR-CNV-06', $e->rule);
            $this->assertArrayHasKey('name', $e->fieldErrors);
            $this->assertArrayHasKey('potong.0', $e->fieldErrors);
        }

        $this->resepPotong();
        try {
            $simpan->handle(null, ['code' => 'pipa6-4x15', 'name' => 'Lain', 'conversion_type' => 'repack', 'hasil' => [['kind' => 'waste', 'item_id' => $this->pipa->id, 'qty' => 1]]], $this->makeUser('warehouse_head'));
            $this->fail('Seharusnya ditolak.');
        } catch (ConversionRuleException $e) {
            $this->assertArrayHasKey('code', $e->fieldErrors, 'Kode ganda.');
            $this->assertArrayHasKey('hasil', $e->fieldErrors, 'Minimal satu hasil bukan waste.');
        }

        try {
            $simpan->handle(null, ['code' => 'X1', 'name' => 'X', 'conversion_type' => 'cut', 'potong' => [['length' => 1, 'count' => 1]]], $this->staf());
            $this->fail('Staf gudang tanpa izin.');
        } catch (ConversionRuleException $e) {
            $this->assertSame('BR-GEN-09', $e->rule);
        }
    }

    #[Test]
    public function tc_rcp_03_terapkan_resep_di_form_lalu_simpan_mencatat_resep(): void
    {
        $r = $this->resepPotong();

        $form = Livewire::actingAs($this->staf())->test(ConversionForm::class)
            ->set('form.project_id', (string) $this->proyek->id)
            ->set('form.warehouse_id', (string) $this->gudang->id)
            ->set('batang', str_replace(':', '_', $this->kunciBatang()))
            ->set('resepId', (string) $r->id)
            ->call('pakaiResep')
            ->assertSet('resepDipakai', (string) $r->id);

        $this->assertSame('1.5', $form->get('potong')[0]['length'], 'Isian number memakai titik.');
        $this->assertSame('4', $form->get('potong')[0]['count']);
        $this->assertSame(str_replace(':', '_', $this->kunciBatang()), $form->get('batang'), 'Batang yang sudah dipilih tetap.');

        // Resep tidak mengunci: pola masih bisa diubah sebelum disimpan.
        $form->set('potong.0.count', '3')->call('simpan')->assertSet('ruleError', '')->assertRedirect();

        $cnv = Conversion::query()->latest('id')->firstOrFail();
        $this->assertSame((int) $r->id, (int) $cnv->recipe_id);
        $this->assertSame(3, $cnv->outputs()->where('output_kind', 'output')->count(), 'Hasil nyata dari isian, bukan dari resep.');
    }

    #[Test]
    public function tc_rcp_04_resep_nonaktif_tidak_bisa_diterapkan(): void
    {
        $r = $this->resepPotong();
        $r->update(['is_active' => false]);

        Livewire::actingAs($this->staf())->test(ConversionForm::class)
            ->set('resepId', (string) $r->id)->call('pakaiResep')
            ->assertHasErrors('resepId');
    }

    #[Test]
    public function tc_rcp_05_layar_resep(): void
    {
        $this->resepPotong();

        Livewire::actingAs($this->makeUser('warehouse_head'))->test(RecipeManager::class)
            ->assertSee('PIPA6-4X15')->assertSee('Potong: 4 × 1,5')
            ->call('bukaForm')
            ->set('form.code', 'SAMBUNG-1')->set('form.name', 'Rakit sambungan')->set('form.conversion_type', 'assemble')
            ->set('form.hasil.0.item_id', (string) $this->baut->id)->set('form.hasil.0.qty', '2')
            ->call('simpan')->assertSet('galat', '')->assertSet('ubah', 0);

        $this->assertTrue(ConversionRecipe::query()->where('code', 'SAMBUNG-1')->exists());

        Livewire::actingAs($this->staf())->test(RecipeManager::class)
            ->assertSee('PIPA6-4X15')->assertDontSee('Resep baru');
    }
}

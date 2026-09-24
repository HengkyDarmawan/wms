<?php

declare(strict_types=1);

namespace Tests\Feature\Template;

use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Enums\PaperSize;
use App\Domain\Template\Livewire\DocumentLayoutForm;
use App\Domain\Template\Models\DocumentLayout;
use App\Domain\Template\Models\DocumentTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\TenantTestCase;

/**
 * TC-TPL-07, TC-TPL-08 — layar layout dokumen (18-template-dokumen-label §6,
 * NFR-14, BR-GEN-05, A-122, A-125).
 */
class DocumentLayoutTest extends TenantTestCase
{
    #[Test]
    public function tc_tpl_07_simpan_layout_dan_validasi(): void
    {
        $admin = $this->makeUser('company_admin');

        Livewire::actingAs($admin)->test(DocumentLayoutForm::class)
            ->assertSet('accent', DocumentLayout::WARNA_BAWAAN)
            ->assertSet('blocks.shipment', "Dibuat oleh\nPengemudi\nPenerima")
            ->set('accent', 'merah')
            ->set('blocks.shipment', "A\nB\nC\nD\nE")
            ->set('papers.label_bin', 'a4')
            ->call('save')
            ->assertHasErrors(['accent', 'signatureBlocks.shipment', 'papers.label_bin']);

        $this->assertNull(DocumentLayout::current()->header_html, 'Isian yang gagal tidak tersimpan sebagian.');

        Livewire::actingAs($admin)->test(DocumentLayoutForm::class)
            ->set('name', 'Kop PT Uji')
            ->set('header', "Jl. Raya Cakung No. 1\nTelp 021-000")
            ->set('footer', 'Dokumen sah tanpa cap')
            ->set('accent', '#AA3300')
            ->set('blocks.shipment', "Petugas gudang\nSopir")
            ->set('blocks.pick_task', "Picker\nDiperiksa")
            ->set('papers.label_bin', PaperSize::Label50x30->value)
            ->set('papers.pick_task', PaperSize::A4Landscape->value)
            ->call('save')
            ->assertHasNoErrors();

        $layout = DocumentLayout::current();
        $this->assertSame('Kop PT Uji', $layout->name);
        $this->assertSame("Jl. Raya Cakung No. 1\nTelp 021-000", $layout->header_html);
        $this->assertSame('#aa3300', $layout->accent());
        $this->assertSame(['Petugas gudang', 'Sopir'], $layout->signatureBlocksFor(DocumentTemplateType::Shipment));
        $this->assertArrayNotHasKey('pick_task', $layout->signature_blocks ?? [], 'Blok sama dengan bawaan tidak disimpan.');
        $this->assertSame(PaperSize::Label50x30, DocumentTemplate::forType(DocumentTemplateType::LabelBin)->paper);
        $this->assertSame(PaperSize::A4Landscape, DocumentTemplate::forType(DocumentTemplateType::PickTask)->paper);
        $this->assertSame(1, DocumentLayout::query()->count(), 'F1 satu layout per company (A-123).');

        $this->assertTrue(
            Activity::query()->where('log_name', 'template')->where('description', 'Layout dokumen diubah')->exists(),
            'Perubahan layout tercatat (BR-GEN-05).',
        );
    }

    #[Test]
    public function tc_tpl_07b_logo_png_jpeg_maks_5_mb(): void
    {
        Storage::fake('local');
        $admin = $this->makeUser('company_admin');

        $this->actingAs($admin)
            ->post($this->tenantUrl('settings/document-layout/logo'), ['logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('logo');
        $this->actingAs($admin)
            ->post($this->tenantUrl('settings/document-layout/logo'), ['logo' => UploadedFile::fake()->image('besar.png')->size(6 * 1024)])
            ->assertSessionHasErrors('logo');
        $this->assertNull(DocumentLayout::current()->logo_path);

        $this->actingAs($admin)
            ->post($this->tenantUrl('settings/document-layout/logo'), ['logo' => UploadedFile::fake()->image('logo.png', 200, 60)])
            ->assertRedirect()->assertSessionHasNoErrors();

        $path = DocumentLayout::current()->logo_path;
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);
        $this->actingAs($admin)->get($this->tenantUrl('settings/document-layout/logo'))->assertOk();

        $this->actingAs($admin)->delete($this->tenantUrl('settings/document-layout/logo'))->assertRedirect();
        $this->assertNull(DocumentLayout::current()->logo_path);
        Storage::disk('local')->assertMissing($path);
    }

    #[Test]
    public function tc_tpl_08_hanya_admin_company_yang_mengubah_layout(): void
    {
        foreach (['warehouse_head', 'warehouse_staff', 'management'] as $role) {
            $user = $this->makeUser($role);
            $this->actingAs($user)->get($this->tenantUrl('settings/document-layout'))->assertForbidden();
            $this->actingAs($user)->get($this->tenantUrl('settings/document-layout/preview'))->assertForbidden();
            $this->actingAs($user)
                ->post($this->tenantUrl('settings/document-layout/logo'), ['logo' => UploadedFile::fake()->image('logo.png')])
                ->assertForbidden();
        }

        $admin = $this->makeUser('company_admin');
        $this->actingAs($admin)->get($this->tenantUrl('settings/document-layout'))->assertOk()
            ->assertSee('Editor template — Fase 2');

        $contoh = $this->actingAs($admin)->get($this->tenantUrl('settings/document-layout/preview'));
        $contoh->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $contoh->headers->get('content-type'));
    }
}

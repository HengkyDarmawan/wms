<?php

declare(strict_types=1);

namespace Tests\Feature\Template;

use App\Domain\Adjustment\Actions\CreateStockAdjustment;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Template\Actions\SaveDocumentTemplate;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Livewire\DocumentLayoutForm;
use App\Domain\Template\Models\DocumentTemplate;
use App\Domain\Template\Support\DocumentPrinter;
use App\Domain\Template\Support\TemplateText;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-TPL-30 s.d. TC-TPL-34 — editor teks template dokumen (A-436): HTML aman,
 * variabel di-escape, `{isi_bawaan}`, cetak & pratinjau.
 */
class DocumentTemplateEditorTest extends TenantTestCase
{
    use ReceiptFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();
    }

    #[Test]
    public function tc_tpl_30_html_disaring_ke_tag_aman(): void
    {
        $bersih = TemplateText::sanitize('<p onclick="x()">Halo <b>{nomor_dokumen}</b><script>alert(1)</script></p>'
            .'<img src="http://jahat/x.png"><div style="background:url(http://jahat)">a</div><a href="http://x">tautan</a>'
            .'<td colspan="2" rowspan="abc">t</td><!-- komentar -->');

        $this->assertStringNotContainsString('script', $bersih);
        $this->assertStringNotContainsString('onclick', $bersih);
        $this->assertStringNotContainsString('<img', $bersih);
        $this->assertStringNotContainsString('url(', $bersih);
        $this->assertStringNotContainsString('<a ', $bersih);
        $this->assertStringNotContainsString('komentar', $bersih);
        $this->assertStringNotContainsString('rowspan', $bersih, 'Atribut angka saja.');
        $this->assertStringContainsString('<b>{nomor_dokumen}</b>', $bersih);
        $this->assertStringContainsString('tautan', $bersih, 'Isi tag terlarang dipertahankan sebagai teks.');
    }

    #[Test]
    public function tc_tpl_31_variabel_tak_dikenal_dan_isi_bawaan_ganda_ditolak(): void
    {
        $simpan = app(SaveDocumentTemplate::class);
        $admin = $this->makeUser('company_admin');

        foreach (['<p>{harga}</p>', '{isi_bawaan}{isi_bawaan}'] as $teks) {
            try {
                $simpan->handle(DocumentTemplateType::StockAdjustment, $teks, $admin);
                $this->fail('Seharusnya ditolak: '.$teks);
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('body', $e->errors());
            }
        }

        $this->expectException(AuthorizationException::class);
        $simpan->handle(DocumentTemplateType::StockAdjustment, '<p>x</p>', $this->makeUser('warehouse_staff'));
    }

    #[Test]
    public function tc_tpl_32_nilai_variabel_di_escape_dan_dipecah_di_isi_bawaan(): void
    {
        $r = TemplateText::render('<p>Untuk {klien}</p>{isi_bawaan}<p>No {nomor_dokumen}</p>', ['klien' => '<script>x</script>PT A', 'nomor_dokumen' => 'ADJ/1']);

        $this->assertSame('<p>Untuk &lt;script&gt;x&lt;/script&gt;PT A</p>', $r['sebelum']);
        $this->assertSame('<p>No ADJ/1</p>', $r['sesudah']);
    }

    #[Test]
    public function tc_tpl_33_dokumen_tercetak_dengan_teks_kustom(): void
    {
        $versi = (int) DocumentTemplate::forType(DocumentTemplateType::StockAdjustment)->version;
        $t = app(SaveDocumentTemplate::class)->handle(DocumentTemplateType::StockAdjustment,
            '<p>Penyesuaian {nomor_dokumen} di gudang {gudang}.</p>{isi_bawaan}<p><small>Diperiksa auditor.</small></p>', $this->makeUser('company_admin'));
        $this->assertSame($versi + 1, (int) $t->version);

        $adj = app(CreateStockAdjustment::class)->handle([
            'warehouse_id' => $this->gudang->id,
            'reason_code_id' => ReasonCode::query()->where('context', ReasonContext::Adjustment->value)->value('id'),
            'notes' => 'uji teks',
        ], [['direction' => 'in', 'bin_id' => $this->binA->id, 'item_id' => $this->baut->id, 'qty' => 2]], $this->makeUser('warehouse_staff'));

        $html = app(DocumentPrinter::class)->view(DocumentTemplateType::StockAdjustment, $adj)->render();
        $this->assertStringContainsString('Penyesuaian '.$adj->number.' di gudang '.$this->gudang->code, $html);
        $this->assertLessThan(strpos($html, 'Diperiksa auditor.'), strpos($html, $this->baut->code), 'Teks sesudah {isi_bawaan} muncul setelah tabel.');

        // Kembalikan ke bawaan: teks hilang.
        app(SaveDocumentTemplate::class)->handle(DocumentTemplateType::StockAdjustment, '', $this->makeUser('company_admin'));
        $this->assertStringNotContainsString('Diperiksa auditor.', app(DocumentPrinter::class)->view(DocumentTemplateType::StockAdjustment, $adj->refresh())->render());
    }

    #[Test]
    public function tc_tpl_34_layar_editor_dan_pratinjau(): void
    {
        $admin = $this->makeUser('company_admin');

        Livewire::actingAs($admin)->test(DocumentLayoutForm::class)
            ->call('aturTeks', DocumentTemplateType::Shipment->value)
            ->assertSee('Variabel yang bisa dipakai')
            ->set('editBody', '<p>Bawa SJ ini ke {proyek}</p>')
            ->call('simpanTeks')->assertHasNoErrors()
            ->set('editBody', '<p>{tidak_ada}</p>')->call('simpanTeks')->assertHasErrors('body');

        $this->assertStringContainsString('{proyek}', (string) DocumentTemplate::forType(DocumentTemplateType::Shipment)->body_html);
        $this->actingAs($admin)->get($this->tenantUrl('settings/document-layout/preview?type=shipment'))->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}

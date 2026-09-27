<?php

declare(strict_types=1);

namespace Tests\Feature\Label;

use App\Domain\Label\Actions\CreateContentLabels;
use App\Domain\Master\Models\Serial;
use App\Domain\Receipt\Actions\CompleteGoodsReceipt;
use App\Domain\Template\Actions\PrintLabels;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Enums\PaperSize;
use App\Domain\Template\Livewire\LabelPrint;
use App\Domain\Template\Models\LabelFormat;
use App\Domain\Template\Support\LabelDesignRules;
use App\Domain\Template\Support\LabelPayload;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Label\Concerns\LabelFixtures;
use Tests\TenantTestCase;

/**
 * TC-TPL-28 s.d. TC-TPL-31, TC-TPL-33 — cetak label kemasan & serial lewat
 * modul Template (A-296, A-298, desain A-261/A-262) dan halaman Telusuri
 * label (A-302).
 */
class PackageLabelPrintTraceTest extends TenantTestCase
{
    use LabelFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanLabel();
    }

    private function html(DocumentTemplateType $type, array $ids): string
    {
        return app(PrintLabels::class)->view($type, $ids, LabelFormat::fromLegacyPaper(PaperSize::Label50x30), 1, $this->makeUser('warehouse_head'))->render();
    }

    #[Test]
    public function tc_tpl_28_label_kemasan_memuat_asal_tanpa_harga(): void
    {
        $grn = $this->grnBerlabel($this->baut, 24, 2, 12);
        $label = $this->labelGrn($grn);

        $html = $this->html(DocumentTemplateType::LabelPackage, $label->pluck('id')->all());
        foreach (['BAUT-M12-0001', 'BAUT-M12-0002', 'Isi 12 PCS', 'PT Baja Jaya', $grn->number, 'Masuk '.now()->lokal()->format('d/m/Y')] as $teks) {
            $this->assertStringContainsString($teks, $html);
        }
        // Gambar barcode/QR (base64) bisa memuat huruf "Rp" secara kebetulan; yang diperiksa teksnya saja.
        $teks = preg_replace('/data:image\/[a-z]+;base64,[A-Za-z0-9+\/=]+/', '', $html);
        $this->assertStringNotContainsStringIgnoringCase('harga', $teks, 'D-07: label tanpa nilai uang.');
        $this->assertStringNotContainsString('Rp', $teks);

        $pdf = $this->actingAs($this->makeUser('warehouse_staff'))
            ->get($this->tenantUrl('labels/print?type=label_package&ids='.$label->pluck('id')->implode(',')));
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));
    }

    #[Test]
    public function tc_tpl_29_label_serial_memuat_vendor_dan_grn_asal(): void
    {
        $grn = $this->grnDiterima([['item_id' => $this->genset->id, 'serial_no' => 'GNS-77']]);
        app(CompleteGoodsReceipt::class)->handle($grn->refresh(), $this->makeUser('warehouse_head'));
        $serial = Serial::query()->where('serial_no', 'GNS-77')->sole();

        $isi = LabelPayload::serial($serial);
        $this->assertSame('GNS-77', $isi['title']);
        $this->assertStringContainsString($grn->number, $isi['detail']);
        $this->assertStringContainsString('PT Baja Jaya', $isi['detail']);
        $this->assertStringContainsString('GNS-77', $this->html(DocumentTemplateType::LabelSerial, [$serial->id]));
    }

    #[Test]
    public function tc_tpl_30_desain_dan_menu_cetak_mengenal_jenis_label_baru(): void
    {
        $this->assertSame(__('Kode label kemasan'), LabelDesignRules::sources(DocumentTemplateType::LabelPackage)['title']);
        $this->assertSame(__('Nomor seri'), LabelDesignRules::sources(DocumentTemplateType::LabelSerial)['title']);

        $grn = $this->grnBerlabel($this->baut, 12, 1, 12);
        $admin = $this->makeUser('company_admin');

        foreach (['label_package', 'label_serial'] as $jenis) {
            $pdf = $this->actingAs($admin)->get($this->tenantUrl('settings/label-designs/preview?type='.$jenis));
            $pdf->assertOk();
            $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'), $jenis);
        }

        Livewire::actingAs($this->makeUser('warehouse_head'))->withQueryParams(['type' => 'label_package'])->test(LabelPrint::class)
            ->assertSee('BAUT-M12-0001')
            ->assertSee($grn->number);
    }

    #[Test]
    public function tc_tpl_31_label_isi_dicetak_dengan_isinya_masing_masing(): void
    {
        $induk = $this->labelGrn($this->grnBerlabel($this->baut, 12, 1, 12))->first();
        $isi = app(CreateContentLabels::class)->handle($induk, 4, $this->makeUser());

        $html = $this->html(DocumentTemplateType::LabelPackage, $isi->pluck('id')->all());
        $this->assertStringContainsString('BAUT-M12-0001-0004', $html);
        $this->assertSame(4, substr_count($html, 'Isi 3 PCS'));
    }

    #[Test]
    public function tc_tpl_33_telusuri_label_menampilkan_asal_dan_riwayat(): void
    {
        $grn = $this->grnBerlabel($this->baut, 24, 2, 12);
        $this->putAway($grn);
        $induk = $this->labelGrn($grn)->first();
        app(CreateContentLabels::class)->handle($induk, 2, $this->makeUser());

        $staf = $this->makeUser('warehouse_staff');

        $this->actingAs($staf)->get($this->tenantUrl('labels/trace?code='.strtolower($induk->code)))
            ->assertOk()
            ->assertSee($induk->code)
            ->assertSee('PT Baja Jaya')
            ->assertSee($grn->number)
            ->assertSee('Diterima')
            ->assertSee('Ditaruh di bin')
            ->assertSee('Dipecah ke label isi')
            ->assertSee('BAUT-M12-0001-0002');

        // Kode item → daftar label item itu.
        $this->actingAs($staf)->get($this->tenantUrl('labels/trace?code=BAUT-M12'))
            ->assertOk()
            ->assertSee('BAUT-M12-0002');

        // Menu samping untuk pemegang item.view.
        $this->actingAs($staf)->get($this->tenantUrl('/'))->assertSee(route('labels.trace'), false);
    }
}

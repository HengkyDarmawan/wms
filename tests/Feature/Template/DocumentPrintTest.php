<?php

declare(strict_types=1);

namespace Tests\Feature\Template;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Adjustment\Actions\CreateStockAdjustment;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Receipt\Actions\CompleteGoodsReceipt;
use App\Domain\Receipt\Actions\CompletePutaway;
use App\Domain\Receipt\Actions\CreateVendorReturn;
use App\Domain\Receipt\Enums\QcResult;
use App\Domain\Receipt\Models\VendorReturn;
use App\Domain\Shipment\Models\DeliveryDiscrepancy;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Template\Actions\SaveDocumentLayout;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Enums\PaperSize;
use App\Domain\Template\Models\DocumentTemplate;
use App\Domain\Template\Support\DocumentPrinter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Receipt\Concerns\OutboundChain;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-TPL-01 s.d. TC-TPL-06, TC-TPL-11, TC-TPL-14 — cetak dokumen bawaan
 * dengan layout induk (18-template-dokumen-label §5.1, §5.2, D-07).
 *
 * Teks PDF dompdf terkompresi, jadi isi diperiksa dari HTML yang sama
 * (`DocumentPrinter::view`), sedangkan route diperiksa jenis responsnya.
 */
class DocumentPrintTest extends TenantTestCase
{
    use OutboundChain;
    use ReceiptFixtures;

    private Shipment $sj;

    private PickTask $pck;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();

        // GRN → QC lolos → put-away → REQ → PCK → SJ berangkat → bukti terima kurang 5 (DSC).
        $grn = $this->grnDiterima([['item_id' => $this->kabel->id, 'qty_received' => 50]]);
        $this->qc($grn, 0, QcResult::Passed, false);
        $grn = app(CompleteGoodsReceipt::class)->handle($grn->refresh(), $this->makeUser());
        app(CompletePutaway::class)->handle($grn->putawayTasks()->sole(), [], $this->makeUser());

        $proyek = $this->makeProject();
        $req = $this->reqDisetujui($proyek, $this->gudang, $this->kabel, 30);
        $this->pck = $this->pckSelesai($req);
        $this->sj = $this->sjBerangkat($this->pck, ['destination_type' => 'project_client', 'destination_project_id' => $proyek->id]);
        $this->sj = $this->buktiTerima($this->sj, 25, 5);
    }

    private function html(DocumentTemplateType $type, object $model): string
    {
        return app(DocumentPrinter::class)->view($type, $model)->render();
    }

    private function tanpaHarga(string $html): void
    {
        // Gambar QR/logo base64 dibuang dulu: urutan acaknya (bergantung id dokumen) bisa memuat "Rp".
        $html = (string) preg_replace('#data:image/[a-z]+;base64,[A-Za-z0-9+/=]+#', '', $html);

        foreach (['Rp', 'harga', 'Harga', 'Total', 'total harga'] as $kata) {
            $this->assertStringNotContainsString($kata, $html, 'Cetakan tidak boleh memuat nilai uang (D-07).');
        }
    }

    #[Test]
    public function tc_tpl_01_sj_bukti_terima_picklist_dan_ba_selisih_tercetak(): void
    {
        $admin = $this->makeUser('company_admin');
        $dsc = DeliveryDiscrepancy::query()->where('shipment_id', $this->sj->id)->sole();

        foreach ([
            ['shipment', $this->sj->id],
            ['proof-of-delivery', $this->sj->id],
            ['pick-task', $this->pck->id],
            ['delivery-discrepancy', $dsc->id],
        ] as [$jenis, $id]) {
            $pdf = $this->actingAs($admin)->get($this->tenantUrl('print/'.$jenis.'/'.$id));
            $pdf->assertOk();
            $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'), $jenis);
        }

        $sj = $this->html(DocumentTemplateType::Shipment, $this->sj);
        $this->assertStringContainsString($this->sj->number, $sj);
        $this->assertStringContainsString('KABEL-NYM', $sj);
        $this->assertStringContainsString('>30<', $sj);
        $this->assertStringContainsString($this->pck->number, $sj);
        $this->assertStringContainsString('Penerima Uji', $sj);
        $this->assertStringContainsString('data:image/png;base64', $sj, 'QR dokumen ikut tercetak.');
        $this->tanpaHarga($sj);

        $bukti = $this->html(DocumentTemplateType::ProofOfDelivery, $this->sj);
        $this->assertStringContainsString('>25<', $bukti);
        $this->assertStringContainsString('>5<', $bukti);
        $this->tanpaHarga($bukti);

        $pck = $this->html(DocumentTemplateType::PickTask, $this->pck);
        $this->assertStringContainsString($this->pck->number, $pck);
        $this->assertStringContainsString('CKG-A-R01-L1-B01', $pck);
        $this->tanpaHarga($pck);

        $ba = $this->html(DocumentTemplateType::DeliveryDiscrepancy, $dsc);
        $this->assertStringContainsString($dsc->number, $ba);
        $this->assertStringContainsString($this->sj->number, $ba);
        $this->tanpaHarga($ba);
    }

    #[Test]
    public function tc_tpl_02_surat_retur_dan_ba_penyesuaian_tercetak(): void
    {
        $grn = $this->grnDiterima([['item_id' => $this->kabel->id, 'qty_received' => 8]]);
        $this->qc($grn, 0, QcResult::Rejected);

        $rtv = app(CreateVendorReturn::class)->handle(
            $grn->refresh(),
            [['goods_receipt_line_id' => $grn->lines()->sole()->id, 'qty_base' => 8]],
            null,
            $this->makeUser('warehouse_staff'),
        );

        $adj = app(CreateStockAdjustment::class)->handle([
            'warehouse_id' => $this->gudang->id,
            'reason_code_id' => ReasonCode::query()->where('context', ReasonContext::Adjustment->value)->value('id'),
            'notes' => 'uji cetak',
        ], [['direction' => 'in', 'bin_id' => $this->binA->id, 'item_id' => $this->baut->id, 'qty' => 2]], $this->makeUser('warehouse_staff'));

        $admin = $this->makeUser('company_admin');
        $this->actingAs($admin)->get($this->tenantUrl('print/vendor-return/'.$rtv->id))->assertOk();
        $this->actingAs($admin)->get($this->tenantUrl('print/stock-adjustment/'.$adj->id))->assertOk();

        $surat = $this->html(DocumentTemplateType::VendorReturn, VendorReturn::query()->findOrFail($rtv->id));
        $this->assertStringContainsString($rtv->number, $surat);
        $this->assertStringContainsString('PT Baja Jaya', $surat);
        $this->assertStringContainsString($grn->number, $surat);
        $this->tanpaHarga($surat);

        $ba = $this->html(DocumentTemplateType::StockAdjustment, StockAdjustment::query()->findOrFail($adj->id));
        $this->assertStringContainsString($adj->number, $ba);
        $this->assertStringContainsString('+2', $ba);
        $this->assertStringContainsString('BAUT-M12', $ba);
        $this->tanpaHarga($ba);
    }

    #[Test]
    public function tc_tpl_03_cetak_mengikuti_izin_lihat_dan_cakupan(): void
    {
        $gudangLain = $this->buatGudang('BKS', 'Gudang Bekasi');
        $stafLain = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $gudangLain->id);

        $status = $this->actingAs($stafLain)->get($this->tenantUrl('print/shipment/'.$this->sj->id))->status();
        $this->assertContains($status, [403, 404], 'SJ gudang lain tidak bisa dicetak (BR-ACC-05).');

        // Klien boleh melihat SJ tetapi tidak picklist.
        $klien = $this->makeUser('client_user');
        $this->actingAs($klien)->get($this->tenantUrl('print/pick-task/'.$this->pck->id))->assertForbidden();

        $stafCkg = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->gudang->id);
        $this->actingAs($stafCkg)->get($this->tenantUrl('print/shipment/'.$this->sj->id))->assertOk();
    }

    #[Test]
    public function tc_tpl_04_jenis_tak_dikenal_dan_stub(): void
    {
        $admin = $this->makeUser('company_admin');

        $this->actingAs($admin)->get($this->tenantUrl('print/faktur/1'))->assertNotFound();
        $this->actingAs($admin)->get($this->tenantUrl('print/label-bin/1'))->assertNotFound();
        $this->actingAs($admin)->get($this->tenantUrl('print/stock-count/1'))->assertNotFound();
        $this->actingAs($admin)->get($this->tenantUrl('print/shipment/999999'))->assertNotFound();
        // BA Waste (modul Konversi & Waste, A-160) dan BA Serah Terima Aset (modul Aset)
        // sudah aktif: dokumen yang tidak ada = 404, bukan lagi 501.
        $this->actingAs($admin)->get($this->tenantUrl('print/asset-handover/999999'))->assertNotFound();
        $this->actingAs($admin)->get($this->tenantUrl('print/waste-disposal/999999'))->assertNotFound();
    }

    #[Test]
    public function tc_tpl_05_dokumen_batal_bertanda_air(): void
    {
        $this->assertStringNotContainsString('DIBATALKAN', $this->html(DocumentTemplateType::PickTask, $this->pck));

        $this->pck->forceFill(['status' => 'cancelled'])->save();

        $this->assertStringContainsString('DIBATALKAN', $this->html(DocumentTemplateType::PickTask, $this->pck->refresh()));
    }

    #[Test]
    public function tc_tpl_06_layout_induk_tercetak_di_kop_dan_kaki(): void
    {
        Storage::fake('local');
        $admin = $this->makeUser('company_admin');
        $aksi = app(SaveDocumentLayout::class);

        $aksi->handle(
            ['name' => 'Kop PT Uji', 'header' => "<b>Jl. Uji No. 1</b>\nTelp 021-000", 'footer' => 'Dokumen resmi PT Uji', 'accent' => '#aa3300'],
            ['shipment' => "Petugas gudang\nSopir\nPenerima barang"],
            [],
            $admin,
        );
        $aksi->storeLogo(UploadedFile::fake()->image('logo.png', 120, 40), $admin);

        $html = $this->html(DocumentTemplateType::Shipment, $this->sj);

        $this->assertStringContainsString('&lt;b&gt;Jl. Uji No. 1&lt;/b&gt;', $html, 'Teks kop di-escape (A-122).');
        $this->assertStringNotContainsString('<b>Jl. Uji', $html);
        $this->assertStringContainsString('Telp 021-000', $html);
        $this->assertStringContainsString('Dokumen resmi PT Uji', $html);
        $this->assertStringContainsString('#aa3300', $html);
        $this->assertStringContainsString('Petugas gudang', $html);
        $this->assertStringContainsString('Sopir', $html);
        $this->assertStringNotContainsString('Dibuat oleh', $html);
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'data:image/png;base64'), 'Logo dan QR tercetak.');

        // Dokumen lain tetap memakai blok bawaan.
        $this->assertStringContainsString('Picker', $this->html(DocumentTemplateType::PickTask, $this->pck));
    }

    #[Test]
    public function tc_tpl_11_template_bawaan_dibuat_saat_pertama_dicetak(): void
    {
        $this->assertSame(0, DocumentTemplate::query()->count());

        $this->actingAs($this->makeUser('company_admin'))
            ->get($this->tenantUrl('print/shipment/'.$this->sj->id))->assertOk();

        $template = DocumentTemplate::query()->where('document_type', 'shipment')->sole();
        $this->assertSame(PaperSize::A4, $template->paper);
        $this->assertTrue($template->is_default);
        $this->assertNotNull($template->layout_id);

        DocumentTemplate::ensureDefaults();
        $this->assertSame(count(DocumentTemplateType::cases()), DocumentTemplate::query()->count());
        $this->assertSame(PaperSize::A4Landscape, DocumentTemplate::forType(DocumentTemplateType::StockCount)->paper);
        $this->assertSame(PaperSize::LabelA4Grid, DocumentTemplate::forType(DocumentTemplateType::LabelBin)->paper);
    }

    #[Test]
    public function tc_tpl_14_laporan_opname_memakai_kop_layout_induk(): void
    {
        app(SaveDocumentLayout::class)->handle(['header' => 'Kawasan Industri Cakung'], [], [], $this->makeUser('company_admin'));

        $kop = view('print.partials.kop', ['judul' => 'Laporan Stock Opname', 'nomor' => 'OPN/CKG/2609/0001', 'status' => 'Ditutup', 'qr' => null])->render();

        $this->assertStringContainsString('Kawasan Industri Cakung', $kop);
        $this->assertStringContainsString('OPN/CKG/2609/0001', $kop);
        $this->assertStringContainsString(
            "@include('print.partials.kop'",
            (string) file_get_contents(resource_path('views/count/report.blade.php')),
            'Laporan opname (modul Count) memakai kop yang sama.',
        );
    }
}

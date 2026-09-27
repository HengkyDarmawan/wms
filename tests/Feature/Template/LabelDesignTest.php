<?php

declare(strict_types=1);

namespace Tests\Feature\Template;

use App\Domain\Template\Actions\PrintLabels;
use App\Domain\Template\Actions\SaveLabelDesign;
use App\Domain\Template\Actions\SaveLabelFormat;
use App\Domain\Template\Actions\SetLabelFormatActive;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Enums\LabelCodeMode;
use App\Domain\Template\Enums\LabelMedia;
use App\Domain\Template\Livewire\LabelDesigner;
use App\Domain\Template\Livewire\LabelFormatManager;
use App\Domain\Template\Livewire\LabelPrint;
use App\Domain\Template\Models\DocumentTemplate;
use App\Domain\Template\Models\LabelDesign;
use App\Domain\Template\Models\LabelFormat;
use App\Domain\Template\Support\LabelDesignRules;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-TPL-17 s.d. TC-TPL-21 — master ukuran label (A-261) dan desain label
 * dengan pilihan barcode/QR (A-262), 18-template-dokumen-label §3.3–§3.4, §6.
 */
class LabelDesignTest extends TenantTestCase
{
    use ReceiptFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();
    }

    private function format(string $kode): LabelFormat
    {
        return LabelFormat::query()->where('code', $kode)->firstOrFail();
    }

    /** @param  array<string, mixed>  $data */
    private function simpanFormat(array $data, ?LabelFormat $format = null): LabelFormat
    {
        return app(SaveLabelFormat::class)->handle($data, $this->makeUser('company_admin'), $format);
    }

    /** @param  array<string, mixed>  $data */
    private function galat(callable $aksi): array
    {
        try {
            $aksi();
            $this->fail('Seharusnya ditolak validasi.');
        } catch (ValidationException $e) {
            return array_keys($e->errors());
        }
    }

    #[Test]
    public function tc_tpl_17_master_ukuran_label_gulungan_dan_lembar(): void
    {
        // Preset dari migrasi; template lama dipetakan ke preset (A-120 → A-261).
        $this->assertSame(6, LabelFormat::query()->count());
        $this->assertSame('A4-3X8', LabelFormat::defaultFor(DocumentTemplateType::LabelBin)->code);
        $this->assertSame('THERMAL-50X30', LabelFormat::defaultFor(DocumentTemplateType::LabelItem)->code);

        // Posisi label lembar A4 2×7 (margin & jarak antar kolom).
        $avery = $this->format('A4-2X7');
        $this->assertSame(14, $avery->perPage());
        $this->assertSame([4.65, 15.15], $avery->positions()[0]);
        $this->assertSame([106.25, 15.15], $avery->positions()[1], '4,65 + 99,1 + 2,5');
        $this->assertSame([4.65, 53.25], $avery->positions()[2], '15,15 + 38,1');

        $gulungan = $this->simpanFormat(['code' => 'thermal-60x40', 'name' => 'Thermal 60×40', 'media' => 'roll', 'width_mm' => '60', 'height_mm' => '40,5']);
        $this->assertSame('THERMAL-60X40', $gulungan->code, 'Kode huruf besar.');
        $this->assertSame([60.0, 40.5], $gulungan->pageSize());
        $this->assertSame([[0.0, 0.0]], $gulungan->positions());

        $this->assertEqualsCanonicalizing(['code', 'name', 'width_mm'], $this->galat(fn () => $this->simpanFormat(['code' => 'THERMAL-60X40', 'name' => '', 'media' => 'roll', 'width_mm' => '5', 'height_mm' => '30'])));
        $this->assertSame(['media'], $this->galat(fn () => $this->simpanFormat(['code' => 'X1', 'name' => 'X', 'media' => 'kertas', 'width_mm' => '50', 'height_mm' => '30'])));

        // Lembar yang tidak muat ditolak dengan kebutuhan mm-nya.
        $lembar = ['code' => 'A4-4X10', 'name' => 'A4 4×10', 'media' => 'sheet', 'width_mm' => '52', 'height_mm' => '29.7',
            'page_width_mm' => '210', 'page_height_mm' => '297', 'columns' => '4', 'rows' => '10',
            'margin_top_mm' => '0', 'margin_left_mm' => '1', 'gap_x_mm' => '0.5', 'gap_y_mm' => '0'];
        $this->assertSame(['columns'], $this->galat(fn () => $this->simpanFormat($lembar)), '1 + 4×52 + 3×0,5 = 210,5 > 210');
        $lembar['gap_x_mm'] = '0';
        $a4 = $this->simpanFormat($lembar);
        $this->assertSame(40, $a4->perPage());
        $this->assertSame([157.0, 267.3], $a4->positions()[39]);

        // Kode terkunci saat diubah; ukuran boleh berubah.
        $ubah = $this->simpanFormat(['code' => 'LAIN', 'name' => 'Thermal 60×40 baru', 'media' => 'roll', 'width_mm' => '62', 'height_mm' => '40'], $gulungan);
        $this->assertSame('THERMAL-60X40', $ubah->code);
        $this->assertSame(62.0, $ubah->width_mm);
    }

    #[Test]
    public function tc_tpl_18_nonaktifkan_ukuran_label(): void
    {
        $admin = $this->makeUser('company_admin');
        $aksi = app(SetLabelFormatActive::class);

        $this->assertSame(['format'], $this->galat(fn () => $aksi->handle($this->format('THERMAL-50X30'), false, $admin)), 'Bawaan label item/lot/potongan.');

        $kecil = $aksi->handle($this->format('THERMAL-40X30'), false, $admin);
        $this->assertFalse($kecil->is_active);
        $this->assertNotNull($kecil->fresh(), 'Tidak dihapus (P-03).');

        // Ukuran nonaktif tidak ditawarkan dan tidak bisa dipakai mencetak.
        $kepala = $this->makeUser('warehouse_head');
        Livewire::actingAs($kepala)->test(LabelPrint::class, ['type' => 'label_item'])
            ->assertDontSee('Thermal 40×30 mm —')
            ->assertSee('Thermal 50×30 mm —');
        $this->actingAs($kepala)->getJson($this->tenantUrl('labels/print?type=label_item&ids='.$this->baut->id.'&format='.$kecil->id))
            ->assertStatus(422)->assertJsonValidationErrors('format');

        $aksi->handle($kecil, true, $admin);
        $this->assertTrue($kecil->refresh()->is_active);

        // Format aktif terakhir tidak bisa dinonaktifkan.
        LabelFormat::query()->whereNotIn('code', ['THERMAL-50X30'])->update(['is_active' => false]);
        DocumentTemplate::query()->update(['label_format_id' => null]);
        $this->assertSame(['format'], $this->galat(fn () => $aksi->handle($this->format('THERMAL-50X30'), false, $admin)));
    }

    #[Test]
    public function tc_tpl_19_desain_label_dan_pilihan_kode(): void
    {
        $admin = $this->makeUser('company_admin');
        $kepala = $this->makeUser('warehouse_head');
        $thermal = $this->format('THERMAL-100X50');

        // Tanpa desain tersimpan: tata letak otomatis di dalam label.
        $otomatis = LabelDesign::resolve(DocumentTemplateType::LabelItem, $thermal);
        $this->assertFalse($otomatis->exists);
        foreach ($otomatis->elements as $kunci => $e) {
            $this->assertLessThanOrEqual(100.0, $e['x'] + $e['w'], $kunci);
            $this->assertLessThanOrEqual(50.0, $e['y'] + $e['h'], $kunci);
        }

        // Isian di luar label dirapikan ke dalam batas; kunci asing dibuang.
        $elemen = LabelDesignRules::defaults($thermal, LabelCodeMode::Both);
        $elemen['title'] = ['visible' => true, 'x' => 95, 'y' => -3, 'w' => 30, 'h' => 8, 'font' => 99, 'bold' => '1', 'align' => 'kiri'];
        $elemen['harga'] = ['visible' => true, 'x' => 1, 'y' => 1, 'w' => 10, 'h' => 5];
        $desain = app(SaveLabelDesign::class)->handle(DocumentTemplateType::LabelItem, $thermal, 'qr', $elemen, true, $admin);

        $this->assertEquals(['x' => 70.0, 'y' => 0.0, 'w' => 30.0, 'font' => 72.0, 'bold' => true, 'align' => 'left'],
            array_intersect_key($desain->elements['title'], array_flip(['x', 'y', 'w', 'font', 'bold', 'align'])));
        $this->assertArrayNotHasKey('harga', $desain->elements);
        $this->assertFalse($desain->elements['barcode']['visible'], 'QR saja: barcode ikut pilihan kode.');
        $this->assertTrue($desain->elements['qr']['visible']);
        $this->assertSame($thermal->id, LabelFormat::defaultFor(DocumentTemplateType::LabelItem)->id, 'Dijadikan bawaan jenis item.');

        // Cetak tanpa ukuran = ukuran bawaan baru; QR saja → tanpa gambar barcode.
        $html = app(PrintLabels::class)->view(DocumentTemplateType::LabelItem, [$this->baut->id], LabelFormat::defaultFor(DocumentTemplateType::LabelItem), 1, $kepala)->render();
        $this->assertSame(1, substr_count($html, '<div class="label"'));
        $this->assertStringContainsString('left: 70mm; top: 0mm; width: 30mm;', $html);
        $this->assertStringContainsString('font-size: 72pt; font-weight: bold;', $html);
        $this->assertSame(1, substr_count($html, 'data:image/png'), 'Hanya QR.');
        $this->assertStringNotContainsString('text-align: center; white-space: nowrap;', $html, 'Teks di bawah barcode tidak ada.');

        // Barcode saja → satu gambar (barcode) + teks kode; keduanya → dua gambar.
        foreach (['barcode' => 1, 'both' => 2] as $mode => $gambar) {
            $d = app(SaveLabelDesign::class)->handle(DocumentTemplateType::LabelItem, $thermal, $mode, $desain->elements, false, $admin);
            $html = app(PrintLabels::class)->view(DocumentTemplateType::LabelItem, [$this->baut->id], $thermal, 1, $kepala)->render();
            $this->assertSame($gambar, substr_count($html, 'data:image/png'), $mode);
            $this->assertSame($mode === 'barcode', $d->elements['qr']['visible'] === false);
        }
        $this->assertSame(1, LabelDesign::query()->count(), 'Satu desain per jenis × ukuran.');

        // Ukuran diperkecil setelah desain disimpan: desain dirapikan saat dipakai.
        $this->simpanFormat(['name' => $thermal->name, 'media' => 'roll', 'width_mm' => '60', 'height_mm' => '40'], $thermal);
        $rapi = LabelDesign::resolve(DocumentTemplateType::LabelItem, $thermal->refresh());
        $this->assertLessThanOrEqual(60.0, $rapi->elements['title']['x'] + $rapi->elements['title']['w']);

        $this->assertSame(['code_mode'], $this->galat(fn () => app(SaveLabelDesign::class)->handle(DocumentTemplateType::LabelItem, $thermal, 'datamatrix', [], false, $admin)));
    }

    #[Test]
    public function tc_tpl_20_layar_ukuran_dan_desain_hanya_admin(): void
    {
        $admin = $this->makeUser('company_admin');
        $kepala = $this->makeUser('warehouse_head');

        foreach (['settings/label-formats', 'settings/label-designs', 'settings/label-designs/preview?type=label_bin'] as $url) {
            $this->actingAs($kepala)->get($this->tenantUrl($url))->assertForbidden();
        }
        $this->actingAs($admin)->get($this->tenantUrl('settings/label-formats'))->assertOk()->assertSee('A4-2X7');
        $this->actingAs($admin)->get($this->tenantUrl('settings/label-designs?type=label_lot'))->assertOk()->assertSee('labelDesigner', false);
        $pdf = $this->actingAs($admin)->get($this->tenantUrl('settings/label-designs/preview?type=label_bin&format='.$this->format('A4-3X8')->id));
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));

        $beranda = $this->actingAs($admin)->get($this->tenantUrl('/'))->assertOk();
        $beranda->assertSee(route('label-designs.index'), false)->assertSee(route('label-formats.index'), false);
        $this->actingAs($kepala)->get($this->tenantUrl('/'))->assertOk()->assertDontSee(route('label-designs.index'), false);

        // Layar ukuran: tambah lembar, tengahkan susunan, simpan.
        Livewire::actingAs($admin)->test(LabelFormatManager::class)
            ->call('baru')
            ->set('form.code', 'LETTER-2X5')
            ->set('form.name', 'Letter 2×5')
            ->set('form.media', LabelMedia::Sheet->value)
            ->set('form.width_mm', '101,6')
            ->set('form.height_mm', '50,8')
            ->call('halaman', 'letter')
            ->set('form.columns', '2')
            ->set('form.rows', '5')
            ->call('tengahkan')
            ->assertSet('form.margin_left_mm', '6,35')
            ->assertSet('form.margin_top_mm', '12,7')
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('LETTER-2X5');
        $this->assertSame(10, $this->format('LETTER-2X5')->perPage());

        // Nonaktifkan bawaan ditolak lewat layar.
        Livewire::actingAs($admin)->test(LabelFormatManager::class)
            ->call('aktifkan', $this->format('A4-3X8')->id, false)
            ->assertHasErrors('format');

        // Desainer: simpan dari kanvas (array elemen, kode, jadikan bawaan).
        $lot = $this->format('THERMAL-40X30');
        $komponen = Livewire::actingAs($admin)->test(LabelDesigner::class, ['type' => 'label_lot', 'formatId' => (string) $lot->id])
            ->assertSet('formatId', (string) $lot->id)
            ->assertSee(__('Tanggal masuk & kedaluwarsa'));
        $elemen = LabelDesignRules::defaults($lot, LabelCodeMode::Barcode);
        $elemen['title']['x'] = 3.5;
        $komponen->call('simpan', $elemen, 'barcode', true)->assertHasNoErrors()->assertSet('versi', 1);

        $d = LabelDesign::query()->where('document_type', 'label_lot')->sole();
        $this->assertSame(3.5, $d->elements['title']['x']);
        $this->assertSame(LabelCodeMode::Barcode, $d->code_mode);
        $this->assertSame($lot->id, (int) DocumentTemplate::forType(DocumentTemplateType::LabelLot)->label_format_id);

        // Layar cetak memakai ukuran bawaan baru.
        Livewire::actingAs($kepala)->test(LabelPrint::class, ['type' => 'label_lot'])->assertSet('formatId', (string) $lot->id);
    }

    #[Test]
    public function tc_tpl_21_pdf_label_lembar_dan_gulungan_bebas(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $lembar = $this->simpanFormat(['code' => 'UJI-2X3', 'name' => 'Uji 2×3', 'media' => 'sheet', 'width_mm' => '90', 'height_mm' => '80',
            'page_width_mm' => '210', 'page_height_mm' => '297', 'columns' => '2', 'rows' => '3',
            'margin_top_mm' => '10', 'margin_left_mm' => '12', 'gap_x_mm' => '6', 'gap_y_mm' => '5']);

        $html = app(PrintLabels::class)->view(DocumentTemplateType::LabelBin, [$this->binA->id, $this->binB->id], $lembar, 4, $kepala)->render();
        $this->assertSame(8, substr_count($html, '<div class="label"'));
        $this->assertSame(2, substr_count($html, 'class="halaman'), '6 per lembar.');
        $this->assertStringContainsString('left: 108mm; top: 95mm; width: 90mm; height: 80mm;', $html, 'Label ke-4: kolom 2, baris 2.');
        $this->assertStringContainsString('width: 210mm; height: 296.7mm;', $html);

        $pdf = $this->actingAs($kepala)->get($this->tenantUrl('labels/print?type=label_bin&ids='.$this->binA->id.'&format='.$lembar->id.'&copies=2'));
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));

        $gulung = $this->simpanFormat(['code' => 'THERMAL-75X25', 'name' => 'Thermal 75×25', 'media' => 'roll', 'width_mm' => '75', 'height_mm' => '25']);
        $html = app(PrintLabels::class)->view(DocumentTemplateType::LabelItem, [$this->baut->id, $this->semen->id], $gulung, 1, $kepala)->render();
        $this->assertSame(2, substr_count($html, 'class="halaman'), 'Gulungan: satu label per halaman.');
        $this->assertStringContainsString('width: 75mm; height: 24.7mm;', $html);
        $this->assertStringContainsString('left: 0mm; top: 0mm; width: 75mm; height: 25mm;', $html);
    }
}

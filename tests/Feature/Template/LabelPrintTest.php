<?php

declare(strict_types=1);

namespace Tests\Feature\Template;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Template\Actions\PrintLabels;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Enums\PaperSize;
use App\Domain\Template\Livewire\LabelPrint;
use App\Domain\Template\Support\LabelPayload;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-TPL-09, TC-TPL-10, TC-TPL-12, TC-TPL-13 — label barcode/QR dan menu
 * (18-template-dokumen-label §5.3, §6, A-120, A-121, A-124).
 */
class LabelPrintTest extends TenantTestCase
{
    use ReceiptFixtures;

    private Lot $lot;

    private Piece $potongan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();

        $this->baut->forceFill(['barcode' => '8991234567890'])->save();
        $this->lot = Lot::create(['item_id' => $this->semen->id, 'lot_no' => 'LOT-2609-A', 'expiry_date' => '2027-03-31']);
        $this->potongan = Piece::create(['item_id' => $this->pipa->id, 'piece_no' => 'P-000123', 'length' => 6]);
    }

    private function html(DocumentTemplateType $type, array $ids, PaperSize $paper, int $copies = 1, ?\App\Domain\Access\Models\User $actor = null): string
    {
        return app(PrintLabels::class)->view($type, $ids, $paper, $copies, $actor ?? $this->makeUser('warehouse_head'))->render();
    }

    #[Test]
    public function tc_tpl_09_empat_jenis_label_dan_dua_kertas(): void
    {
        $kepala = $this->makeUser('warehouse_head');

        // Satu PDF per jenis (kertas bergantian); isi tiap kertas diperiksa dari HTML di bawah.
        // Merender PDF memakan memori, jadi jumlahnya dibatasi.
        foreach ([
            ['label_bin', $this->binA->id.','.$this->binB->id, 'label_50x30'],
            ['label_item', (string) $this->baut->id, 'label_a4_3x8'],
            ['label_lot', (string) $this->lot->id, 'label_50x30'],
            ['label_piece', (string) $this->potongan->id, 'label_a4_3x8'],
        ] as [$jenis, $ids, $kertas]) {
            $pdf = $this->actingAs($kepala)->get($this->tenantUrl('labels/print?type='.$jenis.'&ids='.$ids.'&paper='.$kertas.'&copies=2'));
            $pdf->assertOk();
            $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'), $jenis.' '.$kertas);
        }

        // Thermal: satu label per halaman → 2 bin × 2 salinan = 4 halaman.
        $thermal = $this->html(DocumentTemplateType::LabelBin, [$this->binA->id, $this->binB->id], PaperSize::Label50x30, 2, $kepala);
        $this->assertSame(4, substr_count($thermal, 'class="halaman'));
        $this->assertSame(4, substr_count($thermal, '<div class="label">'));
        $this->assertStringContainsString('CKG-A-R01-L1-B01', $thermal);
        $this->assertStringContainsString('Gudang Utama Cakung', $thermal);

        // A4 3×8: 26 label → dua lembar.
        $binLain = collect(range(3, 15))->map(fn (int $n) => Bin::create([
            'warehouse_id' => $this->gudang->id, 'code' => sprintf('CKG-A-R02-L1-B%02d', $n), 'bin_type' => BinType::Storage,
        ])->id)->all();
        $a4 = $this->html(DocumentTemplateType::LabelBin, $binLain, PaperSize::LabelA4Grid, 2, $kepala);
        $this->assertSame(26, substr_count($a4, '<div class="label">'));
        $this->assertSame(2, substr_count($a4, 'class="halaman'));

        $item = $this->html(DocumentTemplateType::LabelItem, [$this->baut->id], PaperSize::Label50x30);
        $this->assertStringContainsString('8991234567890', $item);

        $lot = $this->html(DocumentTemplateType::LabelLot, [$this->lot->id], PaperSize::Label50x30);
        $this->assertStringContainsString('LOT-2609-A', $lot);
        $this->assertStringContainsString('31/03/2027', $lot);

        $potongan = $this->html(DocumentTemplateType::LabelPiece, [$this->potongan->id], PaperSize::Label50x30);
        $this->assertStringContainsString('P-000123', $potongan);
        $this->assertStringContainsString('Panjang 6', $potongan);

        // Tanpa kertas di URL → kertas bawaan template (bin = A4 3×8).
        $this->actingAs($kepala)->get($this->tenantUrl('labels/print?type=label_bin&ids='.$this->binA->id))->assertOk();
    }

    #[Test]
    public function tc_tpl_10_izin_dan_batas_cetak(): void
    {
        $url = 'labels/print?type=label_bin&ids='.$this->binA->id.'&paper=label_50x30';

        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl($url))->assertForbidden();
        $this->actingAs($this->makeUser('pr_follow_up'))->get($this->tenantUrl('labels'))->assertForbidden();

        $staf = $this->makeUser('warehouse_staff');
        $this->actingAs($staf)->get($this->tenantUrl($url))->assertOk();
        $this->actingAs($staf)->get($this->tenantUrl('labels'))->assertOk();

        // Bin gudang lain tidak ikut tercetak (BR-ACC-05).
        $gudangLain = $this->buatGudang('BKS', 'Gudang Bekasi');
        $binLain = Bin::create(['warehouse_id' => $gudangLain->id, 'code' => 'BKS-A-R01-L1-B01', 'bin_type' => BinType::Storage]);
        $stafCkg = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->gudang->id);
        $this->actingAs($stafCkg)->get($this->tenantUrl('labels/print?type=label_bin&ids='.$binLain->id.'&paper=label_50x30'))->assertNotFound();

        $ids = implode(',', range(1, 201));
        $this->actingAs($staf)->getJson($this->tenantUrl('labels/print?type=label_item&paper=label_50x30&ids='.$ids))
            ->assertStatus(422)->assertJsonValidationErrors('ids');
        $this->actingAs($staf)->getJson($this->tenantUrl('labels/print?type=label_bin&paper=label_50x30&copies=11&ids='.$this->binA->id))
            ->assertStatus(422)->assertJsonValidationErrors('copies');
        $this->actingAs($staf)->getJson($this->tenantUrl('labels/print?type=label_bin&paper=a4&ids='.$this->binA->id))
            ->assertStatus(422)->assertJsonValidationErrors('paper');
        $this->actingAs($staf)->getJson($this->tenantUrl('labels/print?type=label_bin&paper=label_50x30&ids='))
            ->assertStatus(422)->assertJsonValidationErrors('ids');
        $this->actingAs($staf)->get($this->tenantUrl('labels/print?type=shipment&ids=1'))->assertNotFound();
    }

    #[Test]
    public function tc_tpl_12_isi_barcode_dan_qr(): void
    {
        $bin = LabelPayload::bin($this->binA);
        $this->assertSame('CKG-A-R01-L1-B01', $bin['code128']);
        $this->assertSame('CKG-A-R01-L1-B01', $bin['qr']);

        $baut = LabelPayload::item($this->baut);
        $this->assertSame('8991234567890', $baut['code128']);
        $this->assertSame('BAUT-M12', $baut['qr'], 'Tanpa qr_payload, QR = kode item.');

        $kabel = LabelPayload::item($this->kabel->forceFill(['qr_payload' => 'https://katalog.contoh/kabel']));
        $this->assertSame('KABEL-NYM', $kabel['code128'], 'Tanpa barcode, Code128 = kode item.');
        $this->assertSame('https://katalog.contoh/kabel', $kabel['qr']);

        $lot = LabelPayload::lot($this->lot);
        $this->assertSame('LOT-2609-A', $lot['code128']);
        $this->assertSame('SEMEN-PCC|LOT-2609-A', $lot['qr']);

        $potongan = LabelPayload::piece($this->potongan);
        $this->assertSame('P-000123', $potongan['code128']);
        $this->assertSame('P-000123', $potongan['qr']);
    }

    #[Test]
    public function tc_tpl_13_menu_dan_layar_label(): void
    {
        $admin = $this->makeUser('company_admin');
        $beranda = $this->actingAs($admin)->get($this->tenantUrl('/'))->assertOk();
        $beranda->assertSee(route('document-layout.edit'), false);
        $beranda->assertSee(route('labels.index'), false);

        $kepala = $this->makeUser('warehouse_head');
        $this->actingAs($kepala)->get($this->tenantUrl('/'))->assertOk()
            ->assertDontSee(route('document-layout.edit'), false)
            ->assertSee(route('labels.index'), false);

        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl('/'))->assertOk()
            ->assertDontSee(route('labels.index'), false);

        // Layar: pilih bin, kertas, salinan → tautan cetak memuat pilihan.
        Livewire::actingAs($kepala)->test(LabelPrint::class)
            ->assertSet('paper', PaperSize::LabelA4Grid->value)
            ->call('selectPage', [$this->binA->id, $this->binB->id])
            ->set('copies', 3)
            ->assertSee('Cetak 6 label')
            ->set('type', 'label_lot')
            ->assertSet('selected', [])
            ->assertSet('paper', PaperSize::Label50x30->value)
            ->assertSee('LOT-2609-A');
    }
}

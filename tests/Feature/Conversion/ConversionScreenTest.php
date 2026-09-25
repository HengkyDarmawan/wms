<?php

declare(strict_types=1);

namespace Tests\Feature\Conversion;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Conversion\Enums\ConversionStatus;
use App\Domain\Conversion\Livewire\ConversionDetail;
use App\Domain\Conversion\Livewire\ConversionForm;
use App\Domain\Conversion\Livewire\ConversionList;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Support\DocumentPrinter;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Waste\Enums\WasteDisposalStatus;
use App\Domain\Waste\Livewire\WasteDisposalDetail;
use App\Domain\Waste\Livewire\WasteDisposalForm;
use App\Domain\Waste\Livewire\WasteDisposalList;
use App\Domain\Waste\Models\WasteDisposal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalFixtures;
use Tests\Feature\Conversion\Concerns\ConversionFixtures;
use Tests\TenantTestCase;

/**
 * TC-CNV-11 s.d. TC-CNV-13 dan TC-WST-06 — izin layar & menu (BR-GEN-09,
 * BR-ACC-05), form CNV → detail → selesai → pembalik, BA waste lewat layar
 * dan penutupan dengan unggah bukti (POST), cetak Bukti Konversi & BA Waste
 * tanpa harga (D-07, A-160).
 */
class ConversionScreenTest extends TenantTestCase
{
    use ApprovalFixtures;
    use ConversionFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanKonversi();
    }

    private function kodeAlasan(ReasonContext $context): string
    {
        return (string) ReasonCode::query()->where('context', $context->value)->value('code');
    }

    #[Test]
    public function tc_cnv_11_izin_layar_cakupan_dan_menu(): void
    {
        $cnv = $this->cnvPotong();
        $staf = $this->staf();

        foreach (['conversions', 'conversions/create', 'conversions/'.$cnv->id, 'conversions/'.$cnv->id.'/edit', 'waste-disposals', 'waste-disposals/create'] as $url) {
            $this->actingAs($staf)->get($this->tenantUrl($url))->assertOk();
        }
        $this->actingAs($staf)->get($this->tenantUrl('conversions'))->assertSee($cnv->number);

        $auditor = $this->makeUser('external_auditor');
        $this->actingAs($auditor)->get($this->tenantUrl('conversions/'.$cnv->id))->assertOk();
        $this->actingAs($auditor)->get($this->tenantUrl('conversions/create'))->assertForbidden();
        $this->actingAs($auditor)->get($this->tenantUrl('waste-disposals/create'))->assertForbidden();
        $this->actingAs($this->makeUser('management'))->get($this->tenantUrl('conversions/create'))->assertForbidden();
        foreach (['internal_requester', 'driver', 'pr_follow_up'] as $role) {
            $this->actingAs($this->makeUser($role))->get($this->tenantUrl('conversions'))->assertForbidden();
            $this->actingAs($this->makeUser($role))->get($this->tenantUrl('waste-disposals'))->assertForbidden();
        }

        // BR-ACC-05: gudang lain tidak melihat CNV CKG.
        $stafBks = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->bks->id);
        $this->actingAs($stafBks)->get($this->tenantUrl('conversions/'.$cnv->id))->assertNotFound();
        $this->actingAs($stafBks)->get($this->tenantUrl('conversions'))->assertOk()->assertDontSee($cnv->number);

        // Menu & palet mengikuti izin.
        $this->actingAs($staf)->get($this->tenantUrl('/'))->assertOk()
            ->assertSee(route('conversions.index'))->assertSee(route('waste-disposals.index'))
            ->assertSee(__('Konversi baru'))->assertSee(__('BA waste baru'));
        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl('/'))->assertOk()
            ->assertDontSee(route('conversions.index'))->assertDontSee(route('waste-disposals.index'));

        // Laporan Material per proyek memuat kolom konversi & waste (A-161).
        $this->actingAs($this->makeUser('internal_auditor'))->get($this->tenantUrl('reports/material-per-proyek'))
            ->assertOk()->assertSee('Dikonversi')->assertSee('Waste didisposisi');
    }

    #[Test]
    public function tc_cnv_12_layar_form_detail_selesai_dan_pembalik(): void
    {
        $staf = $this->staf();
        $kunci = str_replace(':', '_', $this->kunciBatang());

        // Mode Potong (A-229): pilih batang, isi ukuran × jumlah; kerf & sisa dihitung otomatis.
        $form = Livewire::actingAs($staf)
            ->test(ConversionForm::class)
            ->assertOk()
            ->assertSet('form.conversion_type', 'cut')
            ->set('form.project_id', (string) $this->proyek->id)
            ->set('form.warehouse_id', (string) $this->gudang->id)
            ->assertSee($this->batang->piece_no)
            ->call('simpan')
            ->assertSet('ruleCode', 'BR-CNV-02')
            ->assertHasErrors('rencana.batang')
            ->set('batang', $kunci)
            ->assertSee(__('kerf per potongan'))
            ->set('potong.0.length', '3')
            ->set('potong.0.count', '3')
            ->assertSee(__('melebihi batang'))
            ->call('simpan')
            ->assertHasErrors('rencana.potong');

        $form->set('potong.0.length', '2.5')
            ->set('potong.0.count', '2')
            ->assertSee('6 M → 2 × 2,5 M + offcut 0,99 M + kerf 0,01 M')
            ->assertSee(__('OFFCUT'))
            ->assertSee(__('Neraca seimbang; siap disimpan.'))
            ->call('simpan')
            ->assertSet('ruleError', '')
            ->assertRedirect();

        $cnv = Conversion::query()->latest('id')->firstOrFail();
        $this->assertSame(ConversionStatus::Draft, $cnv->status);
        $this->assertSame(4, $cnv->outputs()->count(), '2 output + kerf + offcut.');
        $this->assertSame(0.99, (float) $cnv->outputs()->where('output_kind', 'offcut')->sole()->qty_base);
        $this->assertSame(0.01, (float) $cnv->outputs()->where('output_kind', 'kerf')->sole()->qty_base);

        Livewire::actingAs($staf)->test(ConversionList::class)->assertOk()->assertSee($cnv->number);

        // Ubah draf lewat form yang sama: batang & ukuran terisi kembali, sisa/kerf tetap otomatis.
        Livewire::actingAs($staf)->test(ConversionForm::class, ['conversion' => $cnv])
            ->assertSet('batang', $kunci)
            ->assertCount('potong', 1)
            ->assertSet('potong.0.count', '2')
            ->assertSee('6 M → 2 × 2,5 M + offcut 0,99 M + kerf 0,01 M');

        // Pindai nomor potongan memilih batang.
        Livewire::actingAs($staf)->test(ConversionForm::class)
            ->set('form.project_id', (string) $this->proyek->id)
            ->set('form.warehouse_id', (string) $this->gudang->id)
            ->set('kodePindai', 'TIDAK-ADA')->call('pindai')->assertHasErrors('kodePindai')
            ->set('kodePindai', mb_strtolower($this->batang->piece_no))->call('pindai')->assertHasNoErrors()->assertSet('batang', $kunci);

        Livewire::actingAs($staf)
            ->test(ConversionDetail::class, ['conversion' => $cnv])
            ->assertSee(__('Selesaikan konversi'))
            ->assertDontSee(__('Ajukan ke approval'))
            ->call('selesaikan')
            ->assertSet('ruleError', '')
            ->assertSee(__('Buat CNV pembalik'));

        $this->assertSame(ConversionStatus::Completed, $cnv->refresh()->status);

        Livewire::actingAs($staf)
            ->test(ConversionDetail::class, ['conversion' => $cnv])
            ->call('mintaDialog', 'balik')
            ->call('buatPembalik')
            ->assertHasErrors('form.reason')
            ->set('form.reason', $this->kodeAlasan(ReasonContext::Cancel))
            ->call('buatPembalik')
            ->assertSet('ruleError', '')
            ->assertRedirect();

        $balik = Conversion::query()->where('reversal_of_id', $cnv->id)->sole();

        Livewire::actingAs($staf)
            ->test(ConversionDetail::class, ['conversion' => $balik])
            ->assertSee(__('Selesaikan pembalikan'))
            ->assertDontSee(__('Ubah draf'))
            ->call('mintaDialog', 'batal')
            ->set('form.reason', $this->kodeAlasan(ReasonContext::Cancel))
            ->call('batalkan')
            ->assertSet('ruleError', '');

        $this->assertSame(ConversionStatus::Cancelled, $balik->refresh()->status);
    }

    #[Test]
    public function tc_cnv_14_ganti_kemasan_dan_rakit_dari_layar_lalu_simpan_dan_selesaikan(): void
    {
        $staf = $this->staf();
        $this->baut->forceFill(['is_cuttable' => true])->save();
        $kunciBaut = str_replace(':', '_', $this->kunciCnv($this->binA, $this->baut));
        $binWaste = $this->binSistem($this->gudang, BinType::Waste);

        // Ganti kemasan: 10 baut → 9 kabel (satuan sama), susut 1 otomatis waste; Simpan & selesaikan langsung menggerakkan stok.
        Livewire::actingAs($staf)
            ->test(ConversionForm::class)
            ->set('form.project_id', (string) $this->proyek->id)
            ->set('form.warehouse_id', (string) $this->gudang->id)
            ->set('form.conversion_type', 'repack')
            ->assertDontSee($this->batang->piece_no)
            ->set('cari', 'baut')
            ->assertSee('BAUT-M12')
            ->set('qty.'.$kunciBaut, '10')
            ->set('hasil.0.item_id', (string) $this->kabel->id)
            ->set('hasil.0.qty', '11')
            ->assertSee(__('melebihi total input'))
            ->set('hasil.0.qty', '9')
            ->assertSee('10 PCS → 9 PCS hasil + susut 1 PCS (waste)')
            ->assertSee(__('Simpan & selesaikan'))
            ->call('simpanDanSelesaikan')
            ->assertSet('ruleError', '')
            ->assertRedirect();

        $cnv = Conversion::query()->latest('id')->firstOrFail();
        $this->assertSame(ConversionStatus::Completed, $cnv->status);
        $this->assertSame(90.0, $this->saldo($this->binA, $this->baut));
        $this->assertSame(9.0, $this->saldo($this->binA, $this->kabel));
        $this->assertSame(1.0, $this->saldo($binWaste, $this->baut, StockStatus::Damaged));

        $this->actingAs($staf)->get($this->tenantUrl('conversions/'.$cnv->id))->assertOk()
            ->assertSee('10 PCS BAUT-M12 → 9 PCS KABEL-NYM + 1 PCS waste')
            ->assertSee(route('waste-disposals.create', ['warehouse' => $this->gudang->id, 'project' => $this->proyek->id]));

        // Tombol Buat BA Waste mengisi gudang & proyek di form WST.
        Livewire::withQueryParams(['warehouse' => $this->gudang->id, 'project' => $this->proyek->id])->actingAs($staf)
            ->test(WasteDisposalForm::class)
            ->assertSet('form.warehouse_id', (string) $this->gudang->id)
            ->assertSet('form.project_id', (string) $this->proyek->id);

        // Rakit tanpa neraca: 5 baut → 1 kabel; dengan aturan approval tombol menjadi Simpan & ajukan.
        $this->aturan(ApprovalDocumentType::Conversion, [$this->lapisUser($this->makeUser('warehouse_head'))]);

        Livewire::actingAs($staf)
            ->test(ConversionForm::class)
            ->set('form.project_id', (string) $this->proyek->id)
            ->set('form.warehouse_id', (string) $this->gudang->id)
            ->set('form.conversion_type', 'assemble')
            ->set('qty.'.$kunciBaut, '5')
            ->set('hasil.0.item_id', (string) $this->kabel->id)
            ->set('hasil.0.qty', '1')
            ->assertSee(__('tanpa neraca ukuran'))
            ->assertSee(__('Simpan & ajukan'))
            ->call('simpanDanSelesaikan')
            ->assertSet('ruleError', '')
            ->assertRedirect();

        $rakit = Conversion::query()->latest('id')->firstOrFail();
        $this->assertSame(ConversionStatus::PendingApproval, $rakit->status);
        $this->assertSame(90.0, $this->saldo($this->binA, $this->baut), 'Belum bergerak sebelum disetujui.');
    }

    #[Test]
    public function tc_cnv_13_cetak_bukti_konversi_dan_ba_waste(): void
    {
        $staf = $this->staf();
        $cnv = $this->selesai($this->cnv(
            [['key' => $this->kunciBatang(), 'qty_base' => 6]],
            [['kind' => 'output', 'item_id' => $this->pipa->id, 'qty_base' => 5.695], ['kind' => 'offcut', 'qty_base' => 0.3], ['kind' => 'kerf', 'qty_base' => 0.005]],
        ), $staf);

        $html = app(DocumentPrinter::class)->view(DocumentTemplateType::Conversion, $cnv)->render();
        $this->assertStringContainsString($cnv->number, $html);
        $this->assertStringContainsString('Bukti Konversi Material', $html);
        $this->assertStringContainsString('PIPA-PVC', $html);
        $this->assertStringContainsString('Dikerjakan oleh', $html);
        $this->assertStringContainsString($this->batang->piece_no, $html);
        $teks = (string) preg_replace('#data:image/[a-z]+;base64,[A-Za-z0-9+/=]+#', '', $html);
        foreach (['Rp', 'harga', 'Harga'] as $kata) {
            $this->assertStringNotContainsString($kata, $teks, 'D-07.');
        }

        $this->actingAs($staf)->get($this->tenantUrl('print/conversion/'.$cnv->id))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl('print/conversion/'.$cnv->id))->assertForbidden();

        $binWaste = $this->binSistem($this->gudang, BinType::Waste);
        $wst = $this->wst([['key' => $this->kunciWaste($binWaste, $this->pipa, 'damaged', ['piece_id' => $cnv->outputs()->where('auto_waste', true)->sole()->new_piece_id]), 'qty_base' => 0.3]]);
        $html = app(DocumentPrinter::class)->view(DocumentTemplateType::WasteDisposal, $wst)->render();
        $this->assertStringContainsString('BA Waste', $html);
        $this->assertStringContainsString('Saksi', $html);
        $this->actingAs($staf)->get($this->tenantUrl('print/waste-disposal/'.$wst->id))->assertOk();
    }

    #[Test]
    public function tc_wst_06_layar_ba_waste_dan_tutup_dengan_unggah_bukti(): void
    {
        Storage::fake('local');

        $binWaste = $this->binSistem($this->gudang, BinType::Waste);
        app(StockLedger::class)->post(new MovementRequest(item: $this->baut, qtyBase: 5, toBinId: $binWaste->id, stockStatus: StockStatus::Damaged));
        $staf = $this->staf();
        $kunci = str_replace(':', '_', $this->kunciWaste($binWaste, $this->baut));

        Livewire::actingAs($staf)
            ->test(WasteDisposalForm::class)
            ->assertOk()
            ->set('form.warehouse_id', (string) $this->gudang->id)
            ->assertSee('BAUT-M12')
            ->set('form.project_id', (string) $this->proyek->id)
            ->set('form.disposition', 'disposed')
            ->set('qty.'.$kunci, '9')
            ->call('simpan')
            ->assertSet('ruleCode', 'BR-STK-06')
            ->set('qty.'.$kunci, '5')
            ->set('reason.'.$kunci, $this->kodeAlasan(ReasonContext::Waste))
            ->call('simpan')
            ->assertSet('ruleError', '')
            ->assertRedirect();

        $wst = WasteDisposal::query()->latest('id')->firstOrFail();
        $this->assertSame(WasteDisposalStatus::Approved, $wst->status);
        $this->assertNotNull($wst->lines()->sole()->reason_code_id);

        Livewire::actingAs($staf)->test(WasteDisposalList::class)->assertOk()->assertSee($wst->number);
        Livewire::actingAs($staf)->test(WasteDisposalDetail::class, ['wasteDisposal' => $wst])
            ->assertSee(__('Tutup BA waste'))
            ->assertSee(route('waste-disposals.close', $wst));

        // POST tanpa bukti ditolak; dengan foto ditutup; foto dialirkan lewat route berizin.
        $this->actingAs($staf)->post($this->tenantUrl('waste-disposals/'.$wst->id.'/close'), [])
            ->assertRedirect()->assertSessionHasErrors('evidence');
        $this->assertSame(WasteDisposalStatus::Approved, $wst->refresh()->status);

        $this->actingAs($this->makeUser('external_auditor'))
            ->post($this->tenantUrl('waste-disposals/'.$wst->id.'/close'), ['evidence_note' => 'x'])->assertForbidden();

        $this->actingAs($staf)->post($this->tenantUrl('waste-disposals/'.$wst->id.'/close'), [
            'evidence_photo' => UploadedFile::fake()->image('ba.png'),
            'evidence_note' => 'BA/WST/UJI',
        ])->assertRedirect(route('waste-disposals.show', $wst));

        $wst->refresh();
        $this->assertSame(WasteDisposalStatus::Closed, $wst->status);
        $this->assertSame(0.0, $this->saldo($binWaste, $this->baut, StockStatus::Damaged));
        $this->actingAs($staf)->get($this->tenantUrl('waste-disposals/'.$wst->id.'/evidence'))->assertOk();
        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl('waste-disposals/'.$wst->id.'/evidence'))->assertForbidden();
        $this->actingAs($staf)->get($this->tenantUrl('waste-disposals/'.$wst->id))->assertOk()->assertSee(__('foto berita acara'));
    }
}

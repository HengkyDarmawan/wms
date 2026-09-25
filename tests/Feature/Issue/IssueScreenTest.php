<?php

declare(strict_types=1);

namespace Tests\Feature\Issue;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Issue\Actions\CancelMaterialIssue;
use App\Domain\Issue\Actions\CreateMaterialIssue;
use App\Domain\Issue\Enums\MaterialIssueStatus;
use App\Domain\Issue\Livewire\IssueDetail;
use App\Domain\Issue\Livewire\IssueForm;
use App\Domain\Issue\Livewire\IssueList;
use App\Domain\Issue\Models\MaterialIssue;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Shared\Attachments\Enums\AttachmentKind;
use App\Domain\Shared\Attachments\Models\Attachment;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Support\DocumentPrinter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Issue\Concerns\IssueFixtures;
use Tests\TenantTestCase;

/**
 * TC-ISU-14 s.d. TC-ISU-17 — izin layar & menu (BR-GEN-09, BR-ACC-05), layar
 * form → detail → konfirmasi → pembalik → approval, ubah draf & dialog batal,
 * cetak Bukti Pemakaian Material (18 §5.1, D-07, A-152).
 */
class IssueScreenTest extends TenantTestCase
{
    use IssueFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPemakaian();
        $this->stok($this->binKrw1, $this->baut, 20);
    }

    private function kodeAlasan(ReasonContext $context): string
    {
        return (string) ReasonCode::query()->where('context', $context->value)->value('code');
    }

    #[Test]
    public function tc_isu_14_izin_layar_cakupan_dan_menu(): void
    {
        $isu = $this->isu([['key' => $this->kunciIsu($this->binKrw1, $this->baut), 'qty_base' => 2]]);

        $staf = $this->stafSite();
        foreach (['issues', 'issues/create', 'issues/'.$isu->id] as $url) {
            $this->actingAs($staf)->get($this->tenantUrl($url))->assertOk();
        }
        $this->actingAs($staf)->get($this->tenantUrl('issues'))->assertSee($isu->number);
        $this->actingAs($staf)->get($this->tenantUrl('issues/'.$isu->id.'/edit'))->assertOk();

        $pemohon = $this->makeUser('internal_requester', ScopeType::Project, $this->proyek->id);
        $this->actingAs($pemohon)->get($this->tenantUrl('issues'))->assertOk()->assertSee($isu->number);
        $this->actingAs($pemohon)->get($this->tenantUrl('issues/create'))->assertOk();
        $this->actingAs($pemohon)->get($this->tenantUrl('issues/'.$isu->id.'/edit'))->assertForbidden();

        $auditor = $this->makeUser('internal_auditor');
        $this->actingAs($auditor)->get($this->tenantUrl('issues/'.$isu->id))->assertOk();
        $this->actingAs($auditor)->get($this->tenantUrl('issues/create'))->assertForbidden();
        $this->actingAs($this->makeUser('management'))->get($this->tenantUrl('issues/create'))->assertForbidden();
        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl('issues'))->assertForbidden();
        $this->actingAs($this->makeUser('pr_follow_up'))->get($this->tenantUrl('issues'))->assertForbidden();

        // BR-ACC-05: staf gudang lain dan pemohon proyek lain tidak melihat ISU ini.
        $stafCkg = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->gudang->id);
        $this->actingAs($stafCkg)->get($this->tenantUrl('issues/'.$isu->id))->assertNotFound();
        $this->actingAs($stafCkg)->get($this->tenantUrl('issues'))->assertOk()->assertDontSee($isu->number);
        $pemohonLain = $this->makeUser('internal_requester', ScopeType::Project, $this->makeProject()->id);
        $this->actingAs($pemohonLain)->get($this->tenantUrl('issues/'.$isu->id))->assertNotFound();

        // Klien tidak punya area pemakaian (dipulangkan ke portal).
        $klien = $this->makeUser('client_user', ScopeType::Project, $this->proyek->id, ['client_id' => $this->proyek->client_id]);
        $this->actingAs($klien)->get($this->tenantUrl('issues'))->assertRedirect();

        // Menu & palet mengikuti izin.
        $this->actingAs($staf)->get($this->tenantUrl('/'))->assertOk()
            ->assertSee(__('Pemakaian material'))->assertSee(route('issues.index'))->assertSee(__('Pemakaian baru'));
        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl('/'))->assertOk()->assertDontSee(route('issues.index'));
        $this->actingAs($auditor)->get($this->tenantUrl('reports'))->assertOk()->assertSee(__('Material per proyek'));
        $this->actingAs($auditor)->get($this->tenantUrl('reports/material-per-proyek'))->assertOk()->assertSee('Terpakai');
        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl('reports/material-per-proyek'))->assertForbidden();
    }

    #[Test]
    public function tc_isu_18_pindai_mengisi_jumlah_stok_gudang_site(): void
    {
        $this->baut->forceFill(['barcode' => '8991112223334'])->save();
        $lot = Lot::create(['item_id' => $this->semen->id, 'lot_no' => 'LOT-S1', 'expiry_date' => now()->addMonths(6)->toDateString(), 'received_at' => now()->toDateString()]);
        $this->stok($this->binKrw1, $this->semen, 50, ['lot_id' => $lot->id]);
        $potong = Piece::create(['item_id' => $this->pipa->id, 'piece_no' => 'P-ISU-9', 'length' => 6, 'is_offcut' => false]);
        $this->stok($this->binKrw1, $this->pipa, 6, ['piece_id' => $potong->id]);

        $kBaut = str_replace(':', '_', $this->kunciIsu($this->binKrw1, $this->baut));
        $kSemen = str_replace(':', '_', $this->kunciIsu($this->binKrw1, $this->semen, ['lot_id' => $lot->id]));
        $kPipa = str_replace(':', '_', $this->kunciIsu($this->binKrw1, $this->pipa, ['piece_id' => $potong->id]));

        Livewire::actingAs($this->stafSite())
            ->test(IssueForm::class)
            ->set('form.project_id', (string) $this->proyek->id)
            ->assertSee(__('Pindai barang'))
            ->set('kodePindai', 'TIDAK-ADA')->call('pindai')->assertHasErrors('kodePindai')
            ->set('kodePindai', 'baut-m12')->call('pindai')->assertHasNoErrors()->assertSet('qty.'.$kBaut, '1')->assertSet('sorot', $kBaut)
            ->set('kodePindai', '8991112223334')->call('pindai')->assertSet('qty.'.$kBaut, '2')
            ->set('kodePindai', 'SEMEN-PCC')->call('pindai')->assertHasErrors('kodePindai')
            ->set('kodePindai', 'semen-pcc|lot-s1')->call('pindai')->assertHasNoErrors()->assertSet('qty.'.$kSemen, '1')
            ->set('kodePindai', 'P-ISU-9')->call('pindai')->assertSet('qty.'.$kPipa, '6')
            ->set('kodePindai', 'P-ISU-9')->call('pindai')->assertHasErrors('kodePindai')
            ->call('simpan')
            ->assertSet('ruleError', '')
            ->assertRedirect();

        $isu = MaterialIssue::query()->latest('id')->firstOrFail();
        $this->assertSame([2.0, 1.0, 6.0], $isu->lines()->get()->sortBy(fn ($l) => [$this->baut->id => 0, $this->semen->id => 1, $this->pipa->id => 2][$l->item_id])->pluck('qty_base')->map(fn ($v) => (float) $v)->values()->all());
    }

    #[Test]
    public function tc_isu_15_layar_form_detail_konfirmasi_pembalik_dan_approval(): void
    {
        $staf = $this->stafSite();
        $kunci = str_replace(':', '_', $this->kunciIsu($this->binKrw1, $this->baut));

        Livewire::actingAs($staf)
            ->test(IssueForm::class)
            ->assertOk()
            ->set('form.project_id', (string) $this->proyek->id)
            ->assertSet('form.warehouse_id', (string) $this->krw1->id)
            ->assertSee('KRW1-A-R01-L1-B01')
            ->set('qty.'.$kunci, '25')
            ->call('simpan')
            ->assertSet('ruleCode', 'BR-STK-06')
            ->set('qty.'.$kunci, '12')
            ->set('note.'.$kunci, 'Tiang pagar')
            ->call('simpan')
            ->assertSet('ruleError', '')
            ->assertRedirect();

        $isu = MaterialIssue::query()->latest('id')->firstOrFail();
        $this->assertSame(MaterialIssueStatus::Draft, $isu->status);
        $this->assertSame('Tiang pagar', $isu->lines()->sole()->work_note);

        Livewire::actingAs($staf)->test(IssueList::class)->assertOk()->assertSee($isu->number);

        Livewire::actingAs($staf)
            ->test(IssueDetail::class, ['materialIssue' => $isu])
            ->assertSee(__('Konfirmasi pemakaian'))
            ->assertSee(__('Ubah draf'))
            ->call('konfirmasi')
            ->assertSet('ruleError', '')
            ->assertSee(__('Buat ISU pembalik'));

        $this->assertSame(MaterialIssueStatus::Confirmed, $isu->refresh()->status);
        $this->assertSame(8.0, $this->saldo($this->binKrw1, $this->baut));

        // Pembalik lewat dialog: Alasan wajib, baris asal terpilih bawaan.
        Livewire::actingAs($staf)
            ->test(IssueDetail::class, ['materialIssue' => $isu])
            ->call('mintaDialog', 'balik')
            ->assertSet('balik.'.$isu->lines()->sole()->id, true)
            ->call('buatPembalik')
            ->assertHasErrors('form.reason')
            ->set('form.reason', $this->kodeAlasan(ReasonContext::Cancel))
            ->call('buatPembalik')
            ->assertSet('ruleError', '')
            ->assertRedirect();

        $balik = MaterialIssue::query()->where('reversal_of_id', $isu->id)->sole();
        $manajemen = $this->makeUser('management');
        $pengaju = $this->stafSite();

        Livewire::actingAs($pengaju)
            ->test(IssueDetail::class, ['materialIssue' => $balik])
            ->assertSee(__('Ajukan pembalikan'))
            ->call('konfirmasi')
            ->assertSet('ruleError', '')
            ->assertSee(__('Menunggu approval'))
            ->assertDontSee(__('Setujui'));

        Livewire::actingAs($manajemen)
            ->test(IssueDetail::class, ['materialIssue' => $balik])
            ->assertSee(__('Riwayat approval'))
            ->call('setujui')
            ->assertSet('ruleError', '');

        $this->assertSame(MaterialIssueStatus::Confirmed, $balik->refresh()->status);
        $this->assertSame(20.0, $this->saldo($this->binKrw1, $this->baut));
    }

    #[Test]
    public function tc_isu_16_ubah_draf_dialog_batal_dan_tolak(): void
    {
        $pemohon = $this->makeUser('internal_requester', ScopeType::Project, $this->proyek->id);
        $kunci = $this->kunciIsu($this->binKrw1, $this->baut);
        $draf = $this->isu([['key' => $kunci, 'qty_base' => 3]], [], $pemohon);

        // Pemohon melihat dua Gudang Site proyek; draf diubah lewat form yang sama.
        Livewire::actingAs($pemohon)
            ->test(IssueForm::class, ['materialIssue' => $draf])
            ->assertSet('form.warehouse_id', (string) $this->krw1->id)
            ->assertSet('qty.'.str_replace(':', '_', $kunci), '3')
            ->set('qty.'.str_replace(':', '_', $kunci), '4')
            ->call('simpan')
            ->assertRedirect();
        $this->assertSame(4.0, (float) $draf->lines()->sole()->qty_base);

        Livewire::actingAs($pemohon)
            ->test(IssueDetail::class, ['materialIssue' => $draf])
            ->assertDontSee(__('Konfirmasi pemakaian'))
            ->call('mintaDialog', 'batal')
            ->call('batalkan')
            ->assertHasErrors('form.reason')
            ->set('form.reason', $this->kodeAlasan(ReasonContext::Cancel))
            ->call('batalkan')
            ->assertSet('ruleError', '');
        $this->assertSame(MaterialIssueStatus::Cancelled, $draf->refresh()->status);

        // Tolak pembalik lewat dialog: tetap Draf dengan alasan penolakan.
        $staf = $this->stafSite();
        $isu = $this->konfirmasi($this->isu([['key' => $kunci, 'qty_base' => 5]], [], $staf), $staf);
        $manajemen = $this->makeUser('management');
        $balik = app(CreateMaterialIssue::class)->reverse($isu, [], $this->alasan(ReasonContext::Cancel), null, $staf);
        $balik = $this->konfirmasi($balik, $staf);

        Livewire::actingAs($manajemen)
            ->test(IssueDetail::class, ['materialIssue' => $balik])
            ->call('mintaDialog', 'tolak')
            ->call('tolak')
            ->assertHasErrors('form.reason')
            ->set('form.reason', $this->kodeAlasan(ReasonContext::Reject))
            ->call('tolak')
            ->assertSet('ruleError', '')
            ->assertSee(__('ajukan ulang atau batalkan'));
        $this->assertSame(MaterialIssueStatus::Draft, $balik->refresh()->status);
        $this->assertNotNull($balik->reject_reason_id);
    }

    #[Test]
    public function tc_isu_17_cetak_bukti_pemakaian_material(): void
    {
        $staf = $this->stafSite();
        $isu = $this->konfirmasi($this->isu([['key' => $this->kunciIsu($this->binKrw1, $this->baut), 'qty_base' => 7, 'work_note' => 'Rangka bekisting']], [], $staf), $staf);

        $html = app(DocumentPrinter::class)->view(DocumentTemplateType::MaterialIssue, $isu)->render();
        $this->assertStringContainsString($isu->number, $html);
        $this->assertStringContainsString('Bukti Pemakaian Material', $html);
        $this->assertStringContainsString('BAUT-M12', $html);
        $this->assertStringContainsString('Rangka bekisting', $html);
        $this->assertStringContainsString('PIC proyek', $html);
        // Gambar QR/logo base64 dibuang dulu: urutan acaknya bisa memuat "Rp".
        $teks = (string) preg_replace('#data:image/[a-z]+;base64,[A-Za-z0-9+/=]+#', '', $html);
        foreach (['Rp', 'harga', 'Harga'] as $kata) {
            $this->assertStringNotContainsString($kata, $teks, 'D-07.');
        }

        $this->actingAs($staf)->get($this->tenantUrl('print/material-issue/'.$isu->id))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->gudang->id))
            ->get($this->tenantUrl('print/material-issue/'.$isu->id))->assertNotFound();
        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl('print/material-issue/'.$isu->id))->assertForbidden();

        $draf = $this->isu([['key' => $this->kunciIsu($this->binKrw1, $this->baut), 'qty_base' => 1]], [], $staf);
        $draf = app(CancelMaterialIssue::class)->handle($draf, $this->alasan(ReasonContext::Cancel), null, $staf);
        $this->assertStringContainsString('DIBATALKAN', app(DocumentPrinter::class)->view(DocumentTemplateType::MaterialIssue, $draf)->render());

        Livewire::actingAs($staf)->test(IssueDetail::class, ['materialIssue' => $isu])
            ->assertSee(route('print.document', ['type' => 'material-issue', 'id' => $isu->id]));
    }

    #[Test]
    public function tc_isu_18_foto_pemakaian_disimpan_sebagai_lampiran_berizin(): void
    {
        Storage::fake('local');
        $staf = $this->stafSite();
        $isu = $this->konfirmasi($this->isu([['key' => $this->kunciIsu($this->binKrw1, $this->baut), 'qty_base' => 2]], [], $staf), $staf);

        $this->actingAs($staf)->post($this->tenantUrl('issues/'.$isu->id.'/photos'), ['photo' => UploadedFile::fake()->image('pasang.jpg', 400, 300)])
            ->assertRedirect(route('issues.show', $isu, false))->assertSessionHasNoErrors();
        $foto = Attachment::query()->for($isu)->sole();
        $this->assertSame(AttachmentKind::Photo, $foto->kind);
        $this->assertSame('pasang.jpg', $foto->original_name);
        Storage::disk('local')->assertExists($foto->path);

        // Dibuka lewat route berizin; dokumen di luar cakupan = 404 (BR-ACC-05).
        $this->actingAs($staf)->get($this->tenantUrl('attachments/'.$foto->id))->assertOk();
        $stafCkg = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->gudang->id);
        $this->actingAs($stafCkg)->get($this->tenantUrl('attachments/'.$foto->id))->assertNotFound();

        // Bukan gambar ditolak; tanpa izin buat ISU tidak bisa mengunggah.
        $this->actingAs($staf)->post($this->tenantUrl('issues/'.$isu->id.'/photos'), ['photo' => UploadedFile::fake()->create('daftar.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('photo');
        $this->actingAs($this->makeUser('management'))->post($this->tenantUrl('issues/'.$isu->id.'/photos'), ['photo' => UploadedFile::fake()->image('x.jpg')])
            ->assertForbidden();
        $this->assertSame(1, Attachment::query()->for($isu)->count());

        Livewire::actingAs($staf)->test(IssueDetail::class, ['materialIssue' => $isu])
            ->assertSee(__('Foto pemakaian'))->assertSee(route('attachments.show', $foto));
    }
}

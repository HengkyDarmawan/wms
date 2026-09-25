<?php

declare(strict_types=1);

namespace Tests\Feature\Count;

use App\Domain\Count\Actions\ApproveStockCount;
use App\Domain\Count\Actions\ReconcileStockCount;
use App\Domain\Count\Enums\StockCountStatus;
use App\Domain\Count\Livewire\CountEntry;
use App\Domain\Count\Livewire\MyCountTasks;
use App\Domain\Count\Livewire\StockCountDetail;
use App\Domain\Count\Livewire\StockCountForm;
use App\Domain\Count\Livewire\StockCountList;
use App\Domain\Count\Models\CountAssignment;
use App\Domain\Count\Models\StockCount;
use App\Domain\Shared\Attachments\Enums\AttachmentKind;
use App\Domain\Shared\Attachments\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Count\Concerns\CountFixtures;
use Tests\TenantTestCase;

/**
 * TC-OPN-18 dan TC-OPN-19 — layar opname: izin halaman (BR-GEN-09), form,
 * detail (mulai, penugasan, rekonsiliasi, setujui, batal), halaman hitung
 * ramah HP yang buta angka sistem, laporan PDF, menu dan palet.
 */
class CountScreenTest extends TenantTestCase
{
    use CountFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanOpname();
    }

    #[Test]
    public function tc_opn_18_izin_halaman_opname(): void
    {
        $sesi = $this->sesiBerjalan();
        $milik = CountAssignment::query()->where('stock_count_id', $sesi->id)->where('counter_user_id', $this->staf1->id)->firstOrFail();
        $lain = CountAssignment::query()->where('stock_count_id', $sesi->id)->where('counter_user_id', $this->staf2->id)->firstOrFail();

        foreach (['counts', 'counts/'.$sesi->id, 'count-tasks', 'count-tasks/'.$milik->id] as $url) {
            $this->actingAs($this->staf1)->get($this->tenantUrl($url))->assertOk();
        }

        $this->actingAs($this->staf1)->get($this->tenantUrl('counts/create'))->assertForbidden();
        $this->actingAs($this->staf1)->get($this->tenantUrl('count-tasks/'.$lain->id))->assertForbidden();
        $this->actingAs($this->kepala)->get($this->tenantUrl('counts/create'))->assertOk();
        $this->actingAs($this->auditor)->get($this->tenantUrl('counts/create'))->assertOk();

        $driver = $this->makeUser('driver');
        foreach (['counts', 'counts/create', 'count-tasks', 'counts/'.$sesi->id] as $url) {
            $this->actingAs($driver)->get($this->tenantUrl($url))->assertForbidden();
        }

        // Laporan PDF baru terbit setelah sesi disetujui (Katalog §2.13).
        $this->actingAs($this->kepala)->get($this->tenantUrl('counts/'.$sesi->id.'/report'))->assertForbidden();

        $this->hitungPutaran($sesi, 1);
        app(ReconcileStockCount::class)->handle($sesi, $this->auditor);
        app(ApproveStockCount::class)->approve($sesi->refresh(), $this->kepala);

        $pdf = $this->actingAs($this->staf1)->get($this->tenantUrl('counts/'.$sesi->id.'/report'));
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));
    }

    #[Test]
    public function tc_opn_18b_form_dan_detail_menjalankan_sesi(): void
    {
        Livewire::actingAs($this->kepala)
            ->test(StockCountForm::class)
            ->assertOk()
            ->set('form.count_type', 'monthly')
            ->set('form.warehouse_ids', [(string) $this->gudang->id])
            ->set('form.bin_ids', [(string) $this->binA->id])
            ->set('form.team_user_ids', [(string) $this->staf1->id, (string) $this->staf2->id])
            ->call('simpan')
            ->assertSet('ruleError', '')
            ->assertRedirect();

        $sesi = StockCount::query()->latest('id')->firstOrFail();

        Livewire::actingAs($this->kepala)
            ->test(StockCountDetail::class, ['stockCount' => $sesi])
            ->assertOk()
            ->call('mulai')
            ->assertSet('ruleError', '')
            ->assertSee('CKG-A-R01-L1-B01');

        $this->assertSame(StockCountStatus::InProgress, $sesi->refresh()->status);

        // Penghitung yang masih menghitung tidak melihat angka sistem.
        Livewire::actingAs($this->staf1)
            ->test(StockCountDetail::class, ['stockCount' => $sesi])
            ->assertSee(__('Hitung buta'))
            ->assertDontSee('100,00');

        Livewire::actingAs($this->kepala)
            ->test(StockCountDetail::class, ['stockCount' => $sesi])
            ->assertSee('100,00');

        // Penugasan diganti Kepala Gudang lewat layar.
        $tugas = CountAssignment::query()->where('stock_count_id', $sesi->id)->sole();
        Livewire::actingAs($this->kepala)
            ->test(StockCountDetail::class, ['stockCount' => $sesi])
            ->set('penghitung.'.$tugas->id, (string) $this->staf2->id)
            ->call('tugaskan', $tugas->id)
            ->assertSet('ruleError', '');
        $this->assertSame($this->staf2->id, (int) $tugas->refresh()->counter_user_id);

        // Halaman hitung ramah HP: daftar tugas, isi, selesai.
        Livewire::actingAs($this->staf2)->test(MyCountTasks::class)->assertSee('CKG-A-R01-L1-B01');

        $baut = $this->baris($sesi, $this->baut);
        $pipa = $this->baris($sesi, $this->pipa);

        Livewire::actingAs($this->staf2)
            ->test(CountEntry::class, ['countAssignment' => $tugas])
            ->assertOk()
            ->assertSee('BAUT-OPN')
            ->assertSee('P-OPN-1')
            ->assertDontSee('100,00')
            ->set('qty.'.$baut->id, '80')
            ->call('selesai')
            ->assertSet('ruleCode', 'BR-OPN-05')
            ->set('qty.'.$pipa->id, '1')
            ->call('selesai')
            ->assertSet('ruleError', '')
            ->assertRedirect();

        $this->assertTrue($tugas->refresh()->isDone());

        // Rekonsiliasi (akar masalah wajib untuk selisih besar) lalu approval.
        $detail = Livewire::actingAs($this->auditor)
            ->test(StockCountDetail::class, ['stockCount' => $sesi])
            ->call('rekonsiliasi')
            ->assertSet('ruleCode', 'BR-OPN-07')
            ->set('akar.'.$baut->id.'.root_cause', 'mispick')
            ->call('simpanAkar', $baut->id)
            ->call('rekonsiliasi')
            ->assertSet('ruleError', '');

        $this->assertSame(StockCountStatus::Reconciling, $sesi->refresh()->status);

        Livewire::actingAs($this->kepala)
            ->test(StockCountDetail::class, ['stockCount' => $sesi])
            ->call('setujui')
            ->assertSet('ruleError', '');

        $this->assertSame(StockCountStatus::Closed, $sesi->refresh()->status);
        $this->assertSame(80.0, $this->saldoBin($this->binA, $this->baut));
    }

    #[Test]
    public function tc_opn_18c_batal_dan_tolak_lewat_dialog(): void
    {
        $sesi = $this->sesi();

        Livewire::actingAs($this->kepala)
            ->test(StockCountDetail::class, ['stockCount' => $sesi])
            ->call('mintaDialog', 'batal')
            ->call('batalkan')
            ->assertHasErrors(['form.reason'])
            ->set('form.reason', 'NOT_NEEDED')
            ->call('batalkan')
            ->assertSet('ruleError', '');

        $this->assertSame(StockCountStatus::Cancelled, $sesi->refresh()->status);

        $jalan = $this->hitungPutaran($this->sesiBerjalan(), 1, []);
        app(ReconcileStockCount::class)->handle($jalan, $this->auditor);

        Livewire::actingAs($this->kepala)
            ->test(StockCountDetail::class, ['stockCount' => $jalan])
            ->call('mintaDialog', 'tolak')
            ->set('form.reason', 'OTHER')
            ->call('tolak')
            ->assertSet('ruleError', '');

        $this->assertSame(StockCountStatus::Reconciling, $jalan->refresh()->status);
        $this->assertNotNull($jalan->reject_reason_id);

        // Staf tidak bisa membatalkan atau menyetujui.
        Livewire::actingAs($this->staf1)
            ->test(StockCountDetail::class, ['stockCount' => $jalan])
            ->call('setujui')
            ->assertForbidden();
    }

    #[Test]
    public function tc_opn_19_menu_dan_palet(): void
    {
        $this->actingAs($this->kepala)->get($this->tenantUrl('/'))->assertOk()
            ->assertSee(__('Opname & penyesuaian'))
            ->assertSee(route('counts.index'), false)
            ->assertSee(route('adjustments.index'), false)
            ->assertSee(__('Sesi opname baru'));

        $this->actingAs($this->staf1)->get($this->tenantUrl('/'))->assertOk()
            ->assertSee(route('count-tasks.index'), false)
            ->assertDontSee(__('Sesi opname baru'));

        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl('/'))->assertOk()
            ->assertDontSee(__('Opname & penyesuaian'));

        Livewire::actingAs($this->kepala)->test(StockCountList::class)->assertOk()->assertSee(__('Belum ada sesi opname.'));
    }

    #[Test]
    public function tc_opn_20_laporan_pdf_diarsipkan_saat_sesi_ditutup(): void
    {
        Storage::fake('local');
        $sesi = $this->sesiBerjalan();
        $this->hitungPutaran($sesi, 1);
        app(ReconcileStockCount::class)->handle($sesi, $this->auditor);
        app(ApproveStockCount::class)->approve($sesi->refresh(), $this->kepala);

        $sesi->refresh();
        $this->assertSame(StockCountStatus::Closed, $sesi->status);
        $arsip = Attachment::query()->findOrFail($sesi->report_attachment_id);
        $this->assertSame(AttachmentKind::Report, $arsip->kind);
        $this->assertSame('application/pdf', $arsip->mime);
        Storage::disk('local')->assertExists($arsip->path);

        // Unduhan berikutnya memakai arsip, bukan PDF baru.
        $unduh = $this->actingAs($this->staf1)->get($this->tenantUrl('counts/'.$sesi->id.'/report'));
        $unduh->assertOk()->assertDownload(str_replace('/', '-', $sesi->number).'.pdf');
        $this->assertSame(Storage::disk('local')->get($arsip->path), $unduh->streamedContent());
        $this->actingAs($this->staf1)->get($this->tenantUrl('attachments/'.$arsip->id))->assertOk();
    }
}

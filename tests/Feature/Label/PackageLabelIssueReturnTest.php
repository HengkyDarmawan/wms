<?php

declare(strict_types=1);

namespace Tests\Feature\Label;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Issue\Actions\ApproveMaterialIssue;
use App\Domain\Issue\Actions\CreateMaterialIssue;
use App\Domain\Issue\Livewire\IssueForm;
use App\Domain\Issue\Models\MaterialIssue;
use App\Domain\Label\Enums\PackageLabelStatus;
use App\Domain\Label\Models\PackageLabel;
use App\Domain\Label\Support\PackageLabelLedger;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\FeatureSetting;
use App\Domain\Receipt\Actions\CompleteGoodsReceipt;
use App\Domain\Receipt\Actions\CompletePutaway;
use App\Domain\Shared\Reports\Definitions\VendorProblemReport;
use App\Domain\Shipment\Actions\ProcessPickTask;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Warehouse\Enums\BinType;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Issue\Concerns\IssueFixtures;
use Tests\TenantTestCase;

/**
 * TC-ISU-20, TC-ISU-21, TC-RET-23, TC-RPT-12 — label kemasan di Gudang Site:
 * dipindai saat dipakai (A-299), dibuka lagi oleh ISU pembalik (A-300), retur
 * penjualan membuka label lalu pilah rusak membatalkannya (A-301), dan
 * laporan barang bermasalah per vendor (A-303).
 */
class PackageLabelIssueReturnTest extends TenantTestCase
{
    use IssueFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPemakaian();
        FeatureSetting::seed(['qc' => false], overwrite: true);
    }

    /** PCK uji memindai label kemasan Di gudang item baris (FIFO kode) sebelum selesai. */
    protected function jalankanPck(PickTask $pck): PickTask
    {
        $staf = $this->makeUser('warehouse_staff');
        $aksi = app(ProcessPickTask::class);
        $pck = $aksi->start($pck, $staf);

        foreach ($pck->lines()->with('pickTask', 'item')->get() as $baris) {
            $butuh = (float) $baris->qty_allocated;

            foreach (PackageLabel::query()->inStock()->where('warehouse_id', $pck->warehouse_id)->where('item_id', $baris->item_id)
                ->where('qty_remaining', '>', 0)->orderBy('id')->get() as $label) {
                if ($butuh <= 0) {
                    break;
                }

                $ambil = min($butuh, (float) $label->qty_remaining);
                $baris = $aksi->claimLabel($baris->load('pickTask', 'item'), $label, $ambil, $staf);
                $butuh -= $ambil;
            }

            if ($baris->scanned_at === null) {
                $aksi->recordLine($baris->load('pickTask', 'item'), (float) $baris->qty_allocated, null, null, null, $staf);
            }
        }

        return $aksi->complete($pck->refresh(), $staf);
    }

    /** Kabel 24 dari vendor, 2 dus × 12, di-put-away ke bin A CKG. */
    private function kabelBerlabel(): void
    {
        $grn = $this->grnDiterima([['item_id' => $this->kabel->id, 'qty_received' => 24]]);
        $grn = app(CompleteGoodsReceipt::class)->handle($grn->refresh(), $this->makeUser('warehouse_head'), [
            $grn->lines()->sole()->id => ['packages' => 2, 'per_package' => 12],
        ]);
        app(CompletePutaway::class)->handle($grn->putawayTasks()->sole(), [], $this->makeUser());
    }

    #[Test]
    public function tc_isu_20_label_pindah_ke_gudang_site_dan_wajib_dipindai_saat_dipakai(): void
    {
        $this->kabelBerlabel();
        $this->kirimKeSite($this->kabel, 24);

        $label = PackageLabel::query()->orderBy('sequence')->get();
        $this->assertTrue($label->every(fn ($l) => $l->status === PackageLabelStatus::InStock && (int) $l->warehouse_id === (int) $this->krw1->id),
            'A-300: label utuh dibuka lagi di Gudang Site tujuan.');
        $this->assertSame([(int) $this->binKrw1->id], $label->pluck('bin_id')->map(fn ($b) => (int) $b)->unique()->values()->all(), 'Put-away site memindah bin label.');

        $kunci = $this->kunciIsu($this->binKrw1, $this->kabel);

        // Tanpa pindai label: konfirmasi ditolak.
        $tanpa = $this->isu([['key' => $kunci, 'qty_base' => 12]]);
        $this->gagalIsu(fn () => $this->konfirmasi($tanpa), 'BR-LBL-04');

        // Layar ISU: pindai label induk → dialog jumlah isi → klaim di baris.
        $k = str_replace(':', '_', $kunci);
        Livewire::actingAs($this->stafSite())->test(IssueForm::class)
            ->set('form.project_id', (string) $this->proyek->id)
            ->set('form.warehouse_id', (string) $this->krw1->id)
            ->set('kodePindai', $label[0]->code)->call('pindai')
            ->assertSet('labelInduk', (int) $label[0]->id)
            ->assertSet('labelIsi', '12')
            ->call('simpanIsiLabel')
            ->assertSet('qty.'.$k, '12')
            ->assertSee($label[0]->code)
            ->call('simpan')
            ->assertHasNoErrors();

        $isu = MaterialIssue::query()->where('id', '!=', $tanpa->id)->sole();
        $this->assertSame([['id' => (int) $label[0]->id, 'qty' => 12.0]], $isu->lines()->sole()->labels);

        $this->konfirmasi($isu);
        $this->assertSame(PackageLabelStatus::Issued, $label[0]->refresh()->status);
        $this->assertSame('Keluar (dipakai)', $label[0]->lastMove()->eventLabel());
        $this->assertSame(12.0, app(PackageLabelLedger::class)->required((int) $this->krw1->id, (int) $this->kabel->id, null, 20), 'Wajib = min(keluar, isi label, saldo).');
    }

    #[Test]
    public function tc_isu_21_isu_pembalik_membuka_lagi_label(): void
    {
        $this->kabelBerlabel();
        $this->kirimKeSite($this->kabel, 24);
        $label = PackageLabel::query()->orderBy('sequence')->first();

        $isu = $this->konfirmasi($this->isu([['key' => $this->kunciIsu($this->binKrw1, $this->kabel), 'qty_base' => 12,
            'labels' => [['id' => $label->id, 'qty' => 12]]]]));
        $this->assertSame(PackageLabelStatus::Issued, $label->refresh()->status);

        $this->makeUser('management');
        $balik = app(CreateMaterialIssue::class)->reverse($isu, [], $this->alasan(ReasonContext::Cancel), 'Salah catat', $this->stafSite());
        $balik = $this->konfirmasi($balik, $this->stafSite());

        // Approver lapis pertama dari snapshot: fixture punya Kepala Gudang bercakupan semua (A-150).
        $snapshot = ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::MaterialIssue, $balik->id)->sole();
        app(ApproveMaterialIssue::class)->approve($balik, User::query()->findOrFail($snapshot->steps[0]['approver_user_ids'][0]));

        $label->refresh();
        $this->assertSame(PackageLabelStatus::InStock, $label->status);
        $this->assertSame(12.0, (float) $label->qty_remaining);
        $this->assertSame('Kembali (pembalik pemakaian)', $label->lastMove()->eventLabel());
    }

    #[Test]
    public function tc_ret_23_retur_penjualan_membuka_label_dan_pilah_rusak_membatalkannya_lalu_tampil_di_laporan_vendor(): void
    {
        $this->kabelBerlabel();
        $sj = $this->terimaSj($this->terkirimKeKlien($this->kabel, 12));
        $label = PackageLabel::query()->orderBy('sequence')->first();
        $this->assertSame(PackageLabelStatus::Issued, $label->refresh()->status);

        $ret = $this->ret([['key' => 'sold:'.$sj->lines()->first()->id, 'qty_base' => 12]]);
        $this->grnRetur($ret, [['goods_return_line_id' => $ret->lines()->sole()->id, 'qty_received' => 12]]);

        $label->refresh();
        $this->assertSame(PackageLabelStatus::InStock, $label->status, 'Retur penjualan membuka label yang dulu keluar utuh.');
        $this->assertSame((int) $this->binSistem($this->gudang, BinType::Return)->id, (int) $label->bin_id);

        $this->pilah($ret, [$ret->lines()->sole()->id => [
            ['sorting' => 'good', 'qty' => 4, 'target_bin_id' => $this->binB->id],
            ['sorting' => 'damaged', 'qty' => 8, 'reason_code_id' => $this->alasan(ReasonContext::Damage)],
        ]]);

        $label->refresh();
        $this->assertSame(4.0, (float) $label->qty_remaining, 'Bagian rusak keluar dari label.');
        $this->assertSame('Dipilah rusak/waste', $label->lastMove()->eventLabel());

        // TC-RPT-12: laporan per vendor menelusuri retur rusak lewat label.
        $this->actingAs($this->makeUser('warehouse_head'));
        $baris = app(VendorProblemReport::class)->rows([])->firstWhere('item', $this->kabel->code.' — '.$this->kabel->name);
        $this->assertNotNull($baris);
        $this->assertSame(24.0, $baris['diterima']);
        $this->assertSame(8.0, $baris['retur_rusak']);
        $this->assertSame('GRN, label', $baris['dilacak']);
        $this->assertSame(33.3, $baris['persen']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\User;
use App\Domain\Approval\Actions\DecideApproval;
use App\Domain\Approval\Actions\EscalateApprovalTask;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Models\ApprovalTask;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Receipt\Actions\CompleteGoodsReceipt;
use App\Domain\Receipt\Actions\CreateVendorReturn;
use App\Domain\Receipt\Enums\QcResult;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\VendorReturn;
use App\Domain\Shared\Reports\ReportRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalFixtures;
use Tests\Feature\Return\Concerns\ReturnFixtures;
use Tests\TenantTestCase;

/**
 * Laporan §9 modul 19, 20, dan 22 (Sisa Fase 1 2d, A-241):
 *
 * - TC-RPT-07 — Receipt/Putaway: penerimaan per vendor, karantina menurut
 *   umur, PUT tertunda, RTV terbuka.
 * - TC-RPT-08 — Approval: tugas terbuka, dokumen menunggu, waktu putus,
 *   eskalasi; cakupan gudang dari snapshot (BR-ACC-05).
 * - TC-RPT-10 — Retur/Transfer: TRF terbuka, barang dalam perjalanan, retur
 *   per proyek & hasil pilah, barang rusak di bin Retur.
 *
 * TC-RPT-09 (Count/Adjustment) ada di `CountReportTest` karena fixture
 * opname memakai gudang/item sendiri.
 */
class ModuleReportTest extends TenantTestCase
{
    use ApprovalFixtures;
    use ReturnFixtures;

    private ReportRegistry $registry;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanTransfer();
        $this->registry = app(ReportRegistry::class);
        $this->admin = $this->makeUser('company_admin');
        $this->admin->forgetPermissionCache();
    }

    /** Setiap laporan terbuka & terekspor oleh admin dan kosong tanpa data. @param  array<int, string>  $kunci */
    private function terbukaDanKosong(array $kunci): void
    {
        foreach ($kunci as $k) {
            $laporan = $this->registry->find($k);
            $this->actingAs($this->admin)->get($this->tenantUrl('reports/'.$k))->assertOk()->assertSee($laporan->title());
            $this->actingAs($this->admin)->get($this->tenantUrl('reports/'.$k.'/export'))->assertOk();
            $this->assertNotEmpty($laporan->columns(), $k);
        }

        $this->actingAs($this->admin);

        foreach ($kunci as $k) {
            $this->assertTrue($this->registry->find($k)->rows([])->isEmpty(), $k.' kosong tanpa data.');
        }
    }

    private function rows(string $kunci, array $filters = [])
    {
        return $this->registry->find($kunci)->rows($filters);
    }

    /** GRN vendor: kabel (wajib QC) 8 dan baut 5, diterima. */
    private function grnKabelBaut(): GoodsReceipt
    {
        return $this->grnDiterima([
            ['item_id' => $this->kabel->id, 'qty_received' => 8],
            ['item_id' => $this->baut->id, 'qty_received' => 5],
        ]);
    }

    private function rtv(GoodsReceipt $grn): VendorReturn
    {
        return app(CreateVendorReturn::class)->handle(
            $grn,
            [['goods_receipt_line_id' => (int) $grn->lines()->orderBy('id')->first()->id, 'qty_base' => 8]],
            null,
            $this->makeUser('warehouse_staff'),
        );
    }

    #[Test]
    public function tc_rpt_07_laporan_penerimaan_putaway(): void
    {
        $this->terbukaDanKosong(['penerimaan-vendor', 'karantina-umur', 'put-tertunda', 'rtv-terbuka']);

        $grn = $this->grnKabelBaut();
        $this->actingAs($this->admin);

        $terima = $this->rows('penerimaan-vendor')->sole();
        $this->assertSame($grn->number, $terima['grn']);
        $this->assertSame('V-BAJA — PT Baja Jaya', $terima['vendor']);
        $this->assertSame(2, $terima['baris']);
        $this->assertSame(13.0, $terima['jumlah']);
        $this->assertTrue($this->rows('penerimaan-vendor', ['vendor_id' => 999999])->isEmpty(), 'Penyaring vendor.');
        $this->assertTrue($this->rows('penerimaan-vendor', ['date_from' => now()->addMonth()->toDateString(), 'date_to' => now()->addMonths(2)->toDateString()])->isEmpty(), 'Penyaring periode.');

        // Kabel menunggu QC di Karantina: masuk hari ini lewat GRN.
        $karantina = $this->rows('karantina-umur')->sole();
        $this->assertSame('KABEL-NYM', $karantina['item']);
        $this->assertSame(8.0, $karantina['jumlah']);
        $this->assertSame($grn->number, $karantina['dokumen']);
        $this->assertSame(0, $karantina['umur']);
        $this->assertTrue($this->rows('karantina-umur', ['min_age_days' => 1])->isEmpty());

        // QC menolak kabel → keluar dari Karantina; GRN selesai → PUT baut tertunda; RTV kabel terbuka.
        $this->qc($grn, 0, QcResult::Rejected);
        $this->assertTrue($this->rows('karantina-umur')->isEmpty(), 'Barang ditolak QC berkondisi Rusak, bukan Karantina.');
        $put = app(CompleteGoodsReceipt::class)->handle($grn->refresh(), $this->makeUser())->putawayTasks()->sole();
        $rtv = $this->rtv($grn);

        $this->actingAs($this->admin);
        $tertunda = $this->rows('put-tertunda')->sole();
        $this->assertSame([$put->number, $grn->number, 1, 0], [$tertunda['put'], $tertunda['grn'], $tertunda['baris'], $tertunda['umur']]);

        $terbuka = $this->rows('rtv-terbuka')->sole();
        $this->assertSame($rtv->number, $terbuka['rtv']);
        $this->assertSame(8.0, $terbuka['jumlah']);

        // Izin: pemohon internal tidak memegang receipt.view.
        $this->actingAs($this->makeUser('internal_requester'))->get($this->tenantUrl('reports/penerimaan-vendor'))->assertForbidden();
    }

    #[Test]
    public function tc_rpt_08_laporan_approval(): void
    {
        $this->terbukaDanKosong(['tugas-approval-terbuka', 'dokumen-menunggu-approval', 'waktu-putus-approval', 'eskalasi-approval']);

        $atasan = $this->makeUser('management');
        $kepala = $this->makeUser('warehouse_head', attributes: ['manager_id' => $atasan->id]);
        $this->aturan(ApprovalDocumentType::VendorReturn, [$this->lapisUser($kepala)]);

        $grn = $this->grnKabelBaut();
        $this->qc($grn, 0, QcResult::Rejected);
        $rtv = $this->rtv($grn);

        $this->actingAs($this->admin);
        $tugas = $this->rows('tugas-approval-terbuka')->sole();
        $this->assertSame([$kepala->name, 'RTV — Retur ke Vendor', $rtv->number, 1, 'Tidak'], [$tugas['approver'], $tugas['jenis'], $tugas['nomor'], $tugas['lapis'], $tugas['lewat']]);

        $menunggu = $this->rows('dokumen-menunggu-approval')->sole();
        $this->assertSame([1, 1, $rtv->number], [$menunggu['dokumen'], $menunggu['tugas'], $menunggu['tertua']]);

        // Cakupan: Kepala Gudang BKS tidak melihat RTV gudang CKG (BR-ACC-05).
        $this->actingAs($this->makeUser('warehouse_head', ScopeType::Warehouse, $this->bks->id));
        $this->assertTrue($this->rows('tugas-approval-terbuka')->isEmpty());
        $this->assertTrue($this->rows('dokumen-menunggu-approval')->isEmpty());

        // Eskalasi manual ke atasan, lalu atasan menyetujui.
        $lama = ApprovalTask::query()->open()->where('approver_user_id', $kepala->id)->sole();
        $baru = app(EscalateApprovalTask::class)->handle($lama, $this->admin);
        app(DecideApproval::class)->approve($baru, $atasan);

        $this->actingAs($this->admin);
        $eskalasi = $this->rows('eskalasi-approval')->sole();
        $this->assertSame([$rtv->number, $kepala->name, $atasan->name, $this->admin->name], [$eskalasi['nomor'], $eskalasi['dari'], $eskalasi['ke'], $eskalasi['oleh']]);

        $putus = $this->rows('waktu-putus-approval')->sole();
        $this->assertSame(['RTV — Retur ke Vendor', 1, 1, 1, 0], [$putus['jenis'], $putus['lapis'], $putus['keputusan'], $putus['setuju'], $putus['tolak']]);
        $this->assertTrue($this->rows('tugas-approval-terbuka')->isEmpty(), 'Dokumen sudah diputus.');
        $this->assertTrue($this->rows('waktu-putus-approval', ['document_type' => 'material_request'])->isEmpty(), 'Penyaring jenis dokumen.');

        // Izin: staf gudang tidak memegang approval_rule.view.
        $this->actingAs($this->makeUser('warehouse_staff'))->get($this->tenantUrl('reports/tugas-approval-terbuka'))->assertForbidden();
    }

    #[Test]
    public function tc_rpt_10_laporan_retur_transfer(): void
    {
        $this->terbukaDanKosong(['trf-terbuka', 'barang-dalam-perjalanan', 'retur-per-proyek', 'rusak-bin-retur']);

        // TRF CKG → BKS 5 baut: diambil dan berangkat, menunggu di Dalam Perjalanan CKG.
        $trf = $this->trf($this->gudang, $this->bks, [['item_id' => $this->baut->id, 'qty_base' => 5]]);
        $sj = $this->sjDari($this->jalankanPck($this->pckTrf($trf)), $this->bks);

        $this->actingAs($this->admin);
        $terbuka = $this->rows('trf-terbuka')->sole();
        $this->assertSame([$trf->number, 'CKG', 'BKS', 5.0, 5.0, 0.0], [$terbuka['trf'], $terbuka['dari'], $terbuka['ke'], $terbuka['diminta'], $terbuka['dikirim'], $terbuka['diterima']]);
        $this->assertCount(1, $this->rows('trf-terbuka', ['warehouse_id' => $this->bks->id]), 'Penyaring cocok pada gudang tujuan juga.');

        $jalan = $this->rows('barang-dalam-perjalanan')->sole();
        $this->assertSame(['CKG', 'BAUT-M12', 5.0, $sj->number, 'BKS', $trf->number, 0], [$jalan['gudang'], $jalan['item'], $jalan['jumlah'], $jalan['sj'], $jalan['tujuan'], $jalan['trf'], $jalan['umur']]);
        $this->assertTrue($this->rows('barang-dalam-perjalanan', ['warehouse_id' => $this->bks->id])->isEmpty(), 'Barang di jalan milik gudang asal (BR-STK-13).');

        // RET 10 baut dari Gudang Site: 7 layak ke bin B, 3 rusak tetap di bin Retur.
        $this->stok($this->binKrw1, $this->baut, 20);
        $ret = $this->ret([['key' => $this->kunciSite($this->binKrw1, $this->baut), 'qty_base' => 10]]);
        $this->grnRetur($ret);
        $this->pilah($ret, [$ret->lines()->sole()->id => [
            ['sorting' => 'good', 'qty' => 7, 'target_bin_id' => $this->binB->id],
            ['sorting' => 'damaged', 'qty' => 3, 'reason_code_id' => $this->alasan(ReasonContext::Damage)],
        ]]);

        $this->actingAs($this->admin);
        $retur = $this->rows('retur-per-proyek')->sole();
        $this->assertSame([$this->proyek->code, $ret->number, 10.0, 10.0, 7.0, 3.0, 0.0, 0.0],
            [$retur['proyek'], $retur['ret'], $retur['diajukan'], $retur['diterima'], $retur['layak'], $retur['rusak'], $retur['offcut'], $retur['waste']]);
        $this->assertTrue($this->rows('retur-per-proyek', ['project_id' => 999999])->isEmpty(), 'Penyaring proyek.');

        $rusak = $this->rows('rusak-bin-retur')->sole();
        $this->assertSame(['CKG', 'BAUT-M12', 3.0, $ret->number, 0], [$rusak['gudang'], $rusak['item'], $rusak['jumlah'], $rusak['dokumen'], $rusak['umur']]);

        // Izin: driver tidak memegang transfer.view maupun return.view.
        $driver = $this->makeUser('driver');
        $this->actingAs($driver)->get($this->tenantUrl('reports/trf-terbuka'))->assertForbidden();
        $this->actingAs($driver)->get($this->tenantUrl('reports/rusak-bin-retur'))->assertForbidden();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Return;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApprovalSnapshotStatus;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Master\Enums\ProjectStatus;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Receipt\Actions\CompleteGoodsReceipt;
use App\Domain\Receipt\Actions\SaveGoodsReceipt;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Return\Actions\ApproveGoodsReturn;
use App\Domain\Return\Actions\CancelGoodsReturn;
use App\Domain\Return\Enums\GoodsReturnStatus;
use App\Domain\Return\Enums\ReturnOwnership;
use App\Domain\Return\Exceptions\ReturnRuleException;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Shipment\Enums\PickTaskStatus;
use App\Domain\Stock\Enums\ReservationLevel;
use App\Domain\Stock\Models\StockReservation;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalFixtures;
use Tests\Feature\Return\Concerns\ReturnFixtures;
use Tests\TenantTestCase;

/**
 * TC-RET-01 s.d. TC-RET-07 — pengajuan, guard, approval, pembatalan, dan guard
 * GRN retur (Katalog §2.8, BR-RET-03, BR-RET-05, BR-GRN-05, A-110–A-112).
 */
class ReturnTest extends TenantTestCase
{
    use ApprovalFixtures;
    use ReturnFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanTransfer();
        $this->stok($this->binKrw1, $this->baut, 20);
    }

    private function gagal(callable $aksi, string $aturan, string $kelas = ReturnRuleException::class): void
    {
        try {
            $aksi();
            $this->fail('Seharusnya ditolak '.$aturan.'.');
        } catch (ReturnRuleException|ReceiptRuleException $e) {
            $this->assertInstanceOf($kelas, $e);
            $this->assertSame($aturan, $e->rule, $e->getMessage());
        }
    }

    #[Test]
    public function tc_ret_01_stok_gudang_site_diantar_sendiri_langsung_diproses(): void
    {
        $kunci = $this->kunciSite($this->binKrw1, $this->baut);
        $this->assertSame(20.0, $this->calon()[$kunci]['max']);

        $ret = $this->ret([['key' => $kunci, 'qty_base' => 12]]);

        $this->assertStringStartsWith('RET/CKG/', $ret->number, 'Nomor memakai gudang tujuan (A-110).');
        $this->assertSame(GoodsReturnStatus::InProgress, $ret->status, 'Tanpa aturan & diantar sendiri → diproses (Katalog §2.8).');
        $this->assertSame((int) $this->krw1->id, (int) $ret->from_warehouse_id);
        $this->assertTrue($ret->self_delivered);

        $baris = $ret->lines()->sole();
        $this->assertSame(ReturnOwnership::Company, $baris->ownership, 'BR-RET-03: stok Gudang Site milik company.');

        // A-111: stok Gudang Site dicadangkan keras sampai GRN retur.
        $this->assertSame(12.0, (float) StockReservation::query()->active()->where('level', ReservationLevel::Hard->value)
            ->forDocument('goods_return', $ret->id)->sum('qty_base'));
        $this->assertSame(8.0, $this->calon()[$kunci]['max']);
        $this->assertSame(ApprovalSnapshotStatus::Approved, ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::GoodsReturn, $ret->id)->sole()->status);
    }

    #[Test]
    public function tc_ret_02_guard_pengajuan(): void
    {
        $kunci = $this->kunciSite($this->binKrw1, $this->baut);

        $this->gagal(fn () => $this->ret([['key' => $kunci, 'qty_base' => 21]]), 'BR-RET-03');
        $this->gagal(fn () => $this->ret([['key' => 'site:999:1:0:0:0', 'qty_base' => 1]]), 'BR-RET-03');
        $this->gagal(fn () => $this->ret([]), 'BR-RET-03');
        $this->gagal(fn () => $this->ret([['key' => $kunci, 'qty_base' => 1]], ['to_warehouse_id' => $this->krw2->id]), 'BR-RET-02');

        // Dua Gudang Site dalam satu RET.
        $this->stok($this->binKrw2, $this->baut, 5);
        $this->gagal(fn () => $this->ret([
            ['key' => $kunci, 'qty_base' => 1],
            ['key' => $this->kunciSite($this->binKrw2, $this->baut), 'qty_base' => 1],
        ]), 'BR-RET-03');

        // BR-ACC-05: pemohon proyek lain.
        $lain = $this->makeUser('internal_requester', ScopeType::Project, $this->makeProject()->id);
        $this->gagal(fn () => $this->ret([['key' => $kunci, 'qty_base' => 1]], [], $lain), 'BR-ACC-05');

        $this->proyek->forceFill(['status' => ProjectStatus::Closed])->save();
        $this->gagal(fn () => $this->ret([['key' => $kunci, 'qty_base' => 1]]), 'BR-PRJ-01');

        $this->assertSame(0, GoodsReturn::query()->count());
    }

    #[Test]
    public function tc_ret_03_approval_sod_dan_tolak(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $this->aturan(ApprovalDocumentType::GoodsReturn, [$this->lapisUser($kepala)]);
        $pemohon = $this->makeUser('internal_requester');
        $kunci = $this->kunciSite($this->binKrw1, $this->baut);

        $ret = $this->ret([['key' => $kunci, 'qty_base' => 5]], [], $pemohon);
        $this->assertSame(GoodsReturnStatus::PendingApproval, $ret->status);
        $this->assertSame(0, StockReservation::query()->active()->forDocument('goods_return', $ret->id)->count());

        $this->gagal(fn () => app(ApproveGoodsReturn::class)->approve($ret, $pemohon), 'BR-APR-03');
        $this->gagal(fn () => app(ApproveGoodsReturn::class)->reject($ret, null, null, $kepala), 'BR-GEN-11');

        $ret = app(ApproveGoodsReturn::class)->approve($ret, $kepala);
        $this->assertSame(GoodsReturnStatus::InProgress, $ret->status);
        $this->assertSame((int) $kepala->id, (int) $ret->approved_by);

        $kedua = $this->ret([['key' => $kunci, 'qty_base' => 5]], [], $pemohon);
        $kedua = app(ApproveGoodsReturn::class)->reject($kedua, $this->alasan(ReasonContext::Reject), null, $kepala);
        $this->assertSame(GoodsReturnStatus::Rejected, $kedua->status);
        $this->assertSame(15.0, $this->calon()[$kunci]['max'], 'Jumlah RET ditolak bebas lagi.');
    }

    #[Test]
    public function tc_ret_04_pembatalan(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $kunci = $this->kunciSite($this->binKrw1, $this->baut);

        // Menunggu approval → dibatalkan pengaju.
        $this->aturan(ApprovalDocumentType::GoodsReturn, [$this->lapisUser($kepala)]);
        $pemohon = $this->makeUser('internal_requester');
        $ret = $this->ret([['key' => $kunci, 'qty_base' => 4]], [], $pemohon);

        $this->gagal(fn () => app(CancelGoodsReturn::class)->handle($ret, null, null, $pemohon), 'BR-GEN-11');
        $ret = app(CancelGoodsReturn::class)->handle($ret, $this->alasan(ReasonContext::Cancel), null, $pemohon);
        $this->assertSame(GoodsReturnStatus::Cancelled, $ret->status);
        $this->assertSame(ApprovalSnapshotStatus::Cancelled, ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::GoodsReturn, $ret->id)->latest('id')->first()->status);

        // Dengan SJ balik: disetujui → PCK di Gudang Site; batal ikut membatalkan PCK.
        $sj = app(ApproveGoodsReturn::class)->approve($this->ret([['key' => $kunci, 'qty_base' => 6]], ['self_delivered' => false], $pemohon), $kepala);
        $this->assertSame(GoodsReturnStatus::Approved, $sj->status, 'Katalog §2.8: diproses saat SJ balik disusun.');
        $pck = $sj->livePickTask();
        $this->assertSame((int) $this->krw1->id, (int) $pck->warehouse_id);

        $sj = app(CancelGoodsReturn::class)->handle($sj, $this->alasan(ReasonContext::Cancel), null, $kepala);
        $this->assertSame(GoodsReturnStatus::Cancelled, $sj->status);
        $this->assertSame(PickTaskStatus::Cancelled, $pck->refresh()->status);
        $this->assertSame(0, StockReservation::query()->active()->whereIn('document_type', ['goods_return', 'pick_task'])->count());

        // PCK sudah selesai → barang di Loading Area Gudang Site: tidak bisa batal.
        $jalan = app(ApproveGoodsReturn::class)->approve($this->ret([['key' => $kunci, 'qty_base' => 3]], ['self_delivered' => false], $pemohon), $kepala);
        $this->jalankanPck($jalan->livePickTask());
        $this->gagal(fn () => app(CancelGoodsReturn::class)->handle($jalan->refresh(), $this->alasan(ReasonContext::Cancel), null, $kepala), 'BR-GEN-03');

        // Diproses (tanpa SJ) tidak bisa dibatalkan.
        $proses = app(ApproveGoodsReturn::class)->approve($this->ret([['key' => $kunci, 'qty_base' => 1]], [], $pemohon), $kepala);
        $this->gagal(fn () => app(CancelGoodsReturn::class)->handle($proses, $this->alasan(ReasonContext::Cancel), null, $kepala), 'BR-GEN-03');
    }

    #[Test]
    public function tc_ret_05_sj_balik_hanya_untuk_stok_gudang_site(): void
    {
        $sj = $this->terkirimKeKlien($this->baut, 10);
        $this->terimaSj($sj);

        $kunciJual = 'sold:'.$sj->lines()->first()->id;

        $this->gagal(fn () => $this->ret([['key' => $kunciJual, 'qty_base' => 2]], ['self_delivered' => false]), 'BR-RET-03');

        $ret = $this->ret([['key' => $kunciJual, 'qty_base' => 2]]);
        $this->assertSame(ReturnOwnership::Sold, $ret->lines()->sole()->ownership, 'BR-RET-03: retur penjualan.');
        $this->assertSame((int) $sj->id, (int) $ret->origin_shipment_id, 'Merujuk SJ asal.');
    }

    #[Test]
    public function tc_ret_06_klien_hanya_barang_terkirim_dan_proyeknya_sendiri(): void
    {
        $sj = $this->terkirimKeKlien($this->baut, 10);
        $this->terimaSj($sj);

        $klien = $this->makeUser('client_user', ScopeType::Project, $this->proyek->id, ['client_id' => $this->proyek->client_id]);

        // BR-RET-05: stok Gudang Site bukan milik klien.
        $this->gagal(fn () => $this->ret([['key' => $this->kunciSite($this->binKrw1, $this->baut), 'qty_base' => 1]], [], $klien), 'BR-RET-05');

        $ret = $this->ret([['key' => 'sold:'.$sj->lines()->first()->id, 'qty_base' => 3]], [], $klien);
        $this->assertTrue($ret->isFromClient());

        $klienLain = $this->makeUser('client_user', ScopeType::Project, $this->makeProject()->id, ['client_id' => $this->makeClient()->id]);
        $this->actingAs($klienLain);
        $this->assertFalse(GoodsReturn::query()->whereKey($ret->id)->exists(), 'BR-PRJ-06: klien lain tidak melihat.');
        $this->actingAs($klien);
        $this->assertTrue(GoodsReturn::query()->whereKey($ret->id)->exists());
    }

    #[Test]
    public function tc_ret_07_guard_grn_retur(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $this->aturan(ApprovalDocumentType::GoodsReturn, [$this->lapisUser($kepala)]);
        $kunci = $this->kunciSite($this->binKrw1, $this->baut);

        $menunggu = $this->ret([['key' => $kunci, 'qty_base' => 5]]);
        $this->gagal(fn () => $this->grnRetur($menunggu), 'BR-RET-01', ReceiptRuleException::class);

        $ret = app(ApproveGoodsReturn::class)->approve($menunggu, $kepala);
        $baris = $ret->lines()->sole();

        // Gudang penerima harus gudang tujuan RET.
        $this->gagal(fn () => app(SaveGoodsReceipt::class)->handle(null, [
            'receipt_type' => 'return', 'warehouse_id' => $this->bks->id, 'goods_return_id' => $ret->id,
        ], [], $this->makeUser()), 'BR-RET-01', ReceiptRuleException::class);

        // BR-GRN-05: kelebihan atas yang diajukan menjadi ADJ (A-245, TC-GRN-11).
        $grn = $this->grnRetur($ret, [['goods_return_line_id' => $baris->id, 'qty_received' => 4]]);
        $this->assertSame(GoodsReturnStatus::Received, $ret->refresh()->status);
        $this->assertSame(4.0, (float) $baris->refresh()->qty_received);

        // Satu GRN aktif per RET; GRN retur tidak diselesaikan manual (A-112).
        $this->assertSame('BR-RET-01', $this->tangkap(fn () => $this->grnRetur($ret)));
        $this->gagal(fn () => app(CompleteGoodsReceipt::class)->handle($grn, $this->makeUser()), 'BR-RET-04', ReceiptRuleException::class);
    }

    private function tangkap(callable $aksi): string
    {
        try {
            $aksi();
        } catch (ReceiptRuleException $e) {
            return $e->rule;
        }

        return '';
    }
}

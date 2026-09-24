<?php

declare(strict_types=1);

namespace Tests\Feature\Receipt;

use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Receipt\Actions\ApproveVendorReturn;
use App\Domain\Receipt\Actions\CancelVendorReturn;
use App\Domain\Receipt\Actions\CompleteVendorReturn;
use App\Domain\Receipt\Actions\CreateVendorReturn;
use App\Domain\Receipt\Actions\ReceiveGoodsReceipt;
use App\Domain\Receipt\Actions\ShipVendorReturn;
use App\Domain\Receipt\Enums\QcResult;
use App\Domain\Receipt\Enums\VendorReturnStatus;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\VendorReturn;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApproverType;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalFixtures;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-RTV-01 s.d. TC-RTV-08 — retur ke vendor (Katalog §2.16, BR-GRN-04,
 * BR-APR-03, BR-GEN-03, BR-GEN-11, A-80).
 */
class VendorReturnTest extends TenantTestCase
{
    use ApprovalFixtures;
    use ReceiptFixtures;

    private GoodsReceipt $grn;

    private \App\Domain\Access\Models\User $kepala;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();

        // Baris 0: kabel ditolak QC (8). Baris 1: baut tanpa QC (5).
        $this->grn = $this->grnDiterima([
            ['item_id' => $this->kabel->id, 'qty_received' => 8],
            ['item_id' => $this->baut->id, 'qty_received' => 5],
        ]);
        $this->qc($this->grn, 0, QcResult::Rejected);

        // RTV diputus lewat mesin approval (A-80 → 20-approval §13): satu lapis
        // kepala gudang terkait, sama dengan aturan demo 00-akun-uji §5.
        $this->kepala = $this->makeUser('warehouse_head');
        $this->aturan(ApprovalDocumentType::VendorReturn, [$this->lapis(ApproverType::WarehouseHead)]);
    }

    private function baris(int $i): int
    {
        return (int) $this->grn->lines()->orderBy('id')->get()[$i]->id;
    }

    private function ajukan(float $qty = 8, ?\App\Domain\Access\Models\User $staf = null): VendorReturn
    {
        return app(CreateVendorReturn::class)->handle(
            $this->grn,
            [['goods_receipt_line_id' => $this->baris(0), 'qty_base' => $qty]],
            null,
            $staf ?? $this->makeUser('warehouse_staff'),
        );
    }

    private function gagal(callable $aksi, string $aturan): void
    {
        try {
            $aksi();
            $this->fail('Seharusnya ditolak '.$aturan.'.');
        } catch (ReceiptRuleException $e) {
            $this->assertSame($aturan, $e->rule, $e->getMessage());
        }
    }

    #[Test]
    public function tc_rtv_01_diajukan_dari_baris_ditolak_langsung_menunggu_approval(): void
    {
        $rtv = $this->ajukan();

        $this->assertSame(VendorReturnStatus::PendingApproval, $rtv->status);
        $this->assertStringStartsWith('RTV/CKG/', $rtv->number);
        $this->assertSame((int) $this->vendor->id, (int) $rtv->vendor_id);
        $baris = $rtv->lines()->sole();
        $this->assertSame(StockStatus::Damaged, $baris->stock_status);
        $this->assertNotNull($baris->reason_code_id, 'Alasan diwarisi dari alasan QC.');
    }

    #[Test]
    public function tc_rtv_02_hanya_dari_karantina_dan_tidak_melebihi_sisa(): void
    {
        $this->gagal(fn () => app(CreateVendorReturn::class)->handle(
            $this->grn,
            [['goods_receipt_line_id' => $this->baris(1), 'qty_base' => 5, 'reason_code_id' => $this->alasan(ReasonContext::Reject)]],
            null,
            $this->makeUser(),
        ), 'BR-GRN-04');

        $this->gagal(fn () => $this->ajukan(9), 'BR-GRN-04');

        $this->ajukan(5);
        $this->gagal(fn () => $this->ajukan(4), 'BR-GRN-04');
    }

    #[Test]
    public function tc_rtv_03_pengaju_tidak_boleh_menyetujui_sendiri(): void
    {
        $staf = $this->makeUser('warehouse_head');
        $rtv = $this->ajukan(8, $staf);

        $this->gagal(fn () => app(ApproveVendorReturn::class)->approve($rtv, $staf), 'BR-APR-03');
        $this->assertFalse($staf->can('approve', $rtv));

        $rtv = app(ApproveVendorReturn::class)->approve($rtv, $this->kepala);
        $this->assertSame(VendorReturnStatus::Approved, $rtv->status);
    }

    #[Test]
    public function tc_rtv_04_menolak_wajib_alasan(): void
    {
        $rtv = $this->ajukan();
        $kepala = $this->kepala;

        $this->gagal(fn () => app(ApproveVendorReturn::class)->reject($rtv, null, null, $kepala), 'BR-GEN-11');

        $rtv = app(ApproveVendorReturn::class)->reject($rtv, $this->alasan(ReasonContext::Reject), 'Diganti potong tagihan', $kepala);
        $this->assertSame(VendorReturnStatus::Rejected, $rtv->status);

        // Jumlahnya kembali bebas untuk RTV baru.
        $this->assertSame(VendorReturnStatus::PendingApproval, $this->ajukan(8)->status);
    }

    #[Test]
    public function tc_rtv_05_kirim_mengeluarkan_stok_karantina_dengan_goods_rejected(): void
    {
        $rtv = app(ApproveVendorReturn::class)->approve($this->ajukan(), $this->kepala);
        $rtv = app(ShipVendorReturn::class)->handle($rtv, null, $this->makeUser());

        $this->assertSame(VendorReturnStatus::Shipped, $rtv->status);
        $this->assertSame(0.0, $this->saldo($this->binSistem($this->gudang, BinType::Quarantine), $this->kabel, StockStatus::Damaged));

        $kejadian = StockEvent::query()->where('source_type', 'vendor_return')->where('source_id', $rtv->id)->sole();
        $this->assertSame('goods_rejected', $kejadian->event_type->value);
        $this->assertSame($this->grn->number, $kejadian->payload['grn_ref']);
    }

    #[Test]
    public function tc_rtv_06_selesai_dan_grn_pengganti_menautkan_diri(): void
    {
        $rtv = app(ApproveVendorReturn::class)->approve($this->ajukan(), $this->kepala);
        $rtv = app(ShipVendorReturn::class)->handle($rtv, null, $this->makeUser());

        $pengganti = $this->grnDraf([['item_id' => $this->kabel->id, 'qty_received' => 8]], ['vendor_return_id' => $rtv->id]);
        app(ReceiveGoodsReceipt::class)->handle($pengganti, $this->makeUser());

        $rtv = app(CompleteVendorReturn::class)->handle($rtv->refresh(), 'Vendor mengganti', $this->makeUser('pr_follow_up'));
        $this->assertSame(VendorReturnStatus::Completed, $rtv->status);
        $this->assertNotNull($rtv->vendor_confirmed_at);
        $this->assertSame((int) $pengganti->id, (int) $rtv->replacement_receipt_id);
    }

    #[Test]
    public function tc_rtv_07_batal_hanya_sebelum_dikirim(): void
    {
        $rtv = $this->ajukan();

        $this->gagal(fn () => app(CancelVendorReturn::class)->handle($rtv, null, null, $this->makeUser()), 'BR-GEN-11');

        $batal = app(CancelVendorReturn::class)->handle($rtv, $this->alasan(ReasonContext::Cancel), null, $this->makeUser());
        $this->assertSame(VendorReturnStatus::Cancelled, $batal->status);

        $kirim = app(ShipVendorReturn::class)->handle(
            app(ApproveVendorReturn::class)->approve($this->ajukan(), $this->kepala),
            null,
            $this->makeUser(),
        );

        $this->gagal(fn () => app(CancelVendorReturn::class)->handle($kirim, $this->alasan(ReasonContext::Cancel), null, $this->makeUser()), 'BR-GEN-03');
    }

    #[Test]
    public function tc_rtv_08_baris_yang_dimuat_rtv_tidak_bisa_diputus_ulang_qc(): void
    {
        $grn = $this->grnDiterima([['item_id' => $this->kabel->id, 'qty_received' => 3]]);
        $this->qc($grn, 0, QcResult::Quarantined, false);

        app(CreateVendorReturn::class)->handle($grn, [[
            'goods_receipt_line_id' => $grn->lines()->first()->id, 'qty_base' => 3, 'reason_code_id' => $this->alasan(ReasonContext::Reject),
        ]], null, $this->makeUser());

        $this->gagal(fn () => $this->qc($grn, 0, QcResult::Passed, false), 'BR-GRN-04');
    }
}

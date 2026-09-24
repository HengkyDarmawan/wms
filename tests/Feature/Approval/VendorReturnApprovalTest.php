<?php

declare(strict_types=1);

namespace Tests\Feature\Approval;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Approval\Actions\DecideApproval;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApprovalSnapshotStatus;
use App\Domain\Approval\Enums\ApproverType;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Models\ApprovalTask;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Receipt\Actions\ApproveVendorReturn;
use App\Domain\Receipt\Actions\CancelVendorReturn;
use App\Domain\Receipt\Actions\CreateVendorReturn;
use App\Domain\Receipt\Enums\QcResult;
use App\Domain\Receipt\Enums\VendorReturnStatus;
use App\Domain\Receipt\Models\VendorReturn;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalFixtures;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-APR-18 — RTV diputus lewat mesin approval, menggantikan jalur
 * sementara A-80 (Katalog §2.16, A-93).
 */
class VendorReturnApprovalTest extends TenantTestCase
{
    use ApprovalFixtures;
    use ReceiptFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();
    }

    private function rtvBaru(\App\Domain\Access\Models\User $staf): VendorReturn
    {
        $grn = $this->grnDiterima([['item_id' => $this->kabel->id, 'qty_received' => 4]]);
        $this->qc($grn, 0, QcResult::Rejected);

        return app(CreateVendorReturn::class)->handle(
            $grn,
            [['goods_receipt_line_id' => $grn->lines()->first()->id, 'qty_base' => 4]],
            null,
            $staf,
        );
    }

    private function snapshot(VendorReturn $rtv): ApprovalSnapshot
    {
        return ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::VendorReturn, (int) $rtv->id)->latest('id')->firstOrFail();
    }

    #[Test]
    public function tc_apr_18_rtv_diputus_lewat_mesin_approval(): void
    {
        $staf = $this->makeUser('warehouse_staff');

        // Tanpa aturan RTV: langsung disetujui saat diajukan (A-08, A-93).
        $otomatis = $this->rtvBaru($staf);
        $this->assertSame(VendorReturnStatus::Approved, $otomatis->status);
        $this->assertTrue($this->snapshot($otomatis)->wasAutoApproved());
        $this->assertSame((int) $this->snapshot($otomatis)->id, (int) $otomatis->approval_snapshot_id);

        // Dengan aturan: Kepala Gudang gudang RTV.
        $kepala = $this->makeUser('warehouse_head', ScopeType::Warehouse, (int) $this->gudang->id);
        $kepalaLain = $this->makeUser('warehouse_head');
        $this->aturan(ApprovalDocumentType::VendorReturn, [$this->lapis(ApproverType::WarehouseHead)], ['vendor_types' => ['company']]);

        $rtv = $this->rtvBaru($staf);
        $this->assertSame(VendorReturnStatus::PendingApproval, $rtv->status);
        $this->assertSame([(int) $kepala->id], ApprovalTask::query()->open()->pluck('approver_user_id')->map(fn ($v) => (int) $v)->all());
        $this->assertFalse($kepalaLain->can('approve', $rtv), 'Tanpa tugas, tombol setujui tidak muncul.');
        $this->assertTrue($kepala->can('approve', $rtv));

        $rtv = app(ApproveVendorReturn::class)->approve($rtv, $kepala);
        $this->assertSame(VendorReturnStatus::Approved, $rtv->status);
        $this->assertSame((int) $kepala->id, (int) $rtv->approved_by);

        // Tolak dari kotak tugas: alasan wajib, RTV ditolak.
        $tolak = $this->rtvBaru($staf);
        $tugas = ApprovalTask::query()->open()->where('approval_snapshot_id', $this->snapshot($tolak)->id)->sole();
        app(DecideApproval::class)->reject($tugas, $kepala, $this->alasan(ReasonContext::Reject), 'Vendor potong tagihan');
        $tolak->refresh();
        $this->assertSame(VendorReturnStatus::Rejected, $tolak->status);
        $this->assertNotNull($tolak->reject_reason_id);

        // Batal saat menunggu: snapshot dihentikan.
        $batal = $this->rtvBaru($staf);
        app(CancelVendorReturn::class)->handle($batal, $this->alasan(ReasonContext::Cancel), null, $staf);
        $this->assertSame(ApprovalSnapshotStatus::Cancelled, $this->snapshot($batal)->status);
        $this->assertSame(0, ApprovalTask::query()->open()->count());
    }
}

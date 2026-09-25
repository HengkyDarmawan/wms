<?php

declare(strict_types=1);

namespace Tests\Feature\PurchaseRequest;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApproverType;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Enums\VendorType;
use App\Domain\Master\Models\ItemVendor;
use App\Domain\Master\Models\Vendor;
use App\Domain\PurchaseRequest\Actions\ApprovePurchaseRequest;
use App\Domain\PurchaseRequest\Actions\CancelPurchaseRequest;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestOrigin;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestStatus;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\Receipt\Actions\ReceiveGoodsReceipt;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalFixtures;
use Tests\Feature\PurchaseRequest\Concerns\PurchaseFixtures;
use Tests\TenantTestCase;

/**
 * TC-PRQ-01 s.d. TC-PRQ-06 — PRQ manual, approval bersyarat jenis vendor,
 * catatan pemesanan, GRN yang merujuk catatan, dan pembatalan (Katalog §2.15,
 * A-47, A-51–A-53, A-170–A-174).
 */
class PurchaseRequestTest extends TenantTestCase
{
    use ApprovalFixtures;
    use PurchaseFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPembelian();
    }

    #[Test]
    public function tc_prq_01_prq_manual_langsung_diajukan_dan_disetujui_otomatis_tanpa_aturan(): void
    {
        $prq = $this->prqManual([
            ['item_id' => $this->baut->id, 'qty_base' => 100, 'required_date' => now()->addWeek()->toDateString()],
            ['item_id' => $this->semen->id, 'qty_base' => 20],
        ]);

        $this->assertStringStartsWith('PRQ', $prq->number);
        $this->assertSame(PurchaseRequestOrigin::Manual, $prq->origin);
        $this->assertSame(PurchaseRequestStatus::Approved, $prq->status, 'A-08: tanpa aturan approval PRQ disetujui otomatis.');
        $this->assertSame(2, $prq->lines()->count());

        $kejadian = StockEvent::query()->where('event_type', StockEventType::PurchaseRequested->value)->sole();
        $this->assertSame($prq->number, $kejadian->payload['purchase_request_number']);
        $this->assertCount(2, $kejadian->payload['lines']);
        $this->assertArrayNotHasKey('price', $kejadian->payload['lines'][0], 'D-07: tanpa harga.');
    }

    #[Test]
    public function tc_prq_02_validasi_prq_manual(): void
    {
        $this->gagalPrq(fn () => $this->prqManual([]), 'BR-GEN-11');
        $this->gagalPrq(fn () => $this->prqManual([['item_id' => $this->baut->id, 'qty_base' => 0]]), 'BR-LED-02');

        $this->baut->forceFill(['status' => ItemStatus::Inactive])->save();
        $this->gagalPrq(fn () => $this->prqManual(), 'BR-MST-05');
        $this->baut->forceFill(['status' => ItemStatus::Active])->save();

        // BR-ACC-05: gudang di luar cakupan tidak ditemukan.
        $bks = $this->buatGudang('BKS', 'Gudang Bekasi');
        $stafBks = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $bks->id);
        $this->actingAs($stafBks);
        $this->gagalPrq(fn () => $this->prqManual(null, ['warehouse_id' => $this->gudang->id, 'project_id' => null], $stafBks), 'BR-ACC-05');
    }

    #[Test]
    public function tc_prq_03_aturan_jenis_vendor_toko_online_dua_lapis_dan_tolak(): void
    {
        $toko = Vendor::create(['code' => 'V-TOKO', 'name' => 'Tokopedia Toko Alat', 'vendor_type' => VendorType::OnlineMarketplace, 'status' => VendorStatus::Active, 'is_active' => true]);
        ItemVendor::create(['item_id' => $this->baut->id, 'vendor_id' => $toko->id, 'priority' => 1, 'is_preferred' => true]);

        $this->aturan(ApprovalDocumentType::PurchaseRequest, [
            $this->lapis(ApproverType::WarehouseHead),
            $this->lapisRole('management'),
        ], ['match' => 'all', 'vendor_types' => ['online_marketplace']], 10, 'PRQ toko online');

        $kepala = $this->makeUser('warehouse_head', ScopeType::Warehouse, $this->gudang->id);
        $manajemen = $this->makeUser('management');
        $pembuat = $this->makeUser('warehouse_head', ScopeType::Warehouse, $this->gudang->id);

        $prq = $this->prqManual(null, [], $pembuat);
        $this->assertSame(PurchaseRequestStatus::PendingApproval, $prq->status);

        // BR-APR-03: pembuat tidak memutus PRQ-nya sendiri; Manajemen menunggu lapis 1.
        $this->assertFalse(Gate::forUser($pembuat)->allows('approve', $prq));
        $this->assertFalse(Gate::forUser($manajemen)->allows('approve', $prq));
        $this->assertTrue(Gate::forUser($kepala)->allows('approve', $prq));

        app(ApprovePurchaseRequest::class)->approve($prq, $kepala);
        $this->assertSame(PurchaseRequestStatus::PendingApproval, $prq->refresh()->status);
        app(ApprovePurchaseRequest::class)->approve($prq, $manajemen);
        $this->assertSame(PurchaseRequestStatus::Approved, $prq->refresh()->status);

        // PRQ tanpa vendor toko online tidak kena aturan.
        $semen = $this->prqManual([['item_id' => $this->semen->id, 'qty_base' => 5]]);
        $this->assertSame(PurchaseRequestStatus::Approved, $semen->status);

        // Tolak wajib alasan; PRQ ditolak tidak bisa dipesan.
        $kedua = $this->prqManual(null, [], $pembuat);
        $this->gagalPrq(fn () => app(ApprovePurchaseRequest::class)->reject($kedua, null, null, $kepala), 'BR-GEN-11');
        app(ApprovePurchaseRequest::class)->reject($kedua, $this->alasan(ReasonContext::Reject), 'Stok masih cukup', $kepala);
        $this->assertSame(PurchaseRequestStatus::Rejected, $kedua->refresh()->status);
        $this->gagalPrq(fn () => $this->pesan($kedua), 'BR-GEN-01');
    }

    #[Test]
    public function tc_prq_04_catatan_pemesanan_dipecah_ke_dua_vendor_dan_vendor_baru_sementara(): void
    {
        $prq = $this->prqManual([['item_id' => $this->baut->id, 'qty_base' => 100]]);
        $baris = $prq->lines()->sole();

        $this->gagalPrq(fn () => $this->pesan($prq, [$baris->id => 120]), 'BR-REQ-08');
        $this->gagalPrq(fn () => $this->pesan($prq, [$baris->id => 0]), 'BR-GEN-11');

        $satu = $this->pesan($prq, [$baris->id => 60]);
        $this->assertSame(PurchaseRequestStatus::Forwarded, $prq->refresh()->status);
        $this->assertNotNull($prq->forwarded_at);
        $this->assertSame('PO-2026-001', $satu->external_po_no);

        // A-53: toko baru dibuat sementara dari form catatan pemesanan.
        $dua = $this->pesan($prq, [$baris->id => 40], [
            'vendor_id' => '', 'new_vendor_name' => 'Toko Besi Baru', 'new_vendor_type' => 'shop',
            'external_po_no' => '', 'marketplace_order_no' => 'INV/2026/77', 'tracking_no' => 'JNE123',
        ]);
        $this->assertSame(VendorStatus::Provisional, $dua->vendor->status);
        $this->assertSame('INV/2026/77 · resi JNE123', $dua->reference());

        $this->assertSame(100.0, (float) $baris->refresh()->qty_ordered);
        $this->gagalPrq(fn () => $this->pesan($prq, [$baris->id => 1]), 'BR-REQ-08');
    }

    #[Test]
    public function tc_prq_05_grn_merujuk_catatan_pemesanan_menjadi_sebagian_lalu_dipenuhi(): void
    {
        $prq = $this->prqManual([['item_id' => $this->baut->id, 'qty_base' => 100]]);
        $ref = $this->barisPesanan($this->pesan($prq));

        // BR-GRN-01: vendor/gudang/item harus sama dengan catatan.
        $lain = Vendor::create(['code' => 'V-LAIN', 'name' => 'Vendor Lain', 'vendor_type' => VendorType::Company, 'status' => VendorStatus::Active, 'is_active' => true]);
        $this->gagalPrq(fn () => $this->grnDraf([['item_id' => $this->baut->id, 'qty_received' => 10, 'purchase_request_order_line_id' => $ref]], ['vendor_id' => $lain->id]), 'BR-GRN-01');
        $this->gagalPrq(fn () => $this->grnDraf([['item_id' => $this->kabel->id, 'qty_received' => 10, 'purchase_request_order_line_id' => $ref]]), 'BR-GRN-01');
        // BR-GRN-05: tidak melebihi sisa pesanan, termasuk draf GRN lain.
        $this->gagalPrq(fn () => $this->grnDraf([['item_id' => $this->baut->id, 'qty_received' => 101, 'purchase_request_order_line_id' => $ref]]), 'BR-GRN-05');
        $draf = $this->grnDraf([['item_id' => $this->baut->id, 'qty_received' => 70, 'purchase_request_order_line_id' => $ref]]);
        $this->gagalPrq(fn () => $this->grnDraf([['item_id' => $this->baut->id, 'qty_received' => 40, 'purchase_request_order_line_id' => $ref]]), 'BR-GRN-05');

        app(ReceiveGoodsReceipt::class)->handle($draf, $this->makeUser('warehouse_staff'));
        $this->assertSame(PurchaseRequestStatus::PartiallyFulfilled, $prq->refresh()->status);
        $this->assertSame(70.0, (float) $prq->lines()->sole()->qty_received);

        $kejadian = StockEvent::query()->where('event_type', StockEventType::GoodsReceived->value)->latest('id')->first();
        $this->assertSame($prq->number, $kejadian->payload['purchase_request_number'] ?? null);
        $this->assertSame('PO-2026-001', $kejadian->payload['external_po_no'] ?? null);

        $this->grnDiterima([['item_id' => $this->baut->id, 'qty_received' => 30, 'purchase_request_order_line_id' => $ref]]);
        $prq->refresh();
        $this->assertSame(PurchaseRequestStatus::Fulfilled, $prq->status);
        $this->assertNotNull($prq->fulfilled_at);
        $this->assertFalse(Gate::forUser($this->makeUser('pr_follow_up'))->allows('cancel', $prq));
    }

    #[Test]
    public function tc_prq_06_batal_dengan_alasan_dan_ditolak_setelah_ada_grn(): void
    {
        $pr = $this->makeUser('pr_follow_up');

        $prq = $this->prqManual();
        $this->gagalPrq(fn () => app(CancelPurchaseRequest::class)->handle($prq, null, null, $pr), 'BR-GEN-11');
        app(CancelPurchaseRequest::class)->handle($prq, $this->alasan(ReasonContext::Cancel), 'Tidak jadi', $pr);
        $this->assertSame(PurchaseRequestStatus::Cancelled, $prq->refresh()->status);
        $this->assertSame(1, StockEvent::query()->where('event_type', StockEventType::PurchaseRequestCancelled->value)->count());

        // Sudah ada barang diterima: tidak bisa dibatalkan (Katalog §2.15 "belum ada GRN").
        $kedua = $this->prqManual();
        $ref = $this->barisPesanan($this->pesan($kedua));
        $this->grnDiterima([['item_id' => $this->baut->id, 'qty_received' => 10, 'purchase_request_order_line_id' => $ref]]);
        $this->assertFalse(Gate::forUser($pr)->allows('cancel', $kedua->refresh()));
        $this->gagalPrq(fn () => app(CancelPurchaseRequest::class)->handle($kedua, $this->alasan(ReasonContext::Cancel), null, $pr), 'BR-GEN-01');

        // Izin: staf membuat tetapi tidak membatalkan; penindak lanjut memesan.
        $ketiga = $this->prqManual();
        $this->assertFalse(Gate::forUser($this->makeUser('warehouse_staff'))->allows('cancel', $ketiga));
        $this->assertFalse(Gate::forUser($this->makeUser('warehouse_staff'))->allows('order', $ketiga));
        $this->assertTrue(Gate::forUser($pr)->allows('order', $ketiga));
        $this->assertSame(1, PurchaseRequest::query()->where('warehouse_id', Warehouse::query()->where('code', 'CKG')->value('id'))->where('status', 'cancelled')->count());
    }
}

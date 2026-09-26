<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Enums\VendorType;
use App\Domain\Master\Models\Vendor;
use App\Domain\PurchaseRequest\Actions\CancelPurchaseRequest;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestStatus;
use App\Domain\PurchaseRequest\Models\PurchaseRequestOrder;
use App\Domain\Purchasing\Actions\ApprovePurchaseOrder;
use App\Domain\Purchasing\Actions\CancelPurchaseOrder;
use App\Domain\Purchasing\Actions\ClosePurchaseOrder;
use App\Domain\Purchasing\Actions\UpdatePurchaseOrderEta;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Warehouse\Models\Warehouse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Purchasing\Concerns\PurchasingFixtures;
use Tests\TenantTestCase;

/**
 * TC-PO-01 s.d. TC-PO-08 — Purchase Order dari PRQ (purchasing/02, Katalog
 * §2.17, D-28, A-208–A-215).
 */
class PurchaseOrderTest extends TenantTestCase
{
    use PurchasingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPurchasing();
    }

    #[Test]
    public function tc_po_01_po_draf_dari_prq_dengan_nilai(): void
    {
        $prq = $this->prqManual([['item_id' => $this->baut->id, 'qty_base' => 100]]);
        $po = $this->poDraf($prq, [['purchase_request_line_id' => $prq->lines()->sole()->id, 'qty_base' => 60, 'unit_price' => 1500]]);

        $this->assertMatchesRegularExpression('#^PO/CKG/\d{4}/0001$#', $po->number);
        $this->assertSame(PurchaseOrderStatus::Draft, $po->status);
        $this->assertSame(90000.0, (float) $po->total_amount);
        $this->assertSame(90000.0, (float) $po->lines()->sole()->line_amount);
        $this->assertSame(PurchaseRequestStatus::Approved, $prq->refresh()->status, 'PO draf belum menjadi catatan pemesanan.');
        $this->assertSame(0.0, (float) $prq->lines()->sole()->qty_ordered);
    }

    #[Test]
    public function tc_po_02_validasi_baris_dan_header(): void
    {
        $prq = $this->prqManual([['item_id' => $this->baut->id, 'qty_base' => 100]]);
        $id = $prq->lines()->sole()->id;
        $baris = fn (array $x = []) => [array_merge(['purchase_request_line_id' => $id, 'qty_base' => 10, 'unit_price' => 1500], $x)];

        $this->gagalPo(fn () => $this->poDraf($prq, []), 'BR-GEN-11');
        $this->gagalPo(fn () => $this->poDraf($prq, $baris(['qty_base' => -5])), 'BR-LED-02');
        $this->gagalPo(fn () => $this->poDraf($prq, $baris(['qty_base' => 101])), 'A-246');
        $this->gagalPo(fn () => $this->poDraf($prq, $baris(['unit_price' => 0])), 'A-211');

        $sementara = Vendor::create(['code' => 'V-SEM', 'name' => 'Toko Sementara', 'vendor_type' => VendorType::Shop, 'status' => VendorStatus::Provisional, 'is_active' => true]);
        $this->gagalPo(fn () => $this->poDraf($prq, $baris(), ['vendor_id' => $sementara->id]), 'BR-MST-05');

        $bks = $this->buatGudang('BKS', 'Gudang Bekasi');
        $this->gagalPo(fn () => $this->poDraf($prq, $baris(), ['warehouse_id' => $bks->id]), 'A-210');

        // Jumlah yang dipegang PO draf lain mengurangi sisa (A-210).
        $this->poDraf($prq, $baris(['qty_base' => 70]));
        $this->gagalPo(fn () => $this->poDraf($prq, $baris(['qty_base' => 31])), 'A-246');
        $this->assertSame(PurchaseOrderStatus::Draft, $this->poDraf($prq, $baris(['qty_base' => 30]))->status);
    }

    #[Test]
    public function tc_po_03_tanpa_aturan_langsung_disetujui_dan_menjadi_catatan_pemesanan(): void
    {
        $prq = $this->prqManual([['item_id' => $this->baut->id, 'qty_base' => 100]]);
        $po = $this->poDisetujui($prq, [['purchase_request_line_id' => $prq->lines()->sole()->id, 'qty_base' => 60, 'unit_price' => 1500]]);

        $this->assertSame(PurchaseOrderStatus::Approved, $po->status);
        $this->assertNull($po->approved_by, 'Disetujui otomatis (A-08).');

        $catatan = PurchaseRequestOrder::query()->where('purchase_order_id', $po->id)->sole();
        $this->assertSame($po->number, $catatan->external_po_no);
        $this->assertSame((int) $this->vendor->id, (int) $catatan->vendor_id);
        $this->assertSame($po->eta_date->toDateString(), $catatan->eta_date->toDateString());
        $this->assertSame((int) $po->lines()->sole()->id, (int) $catatan->lines()->sole()->purchase_order_line_id);

        $prq->refresh();
        $this->assertSame(PurchaseRequestStatus::Forwarded, $prq->status);
        $this->assertSame(60.0, (float) $prq->lines()->sole()->qty_ordered);
    }

    #[Test]
    public function tc_po_04_aturan_nilai_po_berlapis(): void
    {
        $manajemen = $this->makeUser('management');
        $this->aturan(ApprovalDocumentType::PurchaseOrder, [$this->lapisUser($manajemen)], ['order_value_min' => 1000000]);

        $prq = $this->prqManual([['item_id' => $this->baut->id, 'qty_base' => 2000]]);
        $id = $prq->lines()->sole()->id;

        $kecil = $this->poDisetujui($prq, [['purchase_request_line_id' => $id, 'qty_base' => 60, 'unit_price' => 1500]]);
        $this->assertSame(PurchaseOrderStatus::Approved, $kecil->status, 'Rp 90.000 di bawah batas nilai.');

        $besar = $this->poDisetujui($prq, [['purchase_request_line_id' => $id, 'qty_base' => 1000, 'unit_price' => 1500]]);
        $this->assertSame(PurchaseOrderStatus::PendingApproval, $besar->status, 'Rp 1,5 juta ≥ Rp 1 juta.');
        $this->assertSame(1500000.0, (float) $besar->snapshot->context['order_value']);

        $this->gagalPo(fn () => app(ApprovePurchaseOrder::class)->approve($besar, $this->pembeli), 'BR-APR-03');
        app(ApprovePurchaseOrder::class)->approve($besar, $manajemen);
        $this->assertSame(PurchaseOrderStatus::Approved, $besar->refresh()->status);
        $this->assertSame((int) $manajemen->id, (int) $besar->approved_by);
        $this->assertSame(1060.0, (float) $prq->lines()->sole()->refresh()->qty_ordered);

        $tolak = $this->poDisetujui($prq, [['purchase_request_line_id' => $id, 'qty_base' => 900, 'unit_price' => 1500]]);
        $this->gagalPo(fn () => app(ApprovePurchaseOrder::class)->reject($tolak, null, null, $manajemen), 'BR-GEN-11');
        app(ApprovePurchaseOrder::class)->reject($tolak, $this->alasan(ReasonContext::Reject), 'Harga terlalu tinggi', $manajemen);
        $this->assertSame(PurchaseOrderStatus::Rejected, $tolak->refresh()->status);
        $this->assertSame(0, PurchaseRequestOrder::query()->where('purchase_order_id', $tolak->id)->count());

        // Kondisi nilai hanya untuk PO: jenis dokumen gudang membuangnya (BR-APR-07).
        $this->assertNotContains('order_value_min', ApprovalDocumentType::PurchaseRequest->conditions());
        $this->assertContains('order_value_min', ApprovalDocumentType::PurchaseOrder->conditions());
    }

    #[Test]
    public function tc_po_05_grn_menyelesaikan_po_bertahap_tanpa_harga_di_kejadian(): void
    {
        $prq = $this->prqManual([['item_id' => $this->baut->id, 'qty_base' => 60]]);
        $po = $this->poDisetujui($prq);
        $ref = (int) PurchaseRequestOrder::query()->where('purchase_order_id', $po->id)->sole()->lines()->sole()->id;

        $this->gagalPo(fn () => $this->grnDraf([['item_id' => $this->baut->id, 'qty_received' => 61, 'purchase_request_order_line_id' => $ref]]), 'BR-GRN-05');

        $this->grnDiterima([['item_id' => $this->baut->id, 'qty_received' => 40, 'purchase_request_order_line_id' => $ref]]);
        $this->assertSame(PurchaseOrderStatus::PartiallyFulfilled, $po->refresh()->status);
        $this->assertSame(40.0, (float) $po->lines()->sole()->qty_received);
        $this->assertSame(PurchaseRequestStatus::PartiallyFulfilled, $prq->refresh()->status);

        $kejadian = StockEvent::query()->where('event_type', StockEventType::GoodsReceived->value)->latest('id')->first();
        $this->assertSame($po->number, $kejadian->payload['external_po_no'] ?? null);
        $this->assertEmpty(array_intersect(['price', 'unit_price', 'amount', 'line_amount', 'total_amount'], array_keys($kejadian->payload)), 'D-07: kejadian stok tanpa harga.');

        $this->grnDiterima([['item_id' => $this->baut->id, 'qty_received' => 20, 'purchase_request_order_line_id' => $ref]]);
        $po->refresh();
        $this->assertSame(PurchaseOrderStatus::Completed, $po->status);
        $this->assertNotNull($po->completed_at);
        $this->assertSame(PurchaseRequestStatus::Fulfilled, $prq->refresh()->status);
    }

    #[Test]
    public function tc_po_11_pesan_lebih_dari_prq_beralasan_menjadi_stok_biasa(): void
    {
        $prq = $this->prqManual([['item_id' => $this->baut->id, 'qty_base' => 100]]);
        $id = $prq->lines()->sole()->id;

        // A-246: di atas sisa tanpa alasan ditolak, dengan alasan lolos.
        $this->gagalPo(fn () => $this->poDraf($prq, [['purchase_request_line_id' => $id, 'qty_base' => 150, 'unit_price' => 1500]]), 'A-246');

        $po = $this->poDisetujui($prq, [['purchase_request_line_id' => $id, 'qty_base' => 150, 'unit_price' => 1500, 'over_order_reason' => 'MOQ vendor 150']]);
        $baris = $po->lines()->sole();

        $this->assertSame(PurchaseOrderStatus::Approved, $po->status);
        $this->assertSame(50.0, (float) $baris->qty_over_request);
        $this->assertSame('MOQ vendor 150', $baris->over_order_reason);
        $this->assertSame(225000.0, (float) $po->total_amount);

        // Catatan pemesanan memegang jumlah PO penuh; GRN boleh sampai 150, tidak lebih (A-214).
        $catatan = PurchaseRequestOrder::query()->where('purchase_order_id', $po->id)->sole()->lines()->sole();
        $this->assertSame(150.0, (float) $catatan->qty_ordered);
        $this->assertSame(150.0, (float) $prq->lines()->sole()->refresh()->qty_ordered);
        $this->gagalPo(fn () => $this->grnDraf([['item_id' => $this->baut->id, 'qty_received' => 151, 'purchase_request_order_line_id' => $catatan->id]]), 'BR-GRN-05');

        $grn = $this->grnDiterima([['item_id' => $this->baut->id, 'qty_received' => 150, 'purchase_request_order_line_id' => $catatan->id]]);

        $this->assertSame(150.0, (float) $grn->lines()->sole()->qty_received);
        $this->assertSame(PurchaseOrderStatus::Completed, $po->refresh()->status);
        $this->assertSame(PurchaseRequestStatus::Fulfilled, $prq->refresh()->status, 'Kebutuhan 100 terpenuhi; 50 sisanya stok biasa.');

        // Kelebihan karena kemasan juga tercatat per baris.
        $prq2 = $this->prqManual([['item_id' => $this->baut->id, 'qty_base' => 10]]);
        $draf = $this->poDraf($prq2, [['purchase_request_line_id' => $prq2->lines()->sole()->id, 'qty_base' => 12, 'unit_price' => 1500, 'over_order_reason' => 'Kemasan isi 12']]);
        $this->assertSame(2.0, (float) $draf->lines()->sole()->qty_over_request);
    }

    #[Test]
    public function tc_po_06_batal_melepas_pesanan_dan_ditolak_setelah_barang_datang(): void
    {
        $prq = $this->prqManual([['item_id' => $this->baut->id, 'qty_base' => 100]]);
        $po = $this->poDisetujui($prq);
        $cancel = app(CancelPurchaseOrder::class);

        $this->gagalPo(fn () => $cancel->handle($po, null, null, $this->pembeli), 'BR-GEN-11');
        $cancel->handle($po, $this->alasan(ReasonContext::Cancel), 'Vendor tidak sanggup', $this->pembeli);

        $po->refresh();
        $this->assertSame(PurchaseOrderStatus::Cancelled, $po->status);
        $this->assertSame(100.0, (float) $po->lines()->sole()->qty_cancelled);
        $this->assertSame(0.0, (float) $prq->lines()->sole()->refresh()->qty_ordered, 'Sisa bisa dipesan lagi (po_cancelled).');
        $this->assertSame(PurchaseRequestStatus::Forwarded, $prq->refresh()->status);

        // Dipesan lagi lewat PO baru, sebagian diterima: batal ditolak, pakai Tutup sisa.
        $kedua = $this->poDisetujui($prq);
        $ref = (int) PurchaseRequestOrder::query()->where('purchase_order_id', $kedua->id)->sole()->lines()->sole()->id;
        $this->grnDiterima([['item_id' => $this->baut->id, 'qty_received' => 10, 'purchase_request_order_line_id' => $ref]]);
        $this->gagalPo(fn () => $cancel->handle($kedua->refresh(), $this->alasan(ReasonContext::Cancel), null, $this->pembeli), 'BR-GEN-01');

        // Draf dan menunggu approval juga bisa dibatalkan; approval ditarik.
        $this->aturan(ApprovalDocumentType::PurchaseOrder, [$this->lapisUser($this->makeUser('management'))]);
        $prq2 = $this->prqManual([['item_id' => $this->baut->id, 'qty_base' => 5]]);
        $menunggu = $this->poDisetujui($prq2);
        $this->assertSame(PurchaseOrderStatus::PendingApproval, $menunggu->status);
        $cancel->handle($menunggu, $this->alasan(ReasonContext::Cancel), null, $this->pembeli);
        $this->assertFalse($menunggu->refresh()->isAwaitingApproval());
        $this->assertSame(PurchaseOrderStatus::Cancelled, $menunggu->status);
    }

    #[Test]
    public function tc_po_07_tutup_sisa_melepas_yang_belum_datang(): void
    {
        $prq = $this->prqManual([['item_id' => $this->baut->id, 'qty_base' => 100]]);
        $po = $this->poDisetujui($prq);
        $ref = (int) PurchaseRequestOrder::query()->where('purchase_order_id', $po->id)->sole()->lines()->sole()->id;
        $close = app(ClosePurchaseOrder::class);

        $this->gagalPo(fn () => $close->handle($po, $this->alasan(ReasonContext::Cancel), null, $this->pembeli), 'BR-GEN-01');

        $this->grnDiterima([['item_id' => $this->baut->id, 'qty_received' => 30, 'purchase_request_order_line_id' => $ref]]);
        $this->gagalPo(fn () => $close->handle($po->refresh(), null, null, $this->pembeli), 'BR-GEN-11');
        $close->handle($po, $this->alasan(ReasonContext::Cancel), 'Stok vendor habis', $this->pembeli);

        $po->refresh();
        $this->assertSame(PurchaseOrderStatus::ClosedShort, $po->status);
        $this->assertSame(70.0, (float) $po->lines()->sole()->qty_cancelled);

        $baris = $prq->lines()->sole()->refresh();
        $this->assertSame(30.0, (float) $baris->qty_ordered);
        $this->assertSame(70.0, $baris->unorderedQty());

        // Sisa 70 dipesan lagi ke PO baru.
        $baru = $this->poDisetujui($prq);
        $this->assertSame(70.0, (float) $baru->lines()->sole()->qty_base);
        $this->assertSame(PurchaseOrderStatus::Approved, $baru->status);
    }

    #[Test]
    public function tc_po_08_ubah_eta_dan_prq_ber_po_terbuka_tidak_bisa_dibatalkan(): void
    {
        $prq = $this->prqManual([['item_id' => $this->baut->id, 'qty_base' => 10]]);
        $po = $this->poDisetujui($prq);

        $baru = now()->addDays(14)->toDateString();
        app(UpdatePurchaseOrderEta::class)->handle($po, $baru, $this->pembeli);
        $this->assertSame($baru, $po->refresh()->eta_date->toDateString());
        $this->assertSame($baru, PurchaseRequestOrder::query()->where('purchase_order_id', $po->id)->sole()->eta_date->toDateString());

        $this->gagalPo(fn () => app(CancelPurchaseRequest::class)->handle($prq->refresh(), $this->alasan(ReasonContext::Cancel), null, $this->pembeli), 'A-215');
        $this->assertFalse($this->pembeli->can('cancel', $prq));

        // Setelah PO dibatalkan PRQ boleh dibatalkan lagi.
        app(CancelPurchaseOrder::class)->handle($po, $this->alasan(ReasonContext::Cancel), null, $this->pembeli);
        app(CancelPurchaseRequest::class)->handle($prq->refresh(), $this->alasan(ReasonContext::Cancel), null, $this->pembeli);
        $this->assertSame(PurchaseRequestStatus::Cancelled, $prq->refresh()->status);

        $this->assertInstanceOf(Warehouse::class, $po->warehouse);
        $this->assertSame(1, PurchaseOrder::query()->count());
    }
}

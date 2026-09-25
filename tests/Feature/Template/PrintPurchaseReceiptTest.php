<?php

declare(strict_types=1);

namespace Tests\Feature\Template;

use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Models\DocumentTemplate;
use App\Domain\Template\Support\DocumentPrinter;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\PurchaseRequest\Concerns\PurchaseFixtures;
use Tests\TenantTestCase;

/**
 * TC-TPL-16 — Purchase Request dan Bukti Penerimaan (GRN) tercetak (A-232):
 * route 200, isi tampil (termasuk catatan pemesanan PRQ), tanpa nilai uang
 * (D-07), template bawaan ikut dibuat untuk jenis baru.
 */
class PrintPurchaseReceiptTest extends TenantTestCase
{
    use PurchaseFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPembelian();
    }

    #[Test]
    public function tc_tpl_16_purchase_request_dan_grn_tercetak(): void
    {
        $prq = $this->prqManual([['item_id' => $this->baut->id, 'qty_base' => 40]]);
        $grn = $this->grnDiterima([['item_id' => $this->kabel->id, 'qty_received' => 8]]);

        $admin = $this->makeUser('company_admin');
        $this->actingAs($admin)->get($this->tenantUrl('print/purchase-request/'.$prq->id))->assertOk();
        $this->actingAs($admin)->get($this->tenantUrl('print/goods-receipt/'.$grn->id))->assertOk();

        $cetakPrq = app(DocumentPrinter::class)->view(DocumentTemplateType::PurchaseRequest, PurchaseRequest::query()->findOrFail($prq->id))->render();
        $this->assertStringContainsString($prq->number, $cetakPrq);
        $this->assertStringContainsString('BAUT-M12', $cetakPrq);
        $this->assertStringContainsString($this->gudang->name, $cetakPrq);
        $this->tanpaHarga($cetakPrq);

        $cetakGrn = app(DocumentPrinter::class)->view(DocumentTemplateType::GoodsReceipt, GoodsReceipt::query()->findOrFail($grn->id))->render();
        $this->assertStringContainsString($grn->number, $cetakGrn);
        $this->assertStringContainsString('KABEL', $cetakGrn);
        $this->assertStringContainsString('SJV-001', $cetakGrn);
        $this->assertStringContainsString($this->vendor->name, $cetakGrn);
        $this->tanpaHarga($cetakGrn);

        // Template bawaan untuk empat jenis baru ikut dibuat; segmen URL tetap konsisten.
        DocumentTemplate::ensureDefaults();
        $this->assertSame(count(DocumentTemplateType::cases()), DocumentTemplate::query()->count());
        $this->assertSame(DocumentTemplateType::GoodsReturn, DocumentTemplateType::fromRoute('goods-return'));
        $this->assertSame(DocumentTemplateType::Transfer, DocumentTemplateType::fromRoute('transfer'));

        $this->actingAs($admin)->get($this->tenantUrl('receipts/'.$grn->id))->assertOk()
            ->assertSee(route('print.document', ['type' => 'goods-receipt', 'id' => $grn->id]));
        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl('print/purchase-request/'.$prq->id))->assertForbidden();
    }

    private function tanpaHarga(string $html): void
    {
        // Gambar QR/logo base64 dibuang dulu: urutan acaknya (bergantung id dokumen) bisa memuat "Rp".
        $html = (string) preg_replace('#data:image/[a-z]+;base64,[A-Za-z0-9+/=]+#', '', $html);

        foreach (['Rp', 'harga', 'Harga', 'Total', 'total harga'] as $kata) {
            $this->assertStringNotContainsString($kata, $html, 'Cetakan tidak boleh memuat nilai uang (D-07).');
        }
    }
}

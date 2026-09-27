<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Domain\PurchaseRequest\Enums\PurchaseRequestStatus;
use App\Domain\PurchaseRequest\Models\PurchaseRequestOrder;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Livewire\PurchaseOrderDetail;
use App\Domain\Receipt\Livewire\ReceiptForm;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockEvent;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Purchasing\Concerns\PurchasingFixtures;
use Tests\TenantTestCase;

/**
 * TC-GRN-22 — bonus vendor (mis. promo beli 2 gratis 1) diterima sebagai baris
 * GRN bertanda bonus: masuk stok, tidak mengurangi pesanan PO/PRQ (A-267,
 * BR-GRN-05).
 */
class VendorBonusTest extends TenantTestCase
{
    use PurchasingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPurchasing();
    }

    #[Test]
    public function tc_grn_22_bonus_vendor_masuk_stok_di_luar_pesanan(): void
    {
        $prq = $this->prqManual([['item_id' => $this->baut->id, 'qty_base' => 100]]);
        $po = $this->poDisetujui($prq);
        $ref = (int) PurchaseRequestOrder::query()->where('purchase_order_id', $po->id)->sole()->lines()->sole()->id;

        // Kelebihan pada baris pesanan tetap ditolak, dengan petunjuk bonus.
        $this->gagalPo(fn () => $this->grnDraf([['item_id' => $this->baut->id, 'qty_received' => 150, 'purchase_request_order_line_id' => $ref]]), 'BR-GRN-05');

        // Baris bonus tanpa keterangan, atau tetap merujuk pesanan, ditolak.
        $this->gagalPo(fn () => $this->grnDraf([
            ['item_id' => $this->baut->id, 'qty_received' => 100, 'purchase_request_order_line_id' => $ref],
            ['item_id' => $this->baut->id, 'qty_received' => 50, 'is_bonus' => true],
        ]), 'A-267');
        $this->gagalPo(fn () => $this->grnDraf([
            ['item_id' => $this->baut->id, 'qty_received' => 50, 'is_bonus' => true, 'notes' => 'Promo', 'purchase_request_order_line_id' => $ref],
        ]), 'A-267');

        $sebelum = (float) StockBalance::query()->withoutGlobalScopes()->where('item_id', $this->baut->id)->sum('qty_base');

        $grn = $this->grnDiterima([
            ['item_id' => $this->baut->id, 'qty_received' => 100, 'purchase_request_order_line_id' => $ref],
            ['item_id' => $this->baut->id, 'qty_received' => 50, 'is_bonus' => true, 'notes' => 'Promo beli 2 gratis 1'],
        ]);

        $this->assertSame(150.0, round((float) StockBalance::query()->withoutGlobalScopes()->where('item_id', $this->baut->id)->sum('qty_base') - $sebelum, 4), 'Bonus ikut masuk stok.');
        $po->refresh();
        $this->assertSame(PurchaseOrderStatus::Completed, $po->status);
        $this->assertSame(100.0, (float) $po->lines()->sole()->qty_received, 'Bonus tidak dihitung sebagai barang PO.');
        $this->assertSame(PurchaseRequestStatus::Fulfilled, $prq->refresh()->status);

        $bonus = $grn->lines()->where('is_bonus', true)->sole();
        $this->assertNull($bonus->purchase_request_order_line_id);
        $kejadian = StockEvent::query()->where('event_type', StockEventType::GoodsReceived->value)->where('source_number', $grn->number)->get();
        $this->assertCount(1, $kejadian->filter(fn ($k) => ($k->payload['is_bonus'] ?? false) === true), 'Tepat satu kejadian bertanda bonus.');
        $this->assertArrayNotHasKey('external_po_no', $kejadian->first(fn ($k) => ($k->payload['is_bonus'] ?? false) === true)->payload);

        Livewire::actingAs($this->pembeli)->test(PurchaseOrderDetail::class, ['purchaseOrder' => $po])
            ->assertSee(__('Bonus dari vendor'))
            ->assertSee('Promo beli 2 gratis 1');
    }

    #[Test]
    public function tc_grn_22b_form_memisahkan_kelebihan_menjadi_baris_bonus(): void
    {
        $prq = $this->prqManual([['item_id' => $this->baut->id, 'qty_base' => 100]]);
        $po = $this->poDisetujui($prq);
        $ref = (int) PurchaseRequestOrder::query()->where('purchase_order_id', $po->id)->sole()->lines()->sole()->id;

        Livewire::actingAs($this->makeUser('warehouse_staff'))
            ->test(ReceiptForm::class)
            ->set('form.warehouse_id', (string) $this->gudang->id)
            ->set('form.vendor_id', (string) $this->vendor->id)
            ->call('pakaiPesanan', $ref)
            ->set('rows.0.qty', '150')
            ->assertSee(__('Pisahkan kelebihan jadi bonus'))
            ->call('pisahkanBonus', 0)
            ->assertSet('rows.0.qty', '100')
            ->assertSet('rows.1.qty', '50')
            ->assertSet('rows.1.bonus', true)
            ->assertSet('rows.1.order_line_id', '')
            ->set('rows.1.notes', 'Promo beli 2 gratis 1')
            ->call('simpan')
            ->assertSet('ruleError', '')
            ->assertRedirect();

        // Mencentang bonus pada baris pesanan melepas rujukannya.
        Livewire::actingAs($this->makeUser('warehouse_staff'))
            ->test(ReceiptForm::class)
            ->set('form.warehouse_id', (string) $this->gudang->id)
            ->set('form.vendor_id', (string) $this->vendor->id)
            ->call('pakaiPesanan', $ref)
            ->set('rows.0.bonus', true)
            ->assertSet('rows.0.order_line_id', '');
    }
}

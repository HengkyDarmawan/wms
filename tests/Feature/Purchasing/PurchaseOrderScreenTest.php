<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Purchasing\Actions\SaveVendorPrice;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Livewire\PurchaseOrderDetail;
use App\Domain\Purchasing\Livewire\PurchaseOrderForm;
use App\Domain\Purchasing\Livewire\PurchaseOrderList;
use App\Domain\Purchasing\Livewire\VendorPriceList;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\VendorPrice;
use App\Domain\Purchasing\Support\VendorPrices;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Support\DocumentPrinter;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Purchasing\Concerns\PurchasingFixtures;
use Tests\TenantTestCase;

/**
 * TC-PO-09, TC-PO-10, TC-VPR-01 — izin & cakupan, layar PO dari PRQ sampai
 * cetak, dan harga beli vendor (purchasing/02 §2, §6, A-211, A-216, A-217).
 */
class PurchaseOrderScreenTest extends TenantTestCase
{
    use PurchasingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPurchasing();
    }

    #[Test]
    public function tc_po_09_izin_cakupan_dan_menu(): void
    {
        $prq = $this->prqManual([['item_id' => $this->baut->id, 'qty_base' => 10]]);
        $po = $this->poDisetujui($prq);

        $this->actingAs($this->pembeli)->get($this->tenantUrl('purchase-orders'))->assertOk()->assertSee($po->number)->assertSee('Rp 15.000');
        $this->actingAs($this->pembeli)->get($this->tenantUrl('purchase-orders/create'))->assertOk();
        $this->actingAs($this->pembeli)->get($this->tenantUrl('purchase-orders/'.$po->id))->assertOk();
        $this->actingAs($this->pembeli)->get($this->tenantUrl('vendor-prices'))->assertOk()->assertSee('Rp 1.500');
        $this->actingAs($this->pembeli)->get($this->tenantUrl('/'))->assertSee(route('purchase-orders.index'))->assertSee(route('vendor-prices.index'));

        $manajemen = $this->makeUser('management');
        $this->actingAs($manajemen)->get($this->tenantUrl('purchase-orders/'.$po->id))->assertOk();
        $this->actingAs($manajemen)->get($this->tenantUrl('purchase-orders/create'))->assertForbidden();

        // Kepala Gudang dan staf tidak melihat harga (A-216).
        $kepala = $this->makeUser('warehouse_head', ScopeType::Warehouse, $this->gudang->id);
        $this->actingAs($kepala)->get($this->tenantUrl('purchase-orders'))->assertForbidden();
        $this->actingAs($kepala)->get($this->tenantUrl('vendor-prices'))->assertForbidden();
        $this->actingAs($kepala)->get($this->tenantUrl('/'))->assertDontSee(route('purchase-orders.index'));
        $this->actingAs($kepala)->get($this->tenantUrl('purchase-requests/'.$prq->id))->assertOk()->assertDontSee('Rp ')->assertDontSee(__('Buat PO'));

        // Cakupan gudang tujuan: pembeli gudang lain = 404.
        $bks = $this->buatGudang('BKS', 'Gudang Bekasi');
        $pembeliBks = $this->makeUser('pr_follow_up', ScopeType::Warehouse, $bks->id);
        $this->actingAs($pembeliBks)->get($this->tenantUrl('purchase-orders/'.$po->id))->assertNotFound();

        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl('purchase-orders'))->assertForbidden();
    }

    #[Test]
    public function tc_po_10_layar_form_dari_prq_ajukan_setujui_dan_cetak(): void
    {
        $manajemen = $this->makeUser('management');
        $this->aturan(ApprovalDocumentType::PurchaseOrder, [$this->lapisUser($manajemen)], ['order_value_min' => 100000]);

        $prq = $this->prqManual([['item_id' => $this->baut->id, 'qty_base' => 100], ['item_id' => $this->semen->id, 'qty_base' => 20]]);
        [$baut, $semen] = $prq->lines()->orderBy('id')->get()->all();

        $this->actingAs($this->pembeli)->get($this->tenantUrl('purchase-requests/'.$prq->id))->assertOk()->assertSee(__('Buat PO'));

        Livewire::withQueryParams(['prq' => $prq->id])
            ->actingAs($this->pembeli)
            ->test(PurchaseOrderForm::class)
            ->assertSet('form.warehouse_id', (string) $this->gudang->id)
            ->assertSet('qty.'.$baut->id, '100')
            ->set('form.vendor_id', (string) $this->vendor->id)
            ->assertSet('price.'.$baut->id, '1500')
            ->assertSee('Rp 150.000')
            ->set('qty.'.$semen->id, '20')
            ->call('simpanDanAjukan')
            ->assertSet('ruleCode', 'A-211')
            ->set('price.'.$semen->id, '65000')
            ->call('simpanDanAjukan')
            ->assertSet('ruleError', '')
            ->assertRedirect();

        $po = PurchaseOrder::query()->latest('id')->firstOrFail();
        $this->assertSame(PurchaseOrderStatus::PendingApproval, $po->status);
        $this->assertSame(1450000.0, (float) $po->total_amount);

        Livewire::actingAs($this->pembeli)->test(PurchaseOrderList::class)->assertSee($po->number)->assertSee('Rp 1.450.000');
        Livewire::actingAs($this->pembeli)->test(PurchaseOrderDetail::class, ['purchaseOrder' => $po])->assertDontSee(__('Setujui'));

        Livewire::actingAs($manajemen)
            ->test(PurchaseOrderDetail::class, ['purchaseOrder' => $po])
            ->assertSee(__('Setujui'))
            ->call('setujui')
            ->assertSet('ruleError', '');
        $this->assertSame(PurchaseOrderStatus::Approved, $po->refresh()->status);

        Livewire::actingAs($this->pembeli)
            ->test(PurchaseOrderDetail::class, ['purchaseOrder' => $po])
            ->call('mintaDialog', 'eta')
            ->set('form.eta_date', now()->addDays(3)->toDateString())
            ->call('simpanEta')
            ->assertSet('dialog', '');

        // Cetak PO: bernilai uang, hanya pemegang po.view (A-217).
        $html = app(DocumentPrinter::class)->view(DocumentTemplateType::PurchaseOrder, $po)->render();
        $this->assertStringContainsString($po->number, $html);
        $this->assertStringContainsString('Rp 1.450.000', $html);
        $this->assertStringContainsString(__('nilai dalam Rupiah'), $html);
        $this->actingAs($this->pembeli)->get($this->tenantUrl('print/purchase-order/'.$po->id))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->makeUser('warehouse_head', ScopeType::Warehouse, $this->gudang->id))->get($this->tenantUrl('print/purchase-order/'.$po->id))->assertForbidden();
    }

    #[Test]
    public function tc_vpr_01_harga_beli_vendor_berganti_dengan_riwayat(): void
    {
        $simpan = app(SaveVendorPrice::class);
        $kemarin = now()->subDay()->toDateString();

        $this->gagalPo(fn () => $simpan->handle(['vendor_id' => $this->vendor->id, 'item_id' => $this->semen->id, 'unit_price' => 0], $this->pembeli), 'A-211');

        $simpan->handle(['vendor_id' => $this->vendor->id, 'item_id' => $this->semen->id, 'unit_price' => 60000, 'valid_from' => $kemarin], $this->pembeli);
        $simpan->handle(['vendor_id' => $this->vendor->id, 'item_id' => $this->semen->id, 'unit_price' => 62500, 'valid_from' => $kemarin], $this->pembeli);
        $simpan->handle(['vendor_id' => $this->vendor->id, 'item_id' => $this->semen->id, 'unit_price' => 70000, 'valid_from' => now()->addMonth()->toDateString()], $this->pembeli);

        $this->assertSame(3, VendorPrice::query()->where('item_id', $this->semen->id)->count(), 'Riwayat tidak dihapus (P-03).');
        $this->assertSame(62500.0, (float) app(VendorPrices::class)->current((int) $this->vendor->id, (int) $this->semen->id)->unit_price, 'Harga bertanggal sama digantikan; harga masa depan belum berlaku.');

        Livewire::actingAs($this->pembeli)
            ->test(VendorPriceList::class)
            ->assertSee('Rp 62.500')
            ->call('buat')
            ->set('form.vendor_id', (string) $this->vendor->id)
            ->set('form.item_id', (string) $this->pipa->id)
            ->set('form.unit_price', '14500')
            ->call('simpan')
            ->assertHasNoErrors()
            ->assertSee('Rp 14.500');

        $pipa = VendorPrice::query()->where('item_id', $this->pipa->id)->sole();
        Livewire::actingAs($this->pembeli)->test(VendorPriceList::class)->call('nonaktifkan', $pipa->id);
        $this->assertFalse($pipa->refresh()->is_active);

        Livewire::actingAs($this->makeUser('management'))->test(VendorPriceList::class)->assertOk()->assertDontSee(__('Harga baru'));
        $this->actingAs($this->makeUser('warehouse_staff'))->get($this->tenantUrl('vendor-prices'))->assertForbidden();
    }
}

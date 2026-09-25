<?php

declare(strict_types=1);

namespace Tests\Feature\PurchaseRequest;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ItemVendor;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestStatus;
use App\Domain\PurchaseRequest\Livewire\PurchaseRequestDetail;
use App\Domain\PurchaseRequest\Livewire\PurchaseRequestForm;
use App\Domain\PurchaseRequest\Livewire\PurchaseRequestList;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\Receipt\Livewire\ReceiptForm;
use App\Domain\Receipt\Models\GoodsReceipt;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\PurchaseRequest\Concerns\PurchaseFixtures;
use Tests\TenantTestCase;

/**
 * TC-PRQ-10 s.d. TC-PRQ-12 — izin layar & menu (BR-GEN-09, BR-ACC-05), form
 * PRQ manual → detail → catatan pemesanan → batal, dan form GRN vendor yang
 * menawarkan baris catatan pemesanan (A-174).
 */
class PurchaseRequestScreenTest extends TenantTestCase
{
    use PurchaseFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPembelian();
    }

    #[Test]
    public function tc_prq_10_izin_layar_cakupan_dan_menu(): void
    {
        $prq = $this->prqManual();
        $staf = $this->makeUser('warehouse_staff');

        foreach (['purchase-requests', 'purchase-requests/create', 'purchase-requests/'.$prq->id] as $url) {
            $this->actingAs($staf)->get($this->tenantUrl($url))->assertOk();
        }
        $this->actingAs($staf)->get($this->tenantUrl('purchase-requests'))->assertSee($prq->number);
        // Hanya draf yang bisa ditinjau, oleh pemegang pr.submit.
        $this->actingAs($this->makeUser('warehouse_head'))->get($this->tenantUrl('purchase-requests/'.$prq->id.'/edit'))->assertForbidden();

        $pr = $this->makeUser('pr_follow_up');
        $this->actingAs($pr)->get($this->tenantUrl('purchase-requests/'.$prq->id))->assertOk()->assertSee(__('Catat pemesanan'));
        $this->actingAs($pr)->get($this->tenantUrl('purchase-requests/create'))->assertForbidden();
        $this->actingAs($this->makeUser('management'))->get($this->tenantUrl('purchase-requests/create'))->assertForbidden();
        foreach (['internal_requester', 'driver'] as $role) {
            $this->actingAs($this->makeUser($role))->get($this->tenantUrl('purchase-requests'))->assertForbidden();
        }

        // BR-ACC-05: gudang lain tidak melihat PRQ CKG.
        $bks = $this->buatGudang('BKS', 'Gudang Bekasi');
        $stafBks = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $bks->id);
        $this->actingAs($stafBks)->get($this->tenantUrl('purchase-requests/'.$prq->id))->assertNotFound();
        $this->actingAs($stafBks)->get($this->tenantUrl('purchase-requests'))->assertOk()->assertDontSee($prq->number);

        // Menu & palet mengikuti izin.
        $this->actingAs($staf)->get($this->tenantUrl('/'))->assertOk()
            ->assertSee(route('purchase-requests.index'))->assertSee(__('PRQ manual'));
        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl('/'))->assertOk()
            ->assertDontSee(route('purchase-requests.index'));
    }

    #[Test]
    public function tc_prq_11_form_detail_pemesanan_dan_batal_lewat_layar(): void
    {
        $staf = $this->makeUser('warehouse_staff');

        Livewire::actingAs($staf)->test(PurchaseRequestForm::class)
            ->assertOk()
            ->set('form.warehouse_id', (string) $this->gudang->id)
            ->set('rows.0.item_id', (string) $this->baut->id)
            ->set('rows.0.qty_base', '0')
            ->call('simpan')
            ->assertSet('ruleCode', 'BR-LED-02')
            ->set('rows.0.qty_base', '80')
            ->call('tambahBaris')
            ->set('rows.1.item_id', (string) $this->semen->id)
            ->set('rows.1.qty_base', '12')
            ->call('simpan')
            ->assertSet('ruleError', '')
            ->assertRedirect();

        $prq = PurchaseRequest::query()->latest('id')->firstOrFail();
        $this->assertSame(PurchaseRequestStatus::Approved, $prq->status);
        $this->assertNull($prq->project_id);

        Livewire::actingAs($staf)->test(PurchaseRequestList::class)->assertOk()->assertSee($prq->number);

        $pr = $this->makeUser('pr_follow_up');
        $semen = $prq->lines()->where('item_id', $this->semen->id)->sole();
        ItemVendor::create(['item_id' => $this->baut->id, 'vendor_id' => $this->vendor->id, 'priority' => 1]);

        Livewire::actingAs($pr)->test(PurchaseRequestDetail::class, ['purchaseRequest' => $prq])
            ->assertSee(__('Catat pemesanan'))
            ->call('mintaDialog', 'pesan')
            ->assertSet('orderQty.'.$semen->id, '12')
            ->assertSet('order.vendor_id', (string) $this->vendor->id)
            ->assertSee(__('vendor tetap item'))
            ->set('orderQty.'.$semen->id, '0')
            ->set('order.external_po_no', 'PO-LAYAR-1')
            ->call('catatPesanan')
            ->assertSet('ruleError', '')
            ->assertSet('dialog', '')
            ->assertSee('PO-LAYAR-1');

        $this->assertSame(PurchaseRequestStatus::Forwarded, $prq->refresh()->status);
        $this->assertSame(0.0, (float) $semen->refresh()->qty_ordered);

        Livewire::actingAs($pr)->test(PurchaseRequestDetail::class, ['purchaseRequest' => $prq])
            ->call('mintaDialog', 'batal')
            ->call('batalkan')
            ->assertHasErrors('form.reason')
            ->set('form.reason', (string) ReasonCode::query()->where('context', ReasonContext::Cancel->value)->value('code'))
            ->call('batalkan')
            ->assertSet('ruleError', '');

        $this->assertSame(PurchaseRequestStatus::Cancelled, $prq->refresh()->status);
    }

    #[Test]
    public function tc_prq_12_form_grn_vendor_menawarkan_baris_catatan_pemesanan(): void
    {
        $prq = $this->prqManual([['item_id' => $this->baut->id, 'qty_base' => 50]]);
        $order = $this->pesan($prq);
        $ref = $this->barisPesanan($order);
        $staf = $this->makeUser('warehouse_staff');

        Livewire::actingAs($staf)->test(ReceiptForm::class)
            ->set('form.warehouse_id', (string) $this->gudang->id)
            ->set('form.vendor_id', (string) $this->vendor->id)
            ->set('form.vendor_doc_no', 'SJV-PRQ')
            ->assertSee($prq->number)
            ->call('pakaiPesanan', $ref)
            ->assertSet('rows.0.item_id', (string) $this->baut->id)
            ->assertSet('rows.0.qty', '50')
            ->assertSet('rows.0.order_line_id', (string) $ref)
            ->set('rows.0.qty', '20')
            ->call('simpan')
            ->assertSet('ruleError', '')
            ->assertRedirect();

        $grn = GoodsReceipt::query()->latest('id')->firstOrFail();
        $this->assertSame($ref, (int) $grn->lines()->sole()->purchase_request_order_line_id);

        // Draf dibuka lagi: rujukan catatan pemesanan ikut dimuat.
        Livewire::actingAs($staf)->test(ReceiptForm::class, ['goodsReceipt' => $grn])
            ->assertSet('rows.0.order_line_id', (string) $ref);
    }
}

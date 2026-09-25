<?php

declare(strict_types=1);

namespace Tests\Feature\Receipt;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApproverType;
use App\Domain\Receipt\Actions\CompleteGoodsReceipt;
use App\Domain\Receipt\Enums\QcResult;
use App\Domain\Receipt\Livewire\PutawayDetail;
use App\Domain\Receipt\Livewire\PutawayList;
use App\Domain\Receipt\Livewire\ReceiptDetail;
use App\Domain\Receipt\Livewire\ReceiptForm;
use App\Domain\Receipt\Livewire\ReceiptList;
use App\Domain\Receipt\Livewire\VendorReturnDetail;
use App\Domain\Receipt\Livewire\VendorReturnForm;
use App\Domain\Receipt\Livewire\VendorReturnList;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\VendorReturn;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalFixtures;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-GRN-19 s.d. TC-GRN-21, TC-PUT-08, TC-RTV-09 — izin layar (BR-GEN-09),
 * cakupan gudang (BR-ACC-05), dan kedelapan layar §6.
 */
class ReceiptScreenTest extends TenantTestCase
{
    use ApprovalFixtures;
    use ReceiptFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();
    }

    #[Test]
    public function tc_grn_19_izin_layar_penerimaan(): void
    {
        $grn = $this->grnDiterima([['item_id' => $this->baut->id, 'qty_received' => 5]]);
        $put = app(CompleteGoodsReceipt::class)->handle($grn, $this->makeUser())->putawayTasks()->sole();

        $staf = $this->makeUser('warehouse_staff');
        $this->assertTrue($staf->hasPermission('receipt.qc'));
        $this->assertFalse($staf->hasPermission('receipt.cancel'), 'Membatalkan GRN hak Kepala Gudang (Katalog §2.5).');
        $this->assertFalse($staf->hasPermission('vendor_return.approve'));

        foreach (['receipts', 'receipts/create', 'receipts/'.$grn->id, 'putaways', 'putaways/'.$put->id, 'vendor-returns', 'vendor-returns/create'] as $url) {
            $this->actingAs($staf)->get($this->tenantUrl($url))->assertOk();
        }

        $draf = $this->grnDraf([['item_id' => $this->baut->id, 'qty_received' => 2]]);
        $this->actingAs($staf)->get($this->tenantUrl('receipts/'.$draf->id.'/edit'))->assertOk()->assertSee('BAUT-M12');
        $this->actingAs($staf)->get($this->tenantUrl('receipts/'.$grn->id.'/edit'))->assertForbidden();

        $pemohon = $this->makeUser('internal_requester');
        foreach (['receipts', 'putaways', 'vendor-returns'] as $url) {
            $this->actingAs($pemohon)->get($this->tenantUrl($url))->assertForbidden();
        }

        $auditor = $this->makeUser('internal_auditor');
        $this->actingAs($auditor)->get($this->tenantUrl('receipts'))->assertOk();
        $this->actingAs($auditor)->get($this->tenantUrl('receipts/create'))->assertForbidden();

        $pr = $this->makeUser('pr_follow_up');
        $this->assertTrue($pr->hasPermission('vendor_return.complete'));
    }

    #[Test]
    public function tc_grn_20_layar_form_dan_detail_menjalankan_draf_terima_qc_selesai(): void
    {
        $staf = $this->makeUser('warehouse_staff');

        Livewire::actingAs($staf)
            ->test(ReceiptForm::class)
            ->assertOk()
            ->set('form.warehouse_id', (string) $this->gudang->id)
            ->set('form.vendor_id', (string) $this->vendor->id)
            ->set('rows.0.item_id', (string) $this->genset->id)
            ->set('rows.0.units', "GNS-A\nGNS-B")
            ->call('tambahBaris')
            ->set('rows.1.item_id', (string) $this->kabel->id)
            ->set('rows.1.qty', '10')
            ->call('simpan')
            ->assertSet('ruleError', '')
            ->assertRedirect();

        $grn = GoodsReceipt::query()->latest('id')->firstOrFail();
        $this->assertSame(3, $grn->lines()->count(), 'Dua serial menjadi dua baris.');

        $qcLine = $grn->lines()->where('item_id', $this->kabel->id)->firstOrFail();

        Livewire::actingAs($staf)
            ->test(ReceiptDetail::class, ['goodsReceipt' => $grn])
            ->assertOk()
            ->call('terima')
            ->assertSet('ruleError', '')
            ->call('selesaikan')
            ->assertSet('ruleCode', 'BR-GRN-02')
            ->call('mintaDialog', 'qc', $qcLine->id)
            ->set('form.qc_result', 'passed')
            ->call('simpanQc')
            ->assertSet('ruleError', '')
            ->call('selesaikan')
            ->assertSet('ruleError', '');

        $this->assertSame('completed', $grn->refresh()->status->value);
        $this->assertSame(1, $grn->putawayTasks()->count());
    }

    #[Test]
    public function tc_grn_21_grn_gudang_lain_tidak_ditemukan(): void
    {
        $grn = $this->grnDraf([['item_id' => $this->baut->id, 'qty_received' => 5]]);
        $bks = $this->buatGudang('BKS', 'Gudang Bekasi');

        $kepala = $this->makeUser('warehouse_head', ScopeType::Warehouse, $bks->id);

        $this->actingAs($kepala)->get($this->tenantUrl('receipts/'.$grn->id))->assertNotFound();
    }

    #[Test]
    public function tc_put_08_layar_put_away_menyelesaikan_tugas(): void
    {
        $grn = $this->grnDiterima([['item_id' => $this->baut->id, 'qty_received' => 7]]);
        $put = app(CompleteGoodsReceipt::class)->handle($grn, $this->makeUser())->putawayTasks()->sole();
        $baris = $put->lines()->first();

        $staf = $this->makeUser('warehouse_staff');

        Livewire::actingAs($staf)->test(PutawayList::class)->assertOk()->assertSee($put->number);

        Livewire::actingAs($staf)
            ->test(PutawayDetail::class, ['putawayTask' => $put])
            ->assertOk()
            ->assertSet('isian.'.$baris->id.'.bin_id', (string) $this->binA->id)
            // Bin tujuan dipindai (A-201): kode asing ditolak, kode bin gudang ini mengisi baris.
            ->call('pindaiBin', $baris->id, 'TIDAK-ADA')
            ->assertHasErrors('pindai.'.$baris->id)
            ->call('pindaiBin', $baris->id, mb_strtolower((string) $this->binB->code))
            ->assertHasNoErrors()
            ->assertSet('isian.'.$baris->id.'.bin_id', (string) $this->binB->id)
            ->call('selesaikan')
            ->assertSet('ruleCode', 'BR-GRN-03')
            ->set('isian.'.$baris->id.'.override_reason', 'Dekat pintu')
            ->call('selesaikan')
            ->assertSet('ruleError', '');

        $this->assertSame('completed', $put->refresh()->status->value);
        $this->assertSame(7.0, $this->saldo($this->binB, $this->baut));
    }

    #[Test]
    public function tc_rtv_09_layar_rtv_dari_form_sampai_kirim(): void
    {
        $grn = $this->grnDiterima([['item_id' => $this->kabel->id, 'qty_received' => 6]]);
        $this->qc($grn, 0, QcResult::Rejected);

        // RTV diputus lewat mesin approval: satu lapis kepala gudang (20-approval §13).
        $kepala = $this->makeUser('warehouse_head');
        $this->aturan(ApprovalDocumentType::VendorReturn, [$this->lapis(ApproverType::WarehouseHead)]);

        $staf = $this->makeUser('warehouse_staff');
        $line = $grn->lines()->first();

        Livewire::actingAs($staf)
            ->withQueryParams(['receipt' => $grn->id])
            ->test(VendorReturnForm::class)
            ->assertOk()
            ->assertSet('isian.'.$line->id.'.qty', '6')
            ->call('simpan')
            ->assertSet('ruleError', '')
            ->assertRedirect();

        $rtv = VendorReturn::query()->latest('id')->firstOrFail();
        $this->assertSame('pending_approval', $rtv->status->value);

        Livewire::actingAs($staf)->test(VendorReturnList::class)->assertOk()->assertSee($rtv->number);
        Livewire::actingAs($staf)->test(ReceiptList::class)->assertOk()->assertSee($grn->number);

        Livewire::actingAs($kepala)
            ->test(VendorReturnDetail::class, ['vendorReturn' => $rtv])
            ->assertOk()
            ->assertSee(__('Riwayat approval'))
            ->call('setujui')
            ->assertSet('ruleError', '');

        Livewire::actingAs($staf)
            ->test(VendorReturnDetail::class, ['vendorReturn' => $rtv])
            ->call('kirim')
            ->assertSet('ruleError', '');

        $this->assertSame('shipped', $rtv->refresh()->status->value);
        $this->actingAs($staf)->get($this->tenantUrl('vendor-returns/'.$rtv->id))->assertOk();
    }
}

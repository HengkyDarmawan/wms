<?php

declare(strict_types=1);

namespace Tests\Feature\Return;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Receipt\Actions\ReceiveGoodsReceipt;
use App\Domain\Receipt\Livewire\ReceiptForm;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Return\Enums\GoodsReturnStatus;
use App\Domain\Return\Livewire\ReturnDetail;
use App\Domain\Return\Livewire\ReturnForm;
use App\Domain\Return\Livewire\ReturnList;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Warehouse\Enums\BinType;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalFixtures;
use Tests\Feature\Return\Concerns\ReturnFixtures;
use Tests\TenantTestCase;

/**
 * TC-RET-15 s.d. TC-RET-17 — izin layar & portal klien (BR-GEN-09, BR-RET-05,
 * BR-PRJ-06), layar form → detail → GRN retur → pemilahan, dialog tolak/batal.
 */
class ReturnScreenTest extends TenantTestCase
{
    use ApprovalFixtures;
    use ReturnFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanTransfer();
        $this->stok($this->binKrw1, $this->baut, 20);
    }

    #[Test]
    public function tc_ret_15_izin_layar_dan_portal_klien(): void
    {
        $ret = $this->ret([['key' => $this->kunciSite($this->binKrw1, $this->baut), 'qty_base' => 2]]);

        $staf = $this->makeUser('warehouse_staff');
        $this->assertTrue($staf->hasPermission('return.sort'));
        $this->assertFalse($staf->hasPermission('return.approve'));

        foreach (['returns', 'returns/create', 'returns/'.$ret->id] as $url) {
            $this->actingAs($staf)->get($this->tenantUrl($url))->assertOk();
        }

        $pemohon = $this->makeUser('internal_requester', ScopeType::Project, $this->proyek->id);
        $this->actingAs($pemohon)->get($this->tenantUrl('returns'))->assertOk()->assertSee($ret->number);
        $this->actingAs($pemohon)->get($this->tenantUrl('returns/create'))->assertOk();

        $auditor = $this->makeUser('internal_auditor');
        $this->actingAs($auditor)->get($this->tenantUrl('returns/'.$ret->id))->assertOk();
        $this->actingAs($auditor)->get($this->tenantUrl('returns/create'))->assertForbidden();

        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl('returns'))->assertForbidden();

        // Portal klien: proyeknya sendiri saja (BR-PRJ-06); area internal dipulangkan ke portal.
        $klien = $this->makeUser('client_user', ScopeType::Project, $this->proyek->id, ['client_id' => $this->proyek->client_id]);
        $this->actingAs($klien)->get($this->tenantUrl('portal/returns'))->assertOk()->assertSee($ret->number);
        $this->actingAs($klien)->get($this->tenantUrl('portal/returns/create'))->assertOk();
        $this->actingAs($klien)->get($this->tenantUrl('portal/returns/'.$ret->id))->assertOk()->assertDontSee(__('Riwayat approval'));
        $this->actingAs($klien)->get($this->tenantUrl('returns'))->assertRedirect();

        $klienLain = $this->makeUser('client_user', ScopeType::Project, $this->makeProject()->id, ['client_id' => $this->makeClient()->id]);
        $this->actingAs($klienLain)->get($this->tenantUrl('portal/returns/'.$ret->id))->assertNotFound();

        $this->actingAs($staf)->get($this->tenantUrl('/'))->assertOk()->assertSee(route('returns.index'));
        $this->actingAs($klien)->get($this->tenantUrl('portal'))->assertOk()->assertSee(route('portal.returns.index'));
    }

    #[Test]
    public function tc_ret_16_layar_form_detail_grn_retur_dan_pemilahan(): void
    {
        $pemohon = $this->makeUser('internal_requester', ScopeType::Project, $this->proyek->id);
        $kunci = str_replace(':', '_', $this->kunciSite($this->binKrw1, $this->baut));

        Livewire::actingAs($pemohon)
            ->test(ReturnForm::class)
            ->assertOk()
            ->set('form.project_id', (string) $this->proyek->id)
            ->set('form.to_warehouse_id', (string) $this->gudang->id)
            ->assertSee('KRW1-A-R01-L1-B01')
            ->set('qty.'.$kunci, '25')
            ->call('simpan')
            ->assertSet('ruleCode', 'BR-RET-03')
            ->set('qty.'.$kunci, '10')
            ->call('simpan')
            ->assertSet('ruleError', '')
            ->assertRedirect();

        $ret = GoodsReturn::query()->latest('id')->firstOrFail();
        $this->assertSame(GoodsReturnStatus::InProgress, $ret->status);

        Livewire::actingAs($pemohon)->test(ReturnList::class)->assertOk()->assertSee($ret->number);

        // Staf gudang tujuan membuat GRN retur dari detail RET, lalu menerimanya.
        $staf = $this->makeUser('warehouse_staff');
        Livewire::actingAs($staf)
            ->test(ReturnDetail::class, ['goodsReturn' => $ret])
            ->assertSee(__('Terima retur (GRN)'))
            ->call('terima')
            ->assertSet('ruleError', '')
            ->assertRedirect();

        $grn = GoodsReceipt::query()->where('goods_return_id', $ret->id)->sole();

        Livewire::actingAs($staf)
            ->test(ReceiptForm::class, ['goodsReceipt' => $grn])
            ->assertOk()
            ->assertSet('returnQty.'.$ret->lines()->sole()->id, '10');

        app(ReceiveGoodsReceipt::class)->handle($grn, $staf);

        $baris = $ret->lines()->sole();

        Livewire::actingAs($staf)
            ->test(ReturnDetail::class, ['goodsReturn' => $ret->refresh()])
            ->assertSee(__('Pemilahan'))
            ->assertSet('pilah.'.$baris->id.'.0.sorting', 'good')
            ->set('pilah.'.$baris->id.'.0.qty', '8')
            ->set('pilah.'.$baris->id.'.0.target_bin_id', (string) $this->binA->id)
            ->call('tambahBagian', $baris->id)
            ->set('pilah.'.$baris->id.'.1.qty', '2')
            ->call('simpanPilah')
            ->assertSet('ruleCode', 'BR-GEN-11')
            ->set('pilah.'.$baris->id.'.1.reason_code_id', (string) $this->alasan(\App\Domain\Master\Enums\ReasonContext::Damage))
            ->call('simpanPilah')
            ->assertSet('ruleError', '');

        $this->assertSame(GoodsReturnStatus::Sorted, $ret->refresh()->status);
        $this->assertSame(108.0, $this->saldo($this->binA, $this->baut));
        $this->assertSame(2.0, $this->saldo($this->binSistem($this->gudang, BinType::Return), $this->baut, StockStatus::Damaged));
        $this->actingAs($staf)->get($this->tenantUrl('receipts/'.$grn->id))->assertOk()->assertSee($ret->number);
    }

    #[Test]
    public function tc_ret_17_dialog_setujui_tolak_batal(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $this->aturan(ApprovalDocumentType::GoodsReturn, [$this->lapisUser($kepala)]);
        $pemohon = $this->makeUser('internal_requester', ScopeType::Project, $this->proyek->id);
        $kunci = $this->kunciSite($this->binKrw1, $this->baut);

        $ret = $this->ret([['key' => $kunci, 'qty_base' => 3]], [], $pemohon);

        Livewire::actingAs($pemohon)->test(ReturnDetail::class, ['goodsReturn' => $ret])->assertDontSee(__('Setujui'));

        Livewire::actingAs($kepala)
            ->test(ReturnDetail::class, ['goodsReturn' => $ret])
            ->call('mintaDialog', 'tolak')
            ->call('tolak')
            ->assertHasErrors('form.reason')
            ->set('form.reason', 'SPEC')
            ->call('tolak')
            ->assertSet('ruleError', '');
        $this->assertSame(GoodsReturnStatus::Rejected, $ret->refresh()->status);

        $kedua = $this->ret([['key' => $kunci, 'qty_base' => 3]], ['self_delivered' => false], $pemohon);
        Livewire::actingAs($kepala)->test(ReturnDetail::class, ['goodsReturn' => $kedua])->call('setujui')->assertSet('ruleError', '');
        $this->assertSame(GoodsReturnStatus::Approved, $kedua->refresh()->status);
        $this->assertNotNull($kedua->livePickTask(), 'PCK SJ balik dibuat otomatis (A-111).');

        Livewire::actingAs($pemohon)
            ->test(ReturnDetail::class, ['goodsReturn' => $kedua])
            ->call('mintaDialog', 'batal')
            ->set('form.reason', 'NOT_NEEDED')
            ->call('batalkan')
            ->assertSet('ruleError', '');
        $this->assertSame(GoodsReturnStatus::Cancelled, $kedua->refresh()->status);
    }
}

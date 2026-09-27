<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ItemUomConversion;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Uom;
use App\Domain\Receipt\Actions\ReceiveGoodsReceipt;
use App\Domain\Request\Livewire\RequestForm;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Return\Livewire\ReturnForm;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Stock\Livewire\BalanceList;
use App\Domain\Stock\Livewire\StockCard;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Support\DocumentPrinter;
use App\Domain\Warehouse\Support\WarehouseLayoutData;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Approval\Concerns\ApprovalFixtures;
use Tests\Feature\Return\Concerns\ReturnFixtures;
use Tests\TenantTestCase;

/**
 * TC-REQ-37, TC-RET-22, TC-STK-37, TC-WH-30, TC-TPL-27 — jumlah diketik dalam
 * kemasan (mis. 2 DUS) di Permintaan & Retur, dan uraian kemasan di saldo,
 * kartu stok, denah, dan cetakan (A-291, A-293).
 */
class PackagingDisplayTest extends TenantTestCase
{
    use ApprovalFixtures;
    use ReturnFixtures;

    private Uom $dus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanTransfer();

        $this->dus = Uom::query()->where('code', 'DUS')->firstOrFail();
        ItemUomConversion::create(['item_id' => $this->baut->id, 'uom_id' => $this->dus->id, 'qty_base' => 12, 'is_active' => true]);
    }

    #[Test]
    public function tc_req_37_permintaan_dalam_kemasan(): void
    {
        $pemohon = $this->makeUser('internal_requester');
        $pemohon->forgetPermissionCache();

        Livewire::actingAs($pemohon)->test(RequestForm::class)
            ->set('form.project_id', (string) $this->proyek->id)
            ->set('lines.0.item_id', (string) $this->baut->id)
            ->set('lines.0.qty_base', '2')
            ->set('lines.0.uom', (string) $this->dus->id)
            ->assertSee('2 DUS = 24 '.$this->baut->baseUom->code)
            ->call('simpan')->assertHasNoErrors();

        $baris = MaterialRequest::query()->latest('id')->firstOrFail()->lines()->sole();
        $this->assertSame([24.0, (int) $this->dus->id, 2.0], [(float) $baris->qty_base, (int) $baris->uom_id, (float) $baris->qty_input]);
        $this->assertSame('2 DUS', $baris->typedQuantity());

        // Dibuka lagi dalam satuan yang diketik.
        Livewire::actingAs($pemohon)->test(RequestForm::class, ['request' => $baris->request])
            ->assertSet('lines.0.qty_base', '2')->assertSet('lines.0.uom', (string) $this->dus->id);
    }

    #[Test]
    public function tc_ret_22_retur_dalam_kemasan_batas_tetap_satuan_dasar(): void
    {
        $this->stok($this->binKrw1, $this->baut, 30);
        $pemohon = $this->makeUser('internal_requester', ScopeType::Project, $this->proyek->id);
        $kunci = str_replace(':', '_', $this->kunciSite($this->binKrw1, $this->baut));

        Livewire::actingAs($pemohon)->test(ReturnForm::class)
            ->set('form.project_id', (string) $this->proyek->id)
            ->set('form.to_warehouse_id', (string) $this->gudang->id)
            ->set('qty.'.$kunci, '3')
            ->set('satuan.'.$kunci.'.uom', (string) $this->dus->id)
            ->call('simpan')
            ->assertSet('ruleCode', 'BR-RET-03') // 3 DUS = 36 > 30
            ->set('qty.'.$kunci, '2')
            ->call('simpan')
            ->assertSet('ruleError', '');

        $baris = GoodsReturn::query()->latest('id')->firstOrFail()->requestedLines()->sole();
        $this->assertSame([24.0, 2.0], [(float) $baris->qty_base, (float) $baris->qty_input]);
        $this->assertSame('2 DUS', $baris->typedQuantity());
    }

    #[Test]
    public function tc_stk_37_wh_30_tpl_27_uraian_kemasan_di_saldo_denah_dan_cetak(): void
    {
        $this->actingAs($this->makeUser('company_admin'));
        $kode = $this->baut->baseUom->code;

        // Fixture: 100 baut di bin A → 8 DUS 4 (satuan dasar).
        Livewire::test(BalanceList::class)->assertSee('8 DUS 4 '.$kode);
        Livewire::test(StockCard::class, ['item' => $this->baut])->assertSee('8 DUS 4 '.$kode);

        // Panel isi bin denah (WarehouseLayoutData::isi).
        $isiBin = new \ReflectionMethod(WarehouseLayoutData::class, 'isi');
        $isi = collect($isiBin->invoke(app(WarehouseLayoutData::class), [(int) $this->binA->id])[(int) $this->binA->id] ?? [])
            ->firstWhere('item_id', (int) $this->baut->id);
        $this->assertSame('8 DUS 4 '.$kode, $isi['kemasan'] ?? null);

        // Cetak GRN: kolom Dikirim vendor/Baik/Rusak/Kurang dan uraian kemasan.
        $grn = app(ReceiveGoodsReceipt::class)->handle($this->grnDraf([[
            'item_id' => $this->baut->id, 'uom_id' => $this->dus->id, 'qty_vendor' => 3, 'qty_received' => 2, 'qty_damaged' => 1,
            'damage_reason_id' => (int) ReasonCode::query()->where('context', ReasonContext::Damage->value)->value('id'),
        ]]), $this->makeUser('warehouse_staff'));

        $html = app(DocumentPrinter::class)->view(DocumentTemplateType::GoodsReceipt, $grn)->render();
        foreach (['Dikirim vendor', 'Baik', 'Rusak', 'Kurang', '3 DUS', '2 DUS'] as $teks) {
            $this->assertStringContainsString($teks, $html);
        }
    }
}

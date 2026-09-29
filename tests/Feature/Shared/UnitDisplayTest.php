<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Livewire\ItemDetail;
use App\Domain\Master\Models\FeatureSetting;
use App\Domain\Master\Models\ItemUomConversion;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Support\UnitInput;
use App\Domain\Receipt\Actions\ReceiveGoodsReceipt;
use App\Domain\Receipt\Livewire\ReceiptDetail;
use App\Domain\Receipt\Livewire\ReceiptForm;
use App\Domain\Request\Livewire\RequestForm;
use App\Domain\Shared\Support\ActivityText;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-GRN-33, TC-REQ-38, TC-MST-49, TC-RIW-01 — perapian tampilan A-283 & A-287–A-295
 * tanpa mengubah aturan: akhiran satuan di kotak jumlah, hasil satuan dasar
 * di bawah Rusak, isian "Kemasan lain…" selebar baris, bantuan mengikuti
 * saklar, istilah Batch / nomor seri, sisa retur bersatuan, dan deskripsi
 * riwayat aktivitas bawaan dalam Bahasa Indonesia.
 */
class UnitDisplayTest extends TenantTestCase
{
    use ReceiptFixtures;

    private Uom $dus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();

        $this->dus = Uom::query()->where('code', 'DUS')->firstOrFail();
        ItemUomConversion::create(['item_id' => $this->baut->id, 'uom_id' => $this->dus->id, 'qty_base' => 100, 'is_active' => true]);
    }

    #[Test]
    public function tc_grn_33_form_penerimaan_akhiran_satuan_hasil_rusak_dan_kemasan_lain(): void
    {
        $opsi = ['base' => 'PCS', 'per_unit' => false, 'codes' => [$this->dus->id => 'DUS'], 'factors' => [$this->dus->id => 100.0]];
        $this->assertSame('PCS', UnitInput::selectedCode(['uom' => ''], $opsi));
        $this->assertSame('DUS', UnitInput::selectedCode(['uom' => (string) $this->dus->id], $opsi));
        $this->assertSame('1 DUS = 100 PCS', UnitInput::baseText(['uom' => (string) $this->dus->id], $opsi, [], 1));
        $this->assertNull(UnitInput::baseText(['uom' => ''], $opsi, [], 5), 'Satuan dasar tanpa hasil.');

        FeatureSetting::seed(['piece' => false, 'serial' => true], overwrite: true);

        $layar = Livewire::actingAs($this->makeUser('warehouse_staff'))->test(ReceiptForm::class)
            ->set('form.warehouse_id', (string) $this->gudang->id)
            ->set('form.vendor_id', (string) $this->vendor->id)
            ->assertSee(__('Item berserial: tulis satu nomor serial per baris.'))
            ->assertDontSee(__('Item per potong: tulis panjang tiap potongan.'))
            ->set('rows.0.item_id', (string) $this->baut->id)
            ->assertSeeHtml('data-akhiran-satuan>'.$this->baut->baseUom->code.'<')
            ->set('rows.0.uom', (string) $this->dus->id)
            ->set('rows.0.damaged', '1')
            ->assertSeeHtml('data-akhiran-satuan>DUS<')
            ->assertSee('1 DUS = 100 '.$this->baut->baseUom->code)
            ->assertDontSeeHtml('data-kemasan-lain');

        $layar->set('rows.0.uom', 'lain')
            ->assertSeeHtml('data-kemasan-lain')
            ->assertSee(__('Ingat untuk item ini'));

        FeatureSetting::seed(['piece' => true], overwrite: true);
        Livewire::actingAs($this->makeUser('warehouse_staff'))->test(ReceiptForm::class)
            ->assertSee(__('Item per potong: tulis panjang tiap potongan.'));
    }

    #[Test]
    public function tc_grn_33b_detail_penerimaan_batch_nomor_seri_dan_sisa_retur_bersatuan(): void
    {
        FeatureSetting::seed(['piece' => false, 'qc' => false], overwrite: true);

        $grn = app(ReceiveGoodsReceipt::class)->handle($this->grnDraf([[
            'item_id' => $this->baut->id, 'qty_vendor' => 300, 'qty_received' => 200, 'qty_damaged' => 100,
            'damage_reason_id' => $this->alasan(ReasonContext::Damage),
        ]]), $this->makeUser('warehouse_staff'));

        $pcs = $this->baut->baseUom->code;

        Livewire::actingAs($this->makeUser('warehouse_head'))->test(ReceiptDetail::class, ['goodsReceipt' => $grn])
            ->assertSee(__('Batch / nomor seri'))
            ->assertDontSee(__('Lot / serial / potongan'))
            ->assertSee(__('menunggu retur').' 100 '.$pcs.' (1 DUS)');

        FeatureSetting::seed(['piece' => true], overwrite: true);
        Livewire::actingAs($this->makeUser('warehouse_head'))->test(ReceiptDetail::class, ['goodsReceipt' => $grn])
            ->assertSee(__('Batch / nomor seri').' / '.__('potongan'));
    }

    #[Test]
    public function tc_req_38_form_permintaan_akhiran_satuan_dan_kemasan_lain_selebar_baris(): void
    {
        $pemohon = $this->makeUser('internal_requester');
        $pemohon->forgetPermissionCache();

        Livewire::actingAs($pemohon)->test(RequestForm::class)
            ->set('lines.0.item_id', (string) $this->baut->id)
            ->assertSeeHtml('data-akhiran-satuan>'.$this->baut->baseUom->code.'<')
            ->set('lines.0.uom', (string) $this->dus->id)
            ->assertSeeHtml('data-akhiran-satuan>DUS<')
            ->set('lines.0.uom', 'lain')
            ->assertSeeHtml('data-kemasan-lain')
            ->assertSeeHtml('colspan="6"');
    }

    #[Test]
    public function tc_mst_49_kemasan_lain_berisi_kemasan_item_dan_diingat(): void
    {
        $pemohon = $this->makeUser('internal_requester');
        $pemohon->forgetPermissionCache();
        $set = Uom::query()->where('code', 'SET')->firstOrFail();
        $pcs = $this->baut->baseUom->code;

        // A-357: "1 SET berisi 2 DUS" (DUS = 100) → 1 SET = 200; 3 SET = 600.
        $opsi = ['base' => $pcs, 'per_unit' => false, 'codes' => [$this->dus->id => 'DUS'], 'factors' => [$this->dus->id => 100.0]];
        $this->assertSame(200.0, UnitInput::faktorLain(['uom_factor' => '2', 'uom_isi' => (string) $this->dus->id], $opsi));
        $this->assertSame(0.0, UnitInput::faktorLain(['uom_factor' => '2', 'uom_isi' => '999'], $opsi), 'Satuan isi bukan kemasan item.');

        Livewire::actingAs($pemohon)->test(RequestForm::class)
            ->set('lines.0.item_id', (string) $this->baut->id)
            ->set('lines.0.uom', 'lain')
            ->set('lines.0.uom_lain', (string) $set->id)
            ->set('lines.0.uom_factor', '2')
            ->set('lines.0.uom_isi', (string) $this->dus->id)
            ->set('lines.0.qty_base', '3')
            ->assertSee(__('berisi'))
            ->assertSee('3 SET = 600 '.$pcs)
            ->assertSeeHtml('<strong>200 '.$pcs.'</strong>');
    }

    #[Test]
    public function tc_riw_01_deskripsi_riwayat_bawaan_berbahasa_indonesia(): void
    {
        $this->assertSame('dibuat', ActivityText::label('created'));
        $this->assertSame('diubah', ActivityText::label('updated'));
        $this->assertSame('dihapus', ActivityText::label('deleted'));
        $this->assertSame('PRQ ditolak', ActivityText::label('PRQ ditolak'), 'Deskripsi buatan aplikasi tidak diubah.');

        // Item baru tercatat spatie sebagai "created"; layar riwayat menampilkan "dibuat".
        $this->baut->forceFill(['name' => 'Baut diubah'])->save();

        Livewire::actingAs($this->makeUser('company_admin'))->test(ItemDetail::class, ['item' => $this->baut])
            ->set('tab', 'riwayat')
            ->assertSee('diubah')
            ->assertDontSeeHtml('<td>updated</td>')
            ->assertDontSeeHtml('<td>created</td>');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Receipt;

use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\FeatureSetting;
use App\Domain\Master\Models\ItemUomConversion;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Support\QtyFormat;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestStatus;
use App\Domain\PurchaseRequest\Models\PurchaseRequestOrder;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Receipt\Actions\ApproveVendorReturn;
use App\Domain\Receipt\Actions\CompleteGoodsReceipt;
use App\Domain\Receipt\Actions\CreateVendorReturn;
use App\Domain\Receipt\Actions\ReceiveGoodsReceipt;
use App\Domain\Receipt\Actions\RecordQcResult;
use App\Domain\Receipt\Actions\ShipVendorReturn;
use App\Domain\Receipt\Enums\QcResult;
use App\Domain\Receipt\Enums\VendorReturnStatus;
use App\Domain\Receipt\Livewire\VendorReturnForm;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\PutawayTaskLine;
use App\Domain\Receipt\Models\VendorReturn;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Warehouse\Enums\BinType;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Purchasing\Concerns\PurchasingFixtures;
use Tests\TenantTestCase;

/**
 * TC-GRN-23 s.d. TC-GRN-27, TC-PUT-09, TC-RTV-10, TC-RTV-11 — penerimaan
 * vendor mencatat Baik / Rusak / Kurang tanpa QC, barang rusak langsung
 * diretur, dan jumlah diketik dalam kemasan (A-287 s.d. A-292).
 */
class ReceiptConditionTest extends TenantTestCase
{
    use PurchasingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPurchasing();

        // Keputusan pemilik 28 Sep 2026: QC tidak dipakai (bawaan company baru).
        FeatureSetting::seed(['qc' => false], overwrite: true);
    }

    /** Catatan pemesanan PO baut sejumlah $qty. */
    private function pesanan(float $qty): array
    {
        $prq = $this->prqManual([['item_id' => $this->baut->id, 'qty_base' => $qty]]);
        $po = $this->poDisetujui($prq);
        $ref = (int) PurchaseRequestOrder::query()->where('purchase_order_id', $po->id)->sole()->lines()->sole()->id;

        return [$prq, $po, $ref];
    }

    private function rusakId(): int
    {
        return $this->alasan(ReasonContext::Damage);
    }

    private function terima(GoodsReceipt $grn): GoodsReceipt
    {
        return app(ReceiveGoodsReceipt::class)->handle($grn, $this->makeUser('warehouse_staff'));
    }

    #[Test]
    public function tc_grn_23_baik_rusak_kurang_masuk_bin_berbeda_dan_hanya_baik_mengurangi_pesanan(): void
    {
        [$prq, $po, $ref] = $this->pesanan(12);
        $penerimaan = $this->binSistem($this->gudang, BinType::Receiving);
        $karantina = $this->binSistem($this->gudang, BinType::Quarantine);

        $grn = $this->terima($this->grnDraf([[
            'item_id' => $this->baut->id, 'purchase_request_order_line_id' => $ref,
            'qty_vendor' => 12, 'qty_received' => 8, 'qty_damaged' => 2, 'damage_reason_id' => $this->rusakId(),
        ]]));

        $baris = $grn->lines()->sole();
        $this->assertSame([12.0, 8.0, 2.0, 2.0], [(float) $baris->qty_vendor, (float) $baris->qty_received, (float) $baris->qty_damaged, (float) $baris->qty_short], 'Kurang = 12 − 8 − 2.');
        $this->assertSame((int) $karantina->id, (int) $baris->damaged_bin_id);
        $this->assertSame(8.0, $this->saldo($penerimaan, $this->baut));
        $this->assertSame(2.0, $this->saldo($karantina, $this->baut, StockStatus::Damaged));
        $this->assertSame(0.0, $this->saldo($karantina, $this->baut, StockStatus::Quarantine), 'Tanpa QC tidak ada barang menunggu QC.');

        $kejadian = StockEvent::query()->where('event_type', StockEventType::GoodsReceived->value)->where('source_number', $grn->number)->get();
        $this->assertCount(2, $kejadian, 'Baik dan Rusak masing-masing satu kejadian goods_received.');
        $this->assertSame(['available', 'damaged'], $kejadian->pluck('payload.stock_status')->sort()->values()->all());
        $this->assertSame(2.0, (float) $kejadian->first(fn ($k) => $k->payload['stock_status'] === 'damaged')->payload['qty_short']);

        // A-287: hanya Baik dihitung diterima; rusak + kurang tetap sisa pesanan.
        $this->assertSame(8.0, (float) $po->refresh()->lines()->sole()->qty_received);
        $this->assertSame(PurchaseOrderStatus::PartiallyFulfilled, $po->status);
        $this->assertSame(PurchaseRequestStatus::PartiallyFulfilled, $prq->refresh()->status);
    }

    #[Test]
    public function tc_grn_24_validasi_rusak_kurang_dan_batas_sisa_pesanan(): void
    {
        [, , $ref] = $this->pesanan(12);

        $this->gagalPo(fn () => $this->grnDraf([['item_id' => $this->baut->id, 'qty_received' => 5, 'qty_damaged' => 1]]), 'BR-GEN-11');
        $this->gagalPo(fn () => $this->grnDraf([['item_id' => $this->baut->id, 'qty_vendor' => 10, 'qty_received' => 4, 'qty_short' => 5]]), 'A-288');
        $this->gagalPo(fn () => $this->grnDraf([['item_id' => $this->baut->id, 'qty_received' => 0, 'qty_damaged' => 0]]), 'BR-LED-02');

        // A-289: baik + rusak dibatasi sisa pesanan; jumlah menurut vendor boleh melebihi.
        $this->gagalPo(fn () => $this->grnDraf([[
            'item_id' => $this->baut->id, 'purchase_request_order_line_id' => $ref, 'qty_received' => 10, 'qty_damaged' => 3, 'damage_reason_id' => $this->rusakId(),
        ]]), 'BR-GRN-05');

        $draf = $this->grnDraf([[
            'item_id' => $this->baut->id, 'purchase_request_order_line_id' => $ref,
            'qty_vendor' => 20, 'qty_received' => 10, 'qty_damaged' => 2, 'damage_reason_id' => $this->rusakId(),
        ]]);
        $this->assertSame(8.0, (float) $draf->lines()->sole()->qty_short);

        // Draf lain ikut dihitung (baik + rusak): sisa 0.
        $this->gagalPo(fn () => $this->grnDraf([['item_id' => $this->baut->id, 'purchase_request_order_line_id' => $ref, 'qty_received' => 1]]), 'BR-GRN-05');

        // Kurang diisi sendiri tanpa jumlah vendor → jumlah vendor dihitung.
        $sendiri = $this->grnDraf([['item_id' => $this->baut->id, 'qty_received' => 7, 'qty_short' => 3]]);
        $this->assertSame(10.0, (float) $sendiri->lines()->sole()->qty_vendor);
    }

    #[Test]
    public function tc_grn_25_lot_serial_dan_potongan_rusak(): void
    {
        FeatureSetting::seed(['piece' => true], overwrite: true);
        $karantina = $this->binSistem($this->gudang, BinType::Quarantine);

        $grn = $this->terima($this->grnDraf([
            ['item_id' => $this->semen->id, 'lot_no' => 'LOT-R1', 'expiry_date' => now()->addYear()->toDateString(), 'qty_received' => 5, 'qty_damaged' => 1, 'damage_reason_id' => $this->rusakId()],
            ['item_id' => $this->genset->id, 'serial_no' => 'GNS-OK'],
            ['item_id' => $this->genset->id, 'serial_no' => 'GNS-RUSAK', 'damaged_unit' => true, 'damage_reason_id' => $this->rusakId(), 'qty_short' => 1],
            ['item_id' => $this->pipa->id, 'piece_length' => 6, 'damaged_unit' => true, 'damage_reason_id' => $this->rusakId()],
        ]));

        [$semen, $gnsBaik, $gnsRusak, $pipa] = $grn->lines()->orderBy('id')->get()->all();

        $this->assertSame(1.0, (float) $semen->qty_damaged);
        $this->assertSame(1.0, $this->saldo($karantina, $this->semen, StockStatus::Damaged));
        $this->assertSame(1, DB::table('stock_balances')->where('item_id', $this->semen->id)->distinct()->count('lot_id'), 'Baik dan rusak satu lot.');

        $this->assertSame([1.0, 0.0], [(float) $gnsBaik->qty_received, (float) $gnsBaik->qty_damaged]);
        $this->assertSame([0.0, 1.0, 1.0, 2.0], [(float) $gnsRusak->qty_received, (float) $gnsRusak->qty_damaged, (float) $gnsRusak->qty_short, (float) $gnsRusak->qty_vendor]);
        $this->assertNotNull($gnsRusak->serial_id);
        $this->assertNull($gnsRusak->receiving_bin_id);
        $this->assertSame(1.0, $this->saldo($karantina, $this->genset, StockStatus::Damaged));

        $this->assertSame(6.0, (float) $pipa->qty_damaged);
        $this->assertSame(6.0, $this->saldo($karantina, $this->pipa, StockStatus::Damaged));
    }

    #[Test]
    public function tc_grn_26_qc_lama_tetap_untuk_bagian_baik_dan_baris_rusak_semua_tidak_menghalangi(): void
    {
        FeatureSetting::seed(['qc' => true], overwrite: true);
        $karantina = $this->binSistem($this->gudang, BinType::Quarantine);

        $grn = $this->terima($this->grnDraf([
            ['item_id' => $this->kabel->id, 'qty_received' => 5, 'qty_damaged' => 1, 'damage_reason_id' => $this->rusakId()],
            ['item_id' => $this->baut->id, 'qty_received' => 0, 'qty_damaged' => 2, 'damage_reason_id' => $this->rusakId()],
        ]));

        [$kabel, $baut] = $grn->lines()->orderBy('id')->get()->all();
        $this->assertSame(5.0, $this->saldo($karantina, $this->kabel, StockStatus::Quarantine));
        $this->assertSame(1.0, $this->saldo($karantina, $this->kabel, StockStatus::Damaged));
        $this->assertTrue($kabel->awaitsQc());
        $this->assertFalse($baut->awaitsQc(), 'Baris tanpa barang Baik tidak menunggu QC.');

        $this->gagalPo(fn () => app(CompleteGoodsReceipt::class)->handle($grn->refresh(), $this->makeUser('warehouse_staff')), 'BR-GRN-02');
        $this->qc($grn, 0, QcResult::Passed);
        app(CompleteGoodsReceipt::class)->handle($grn->refresh(), $this->makeUser('warehouse_staff'));

        $this->assertSame(0, PutawayTaskLine::query()->where('goods_receipt_line_id', $baut->id)->count(), 'Baris seluruhnya rusak tidak di-put-away.');
    }

    #[Test]
    public function tc_grn_27_jumlah_dalam_kemasan_dan_ingat_kemasan(): void
    {
        $dus = Uom::query()->where('code', 'DUS')->firstOrFail();

        $grn = $this->grnDraf([[
            'item_id' => $this->baut->id, 'uom_id' => $dus->id, 'uom_factor' => 12, 'remember_uom' => true,
            'qty_vendor' => 10, 'qty_received' => 9, 'qty_damaged' => 1, 'damage_reason_id' => $this->rusakId(),
        ]]);

        $baris = $grn->lines()->sole();
        $this->assertSame([120.0, 108.0, 12.0, 0.0], [(float) $baris->qty_vendor, (float) $baris->qty_received, (float) $baris->qty_damaged, (float) $baris->qty_short]);
        $this->assertSame([(int) $dus->id, 10.0, 12.0], [(int) $baris->uom_id, (float) $baris->qty_input, (float) $baris->uom_qty_base]);
        $this->assertSame('10 DUS', $baris->typedQuantity());

        $kemasan = ItemUomConversion::query()->where('item_id', $this->baut->id)->where('uom_id', $dus->id)->sole();
        $this->assertSame(12.0, (float) $kemasan->qty_base);
        $this->assertTrue(Activity::query()->where('subject_type', $this->baut->getMorphClass())->where('subject_id', $this->baut->id)
            ->where('description', 'like', 'Kemasan 1 DUS = 12%')->exists(), 'A-292: tercatat di riwayat item.');

        // Kemasan yang sudah ada dipakai; isian lain tidak menimpanya.
        $kedua = $this->grnDraf([['item_id' => $this->baut->id, 'uom_id' => $dus->id, 'uom_factor' => 20, 'remember_uom' => true, 'qty_received' => 2]]);
        $this->assertSame(24.0, (float) $kedua->lines()->sole()->qty_received);
        $this->assertSame(12.0, (float) $kemasan->refresh()->qty_base);

        $this->assertSame('9 DUS 5 '.$this->baut->baseUom->code, QtyFormat::packaging($this->baut->refresh(), 113));
    }

    #[Test]
    public function tc_put_09_put_away_hanya_bagian_baik(): void
    {
        $grn = $this->terima($this->grnDraf([
            ['item_id' => $this->baut->id, 'qty_received' => 8, 'qty_damaged' => 2, 'damage_reason_id' => $this->rusakId()],
            ['item_id' => $this->kabel->id, 'qty_received' => 0, 'qty_damaged' => 3, 'damage_reason_id' => $this->rusakId()],
        ]));

        app(CompleteGoodsReceipt::class)->handle($grn, $this->makeUser('warehouse_staff'));
        [$baut, $kabel] = $grn->lines()->orderBy('id')->get()->all();

        $this->assertSame(8.0, (float) PutawayTaskLine::query()->where('goods_receipt_line_id', $baut->id)->sum('qty_base'));
        $this->assertSame(0, PutawayTaskLine::query()->where('goods_receipt_line_id', $kabel->id)->count());
    }

    #[Test]
    public function tc_rtv_10_retur_langsung_dari_barang_rusak_tanpa_qc(): void
    {
        [$prq, $po, $ref] = $this->pesanan(12);
        $karantina = $this->binSistem($this->gudang, BinType::Quarantine);
        $grn = $this->terima($this->grnDraf([[
            'item_id' => $this->baut->id, 'purchase_request_order_line_id' => $ref,
            'qty_vendor' => 12, 'qty_received' => 10, 'qty_damaged' => 2, 'damage_reason_id' => $this->rusakId(),
        ]]));

        $staf = $this->makeUser('warehouse_head');
        $staf->forgetPermissionCache();

        // Tombol "Retur ke vendor": form terisi bagian rusak penuh.
        Livewire::actingAs($staf)->withQueryParams(['receipt' => $grn->id])->test(VendorReturnForm::class)
            ->assertSet('rusak.'.$grn->lines()->sole()->id.'.qty', '2')
            ->set('rusak.'.$grn->lines()->sole()->id.'.qty', '3')
            ->call('simpan')
            ->assertSet('ruleCode', 'BR-GRN-04');

        Livewire::actingAs($staf)->withQueryParams(['receipt' => $grn->id])->test(VendorReturnForm::class)->call('simpan');

        $rtv = VendorReturn::query()->where('goods_receipt_id', $grn->id)->sole();
        $baris = $rtv->lines()->sole();
        $this->assertTrue($baris->is_receipt_damage);
        $this->assertSame([(int) $karantina->id, StockStatus::Damaged, 2.0], [(int) $baris->bin_id, $baris->stock_status, (float) $baris->qty_base]);

        if ($rtv->status === VendorReturnStatus::PendingApproval) {
            $rtv = app(ApproveVendorReturn::class)->approve($rtv, $this->makeUser('warehouse_head'));
        }

        app(ShipVendorReturn::class)->handle($rtv->refresh(), null, $this->makeUser('warehouse_staff'));
        $this->assertSame(0.0, $this->saldo($karantina, $this->baut, StockStatus::Damaged));
        $this->assertTrue(StockEvent::query()->where('event_type', StockEventType::GoodsRejected->value)->where('source_number', $rtv->number)->exists());

        // Barang pengganti diterima atas pesanan yang sama: pesanan selesai.
        $this->assertSame(PurchaseRequestStatus::PartiallyFulfilled, $prq->refresh()->status);
        $this->terima($this->grnDraf([['item_id' => $this->baut->id, 'purchase_request_order_line_id' => $ref, 'qty_received' => 2]]));
        $this->assertSame(PurchaseOrderStatus::Completed, $po->refresh()->status);
        $this->assertSame(PurchaseRequestStatus::Fulfilled, $prq->refresh()->status);
    }

    #[Test]
    public function tc_rtv_11_jatah_rusak_dan_jatah_qc_terpisah(): void
    {
        FeatureSetting::seed(['qc' => true], overwrite: true);
        $grn = $this->terima($this->grnDraf([
            ['item_id' => $this->kabel->id, 'qty_received' => 5, 'qty_damaged' => 1, 'damage_reason_id' => $this->rusakId()],
        ]));
        $baris = $grn->lines()->sole();

        app(CreateVendorReturn::class)->handle($grn, [[
            'goods_receipt_line_id' => $baris->id, 'part' => 'damage', 'qty_base' => 1, 'reason_code_id' => $this->rusakId(),
        ]], null, $this->makeUser('warehouse_head'));

        $this->assertSame(0.0, $baris->refresh()->damagedReturnable());
        $this->gagalPo(fn () => app(CreateVendorReturn::class)->handle($grn, [[
            'goods_receipt_line_id' => $baris->id, 'part' => 'damage', 'qty_base' => 1, 'reason_code_id' => $this->rusakId(),
        ]], null, $this->makeUser('warehouse_head')), 'BR-GRN-04');

        // RTV bagian rusak tidak mengunci QC bagian baik.
        $hasil = app(RecordQcResult::class)->handle($baris, QcResult::Passed, null, null, $this->makeUser('warehouse_staff'));
        $this->assertSame(QcResult::Passed, $hasil->qc_result);
    }
}

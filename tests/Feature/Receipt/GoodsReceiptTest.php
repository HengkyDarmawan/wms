<?php

declare(strict_types=1);

namespace Tests\Feature\Receipt;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\CompanySetting;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\Serial;
use App\Domain\Receipt\Actions\CancelGoodsReceipt;
use App\Domain\Receipt\Actions\CompleteGoodsReceipt;
use App\Domain\Receipt\Actions\ReceiveGoodsReceipt;
use App\Domain\Receipt\Actions\SaveGoodsReceipt;
use App\Domain\Receipt\Enums\GoodsReceiptStatus;
use App\Domain\Receipt\Enums\QcResult;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Warehouse\Enums\BinType;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-GRN-01 s.d. TC-GRN-13 — GRN vendor manual (Katalog §2.5, BR-GRN-01,
 * BR-GRN-02, BR-LED-03, BR-GEN-04, BR-GEN-10, BR-GEN-11, BR-REQ-03).
 */
class GoodsReceiptTest extends TenantTestCase
{
    use ReceiptFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();
    }

    private function gagal(callable $aksi, string $aturan): void
    {
        try {
            $aksi();
            $this->fail('Seharusnya ditolak '.$aturan.'.');
        } catch (ReceiptRuleException $e) {
            $this->assertSame($aturan, $e->rule, $e->getMessage());
        }
    }

    #[Test]
    public function tc_grn_01_draf_bernomor_tanpa_menyentuh_stok(): void
    {
        $grn = $this->grnDraf([['item_id' => $this->baut->id, 'qty_received' => 100]]);

        $this->assertSame(GoodsReceiptStatus::Draft, $grn->status);
        $this->assertStringStartsWith('GRN/CKG/', $grn->number);
        $this->assertSame(1, $grn->lines()->count());
        $this->assertSame(0, StockMovement::query()->where('document_type', 'goods_receipt')->count());
    }

    #[Test]
    public function tc_grn_02_terima_tanpa_qc_masuk_bin_penerimaan_dan_menerbitkan_goods_received(): void
    {
        $grn = $this->grnDiterima([['item_id' => $this->baut->id, 'qty_received' => 100]]);

        $this->assertSame(GoodsReceiptStatus::Received, $grn->status);
        $penerimaan = $this->binSistem($this->gudang, BinType::Receiving);
        $this->assertSame(100.0, $this->saldo($penerimaan, $this->baut));

        $kejadian = StockEvent::query()->where('source_type', 'goods_receipt')->where('source_id', $grn->id)->first();
        $this->assertNotNull($kejadian);
        $this->assertSame('goods_received', $kejadian->event_type->value);
        $this->assertSame('SJV-001', $kejadian->payload['vendor_doc_no']);
        $this->assertFalse($kejadian->payload['qc_required']);
    }

    #[Test]
    public function tc_grn_03_item_wajib_qc_masuk_karantina_berkondisi_karantina(): void
    {
        $this->grnDiterima([['item_id' => $this->kabel->id, 'qty_received' => 40]]);

        $karantina = $this->binSistem($this->gudang, BinType::Quarantine);
        $this->assertSame(40.0, $this->saldo($karantina, $this->kabel, StockStatus::Quarantine));
        $this->assertSame(0.0, $this->saldo($karantina, $this->kabel));
    }

    #[Test]
    public function tc_grn_04_item_lot_wajib_nomor_lot_dan_kedaluwarsa(): void
    {
        $this->gagal(fn () => $this->grnDraf([['item_id' => $this->semen->id, 'qty_received' => 10]]), 'BR-LED-03');
        $this->gagal(fn () => $this->grnDraf([['item_id' => $this->semen->id, 'qty_received' => 10, 'lot_no' => 'L1']]), 'BR-STK-12');

        $grn = $this->grnDiterima([[
            'item_id' => $this->semen->id, 'qty_received' => 10, 'lot_no' => 'lot-2609', 'expiry_date' => now()->addMonths(6)->toDateString(),
        ]]);

        $baris = $grn->lines()->with('lot')->first();
        $this->assertSame('LOT-2609', $baris->lot?->lot_no);
        $this->assertSame((int) $this->vendor->id, (int) $baris->lot->vendor_id);
    }

    #[Test]
    public function tc_grn_05_serial_satu_baris_per_unit_dan_dibuat_saat_diterima(): void
    {
        $this->gagal(fn () => $this->grnDraf([
            ['item_id' => $this->genset->id, 'serial_no' => 'GNS-01'],
            ['item_id' => $this->genset->id, 'serial_no' => 'gns-01'],
        ]), 'BR-LED-04');

        $grn = $this->grnDraf([
            ['item_id' => $this->genset->id, 'serial_no' => 'GNS-01', 'qty_received' => 5],
            ['item_id' => $this->genset->id, 'serial_no' => 'GNS-02'],
        ]);

        $this->assertSame([1.0, 1.0], $grn->lines()->pluck('qty_received')->map(fn ($q) => (float) $q)->all());
        $this->assertSame(0, Serial::query()->where('item_id', $this->genset->id)->count(), 'Serial belum dibuat saat draf.');

        app(ReceiveGoodsReceipt::class)->handle($grn, $this->makeUser());

        $this->assertSame(2, Serial::query()->where('item_id', $this->genset->id)->count());
        $this->assertSame(2.0, $this->saldo($this->binSistem($this->gudang, BinType::Receiving), $this->genset));
    }

    #[Test]
    public function tc_grn_06_potongan_per_panjang(): void
    {
        $this->gagal(fn () => $this->grnDraf([['item_id' => $this->pipa->id]]), 'BR-STK-09');

        $grn = $this->grnDiterima([
            ['item_id' => $this->pipa->id, 'piece_length' => 6],
            ['item_id' => $this->pipa->id, 'piece_length' => '4.5'],
        ]);

        $potongan = Piece::query()->where('origin_type', 'grn')->where('origin_id', $grn->id)->orderBy('id')->get();
        $this->assertCount(2, $potongan);
        $this->assertSame([6.0, 4.5], $potongan->map(fn ($p) => (float) $p->length)->all());
        $this->assertSame(10.5, $this->saldo($this->binSistem($this->gudang, BinType::Receiving), $this->pipa));
    }

    #[Test]
    public function tc_grn_07_item_sementara_ditolak(): void
    {
        $this->baut->forceFill(['status' => ItemStatus::Provisional])->save();

        $this->gagal(fn () => $this->grnDraf([['item_id' => $this->baut->id, 'qty_received' => 1]]), 'BR-REQ-03');
    }

    #[Test]
    public function tc_grn_08_batal_hanya_draf_dengan_alasan(): void
    {
        $kepala = $this->makeUser('warehouse_head');
        $grn = $this->grnDraf([['item_id' => $this->baut->id, 'qty_received' => 5]]);

        $this->gagal(fn () => app(CancelGoodsReceipt::class)->handle($grn, null, null, $kepala), 'BR-GEN-11');

        $grn = app(CancelGoodsReceipt::class)->handle($grn, $this->alasan(ReasonContext::Cancel), 'Salah vendor', $kepala);
        $this->assertSame(GoodsReceiptStatus::Cancelled, $grn->status);

        $diterima = $this->grnDiterima([['item_id' => $this->baut->id, 'qty_received' => 5]]);
        $this->gagal(fn () => app(CancelGoodsReceipt::class)->handle($diterima, $this->alasan(ReasonContext::Cancel), null, $kepala), 'BR-GEN-04');
    }

    #[Test]
    public function tc_grn_09_selesai_ditolak_bila_qc_belum_terisi(): void
    {
        $grn = $this->grnDiterima([['item_id' => $this->kabel->id, 'qty_received' => 10]]);

        $this->gagal(fn () => app(CompleteGoodsReceipt::class)->handle($grn, $this->makeUser()), 'BR-GRN-02');
        $this->assertSame(GoodsReceiptStatus::Received, $grn->refresh()->status);
    }

    #[Test]
    public function tc_grn_10_selesai_membuat_put_untuk_baris_tanpa_qc_dan_lolos_saja(): void
    {
        $grn = $this->grnDiterima([
            ['item_id' => $this->baut->id, 'qty_received' => 10],
            ['item_id' => $this->kabel->id, 'qty_received' => 20],
            ['item_id' => $this->kabel->id, 'qty_received' => 5],
        ]);

        $this->qc($grn, 1, QcResult::Passed);
        $this->qc($grn, 2, QcResult::Rejected);

        $grn = app(CompleteGoodsReceipt::class)->handle($grn->refresh(), $this->makeUser());

        $this->assertSame(GoodsReceiptStatus::Completed, $grn->status);
        $put = $grn->putawayTasks()->with('lines')->sole();
        $this->assertSame('pending', $put->status->value);
        $this->assertEqualsCanonicalizing([10.0, 20.0], $put->lines->map(fn ($l) => (float) $l->qty_base)->all());
    }

    #[Test]
    public function tc_grn_12_sumber_retur_menunggu_modul_retur(): void
    {
        $this->gagal(fn () => app(SaveGoodsReceipt::class)->handle(null, [
            'receipt_type' => 'return', 'warehouse_id' => $this->gudang->id,
        ], [], $this->makeUser()), 'BR-GEN-10');
    }

    #[Test]
    public function tc_grn_13_periode_terkunci_menolak_penerimaan(): void
    {
        $grn = $this->grnDraf([['item_id' => $this->baut->id, 'qty_received' => 5]]);
        CompanySetting::put('stock_lock_date', now()->toDateString());

        $this->gagal(fn () => app(ReceiveGoodsReceipt::class)->handle($grn, $this->makeUser()), 'BR-STK-15');
        $this->assertSame(GoodsReceiptStatus::Draft, $grn->refresh()->status);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Receipt;

use App\Domain\Adjustment\Enums\AdjustmentOrigin;
use App\Domain\Adjustment\Enums\StockAdjustmentStatus;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Receipt\Actions\ReceiveGoodsReceipt;
use App\Domain\Receipt\Actions\SaveGoodsReceipt;
use App\Domain\Receipt\Enums\GoodsReceiptStatus;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Warehouse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Receipt\Concerns\OutboundChain;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-GRN-11 — GRN transfer masuk: barang pindah dari Dalam Perjalanan gudang
 * asal ke bin Penerimaan gudang tujuan (BR-SJ-04, BR-STK-13, BR-GRN-05,
 * matriks §14 `stock_transferred`, A-81, A-82).
 */
class TransferReceiptTest extends TenantTestCase
{
    use OutboundChain;
    use ReceiptFixtures;

    private Warehouse $bks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();

        $this->bks = $this->buatGudang('BKS', 'Gudang Cabang Bekasi');
        app(StockLedger::class)->post(new MovementRequest(item: $this->baut, qtyBase: 50, toBinId: $this->binA->id));
    }

    private function sjKeBks(): Shipment
    {
        $req = $this->reqDisetujui($this->makeProject(), $this->gudang, $this->baut, 20);

        return $this->sjBerangkat($this->pckSelesai($req), [
            'destination_type' => 'warehouse',
            'destination_warehouse_id' => $this->bks->id,
        ]);
    }

    private function draf(Shipment $sj, array $lines = [], ?Warehouse $gudang = null)
    {
        return app(SaveGoodsReceipt::class)->handle(null, [
            'receipt_type' => 'transfer',
            'warehouse_id' => ($gudang ?? $this->bks)->id,
            'shipment_id' => $sj->id,
        ], $lines, $this->makeUser());
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
    public function tc_grn_11_transfer_masuk_dari_dalam_perjalanan_ke_penerimaan_tujuan(): void
    {
        $sj = $this->sjKeBks();

        // Belum ada bukti terima: belum bisa diterima gudang tujuan.
        $this->gagal(fn () => $this->draf($sj), 'BR-SJ-04');

        $sj = $this->buktiTerima($sj, 18, 2);
        $transitCkg = $this->binSistem($this->gudang, BinType::InTransit);
        $this->assertSame(20.0, $this->saldo($transitCkg, $this->baut), 'Sebelum GRN, semuanya masih milik CKG.');

        // Bukan gudang tujuannya.
        $this->gagal(fn () => $this->draf($sj, [], $this->gudang), 'BR-SJ-04');

        // BR-GRN-05 (A-245): GRN hanya sampai jumlah baik; kelebihannya dicatat terpisah.
        $grn = $this->draf($sj, [['shipment_line_id' => $sj->lines()->first()->id, 'qty_received' => 19]]);
        $this->assertSame(18.0, (float) $grn->lines()->first()->qty_received);
        $this->assertSame(1.0, (float) $grn->lines()->first()->qty_excess);

        // SJ : GRN = 1 : 1.
        $this->gagal(fn () => $this->draf($sj), 'BR-GRN-05');

        $grn = app(ReceiveGoodsReceipt::class)->handle($grn, $this->makeUser());

        $this->assertSame(GoodsReceiptStatus::Received, $grn->status);
        $this->assertSame(18.0, $this->saldo($this->binSistem($this->bks, BinType::Receiving), $this->baut));
        $this->assertSame(2.0, $this->saldo($transitCkg, $this->baut), 'Yang kurang tetap menunggu DSC di Dalam Perjalanan CKG.');
        $this->assertNull($grn->lines()->first()->qc_result, 'Transfer tidak melewati QC (A-79).');

        // Kelebihan 1 memicu ADJ `over_receipt` ke bin Penerimaan BKS, lewat approval (A-09).
        $adj = StockAdjustment::query()->where('goods_receipt_id', $grn->id)->sole();
        $this->assertSame(AdjustmentOrigin::OverReceipt, $adj->origin);
        $this->assertSame(StockAdjustmentStatus::PendingApproval, $adj->status);
        $this->assertSame(1.0, (float) $adj->lines()->sole()->qty_delta);
        $this->assertSame((int) $this->binSistem($this->bks, BinType::Receiving)->id, (int) $adj->lines()->sole()->bin_id);

        $kejadian = StockEvent::query()->where('source_type', 'goods_receipt')->where('source_id', $grn->id)->sole();
        $this->assertSame('stock_transferred', $kejadian->event_type->value);
        $this->assertSame('goods_receipt', $kejadian->payload['phase']);
        $this->assertSame((int) $this->gudang->id, (int) $kejadian->payload['from_warehouse_id']);
        $this->assertSame((int) $this->bks->id, (int) $kejadian->payload['to_warehouse_id']);
    }

    #[Test]
    public function tc_grn_11b_stok_dalam_perjalanan_tidak_dialokasikan_picking(): void
    {
        $this->buktiTerima($this->sjKeBks(), 20);

        // Sisa di bin penyimpanan CKG 30; 20 di Dalam Perjalanan tidak boleh dijanjikan
        // (A-85), apalagi dipetik (A-84) — REQ 40 sudah tertahan di approval.
        $this->assertSame(30.0, app(StockLedger::class)
            ->availableQty((int) $this->baut->id, (int) $this->gudang->id));

        try {
            $this->reqDisetujui($this->makeProject(), $this->gudang, $this->baut, 40);
            $this->fail('Stok di Dalam Perjalanan seharusnya tidak bisa direservasi.');
        } catch (RequestRuleException $e) {
            $this->assertSame('BR-REQ-05', $e->rule);
            $this->assertStringContainsString('hanya 30', $e->getMessage());
        }
    }
}

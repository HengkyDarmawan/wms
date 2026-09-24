<?php

declare(strict_types=1);

namespace Tests\Feature\Receipt;

use App\Domain\Receipt\Actions\CompleteGoodsReceipt;
use App\Domain\Receipt\Actions\CompletePutaway;
use App\Domain\Receipt\Enums\QcResult;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Enums\BinType;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Receipt\Concerns\OutboundChain;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-GRN-18 — rantai penuh tanpa seeder stok: GRN vendor → QC lolos → selesai
 * → put-away → REQ → PCK → SJ → bukti terima. Stok yang dipetik adalah stok
 * yang baru saja diterima.
 */
class ReceiptChainTest extends TenantTestCase
{
    use OutboundChain;
    use ReceiptFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();
    }

    #[Test]
    public function tc_grn_18_barang_dari_vendor_bisa_dipetik_dan_dikirim_setelah_put_away(): void
    {
        // Kabel wajib QC: masuk Karantina dulu.
        $grn = $this->grnDiterima([['item_id' => $this->kabel->id, 'qty_received' => 50]]);
        $this->assertSame(0.0, app(StockLedger::class)->availableQty($this->kabel->id, $this->gudang->id));

        $this->qc($grn, 0, QcResult::Passed, false);
        $grn = app(CompleteGoodsReceipt::class)->handle($grn->refresh(), $this->makeUser());

        $put = $grn->putawayTasks()->sole();
        app(CompletePutaway::class)->handle($put, [], $this->makeUser());

        $this->assertSame(50.0, $this->saldo($this->binA, $this->kabel));
        $this->assertSame(50.0, app(StockLedger::class)->availableQty($this->kabel->id, $this->gudang->id));

        // Barang yang baru di-put-away dipenuhi lewat REQ → PCK → SJ.
        $proyek = $this->makeProject();
        $req = $this->reqDisetujui($proyek, $this->gudang, $this->kabel, 30);
        $pck = $this->pckSelesai($req);

        $this->assertSame([(int) $this->binA->id], $pck->lines->pluck('bin_id')->map(fn ($b) => (int) $b)->unique()->values()->all());

        $sj = $this->sjBerangkat($pck, ['destination_type' => 'project_client', 'destination_project_id' => $proyek->id]);
        $sj = $this->buktiTerima($sj, 30);

        $this->assertSame(ShipmentStatus::Delivered, $sj->status);
        $this->assertSame(MaterialRequestStatus::Completed, $req->refresh()->status);
        $this->assertSame(20.0, $this->saldo($this->binA, $this->kabel));
        $this->assertSame(0.0, $this->saldo($this->binSistem($this->gudang, BinType::Receiving), $this->kabel));

        $jenis = StockEvent::query()->pluck('event_type')->map(fn ($e) => $e->value)->all();
        foreach ([StockEventType::GoodsReceived, StockEventType::GoodsShipped, StockEventType::GoodsDelivered] as $e) {
            $this->assertContains($e->value, $jenis);
        }

        // Saldo tetap bisa dibangun ulang dari kartu stok (BR-STK-01).
        $ledger = app(StockLedger::class)->rebuildFromLedger($this->kabel->id);
        $this->assertSame(20.0, $ledger[implode('|', [$this->kabel->id, $this->binA->id, 0, 0, 0, 'available'])] ?? null);
    }
}

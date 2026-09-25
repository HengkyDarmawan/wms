<?php

declare(strict_types=1);

namespace Tests\Feature\PurchaseRequest\Concerns;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Project;
use App\Domain\PurchaseRequest\Actions\CreatePurchaseRequest;
use App\Domain\PurchaseRequest\Actions\OrderPurchaseRequest;
use App\Domain\PurchaseRequest\Exceptions\PurchaseRequestRuleException;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\PurchaseRequest\Models\PurchaseRequestOrder;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Request\Exceptions\RequestRuleException;
use Tests\Feature\Receipt\Concerns\OutboundChain;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;

/**
 * Bahan uji modul Purchase Request: gudang CKG, vendor PT Baja Jaya, dan item
 * ReceiptFixtures; satu proyek aktif; PRQ manual dan catatan pemesanan lewat
 * aksi sungguhan.
 */
trait PurchaseFixtures
{
    use OutboundChain;
    use ReceiptFixtures;

    protected Project $proyek;

    protected function siapkanPembelian(): void
    {
        $this->siapkanPenerimaan();
        $this->proyek = $this->makeProject();
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $lines
     * @param  array<string, mixed>  $header
     */
    protected function prqManual(?array $lines = null, array $header = [], ?User $actor = null): PurchaseRequest
    {
        return app(CreatePurchaseRequest::class)->handle($header + [
            'warehouse_id' => $this->gudang->id,
            'project_id' => $this->proyek->id,
            'notes' => 'PRQ uji',
        ], $lines ?? [['item_id' => $this->baut->id, 'qty_base' => 100]], $actor ?? $this->makeUser('warehouse_staff'));
    }

    /**
     * Catatan pemesanan; tanpa `$qty` semua sisa baris dipesan ke vendor fixture.
     *
     * @param  array<int|string, mixed>|null  $qty  purchase_request_line_id => jumlah
     * @param  array<string, mixed>  $header
     */
    protected function pesan(PurchaseRequest $prq, ?array $qty = null, array $header = []): PurchaseRequestOrder
    {
        $qty ??= $prq->lines()->get()->mapWithKeys(fn ($l) => [$l->id => $l->unorderedQty()])->all();

        return app(OrderPurchaseRequest::class)->handle($prq->refresh(), $header + [
            'vendor_id' => $this->vendor->id,
            'external_po_no' => 'PO-2026-001',
            'eta_date' => now()->addDays(5)->toDateString(),
        ], $qty, $this->makeUser('pr_follow_up'));
    }

    protected function barisPesanan(PurchaseRequestOrder $order, int $index = 0): int
    {
        return (int) $order->lines()->orderBy('id')->get()[$index]->id;
    }

    protected function gagalPrq(callable $aksi, string $aturan): void
    {
        try {
            $aksi();
            $this->fail('Seharusnya ditolak '.$aturan.'.');
        } catch (PurchaseRequestRuleException|ReceiptRuleException|RequestRuleException $e) {
            $this->assertSame($aturan, $e->rule, $e->getMessage());
        }
    }
}

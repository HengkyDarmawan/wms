<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing\Concerns;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Exceptions\ApprovalRuleException;
use App\Domain\PurchaseRequest\Exceptions\PurchaseRequestRuleException;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\Purchasing\Actions\CreatePurchaseOrder;
use App\Domain\Purchasing\Actions\SubmitPurchaseOrder;
use App\Domain\Purchasing\Exceptions\PurchasingRuleException;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\VendorPrice;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use Tests\Feature\Approval\Concerns\ApprovalFixtures;
use Tests\Feature\PurchaseRequest\Concerns\PurchaseFixtures;

/**
 * Bahan uji Purchasing inti (purchasing/02 §10): PurchaseFixtures (gudang CKG,
 * vendor PT Baja Jaya, item, proyek), Penindak Lanjut PR, dan harga beli baut
 * Rp 1.500 di vendor fixture.
 */
trait PurchasingFixtures
{
    use ApprovalFixtures;
    use PurchaseFixtures;

    protected User $pembeli;

    protected function siapkanPurchasing(): void
    {
        $this->siapkanPembelian();
        $this->pembeli = $this->makeUser('pr_follow_up');

        VendorPrice::create([
            'vendor_id' => $this->vendor->id, 'item_id' => $this->baut->id,
            'unit_price' => 1500, 'currency' => 'IDR', 'valid_from' => now()->subMonth()->toDateString(), 'is_active' => true,
        ]);
    }

    /**
     * PO draf; tanpa `$lines` seluruh baris PRQ dipesan dengan harga 1.500.
     *
     * @param  array<int, array<string, mixed>>|null  $lines
     * @param  array<string, mixed>  $header
     */
    protected function poDraf(PurchaseRequest $prq, ?array $lines = null, array $header = [], ?User $actor = null): PurchaseOrder
    {
        $lines ??= $prq->lines()->get()->map(fn ($l) => [
            'purchase_request_line_id' => $l->id, 'qty_base' => $l->unorderedQty(), 'unit_price' => 1500,
        ])->all();

        return app(CreatePurchaseOrder::class)->handle($header + [
            'vendor_id' => $this->vendor->id,
            'warehouse_id' => $this->gudang->id,
            'eta_date' => now()->addDays(7)->toDateString(),
        ], $lines, $actor ?? $this->pembeli);
    }

    protected function ajukan(PurchaseOrder $po, ?User $actor = null): PurchaseOrder
    {
        return app(SubmitPurchaseOrder::class)->handle($po->refresh(), $actor ?? $this->pembeli);
    }

    /** @param  array<int, array<string, mixed>>|null  $lines */
    protected function poDisetujui(PurchaseRequest $prq, ?array $lines = null): PurchaseOrder
    {
        return $this->ajukan($this->poDraf($prq, $lines));
    }

    protected function gagalPo(callable $aksi, string $aturan): void
    {
        try {
            $aksi();
            $this->fail('Seharusnya ditolak '.$aturan.'.');
        } catch (PurchasingRuleException|PurchaseRequestRuleException|ReceiptRuleException|ApprovalRuleException $e) {
            $this->assertSame($aturan, $e->rule, $e->getMessage());
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Domain\PurchaseRequest\Models\PurchaseRequestOrder;
use App\Domain\Shared\Support\DocumentLineage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Purchasing\Concerns\PurchasingFixtures;
use Tests\TenantTestCase;

/**
 * TC-DOC-03 — dokumen terkait rantai pembelian PRQ → PO → GRN (A-252); PO
 * hanya tampil bagi pemegang `po.view` (harga, D-07).
 */
class PurchaseLineageTest extends TenantTestCase
{
    use PurchasingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPurchasing();
    }

    #[Test]
    public function tc_doc_03_rantai_prq_po_grn(): void
    {
        $prq = $this->prqManual([['item_id' => $this->baut->id, 'qty_base' => 40]]);
        $po = $this->poDisetujui($prq);
        $ref = (int) PurchaseRequestOrder::query()->where('purchase_order_id', $po->id)->sole()->lines()->sole()->id;
        $grn = $this->grnDiterima([['item_id' => $this->baut->id, 'qty_received' => 40, 'purchase_request_order_line_id' => $ref]]);

        $rantai = app(DocumentLineage::class);

        $this->actingAs($this->makeUser('company_admin'));
        $dariGrn = $rantai->for($grn);
        $this->assertEqualsCanonicalizing([$prq->number, $po->number], $dariGrn['asal']->pluck('number')->all());
        $this->assertEqualsCanonicalizing([$po->number, $grn->number], $rantai->for($prq)['turunan']->pluck('number')->all());
        $this->assertSame([$grn->number], $rantai->for($po)['turunan']->pluck('number')->all());

        // Kepala Gudang tidak memegang po.view: PO tidak tampil, PRQ tetap.
        $kepala = $this->makeUser('warehouse_head');
        $this->assertFalse($kepala->hasPermission('po.view'));
        $this->actingAs($kepala);
        $this->assertSame([$prq->number], $rantai->for($grn)['asal']->pluck('number')->all());
        $this->get($this->tenantUrl('receipts/'.$grn->id))->assertOk()
            ->assertSee(__('Dokumen terkait'))->assertSee($prq->number)->assertDontSee($po->number);
    }
}

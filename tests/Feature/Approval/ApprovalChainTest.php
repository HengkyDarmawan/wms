<?php

declare(strict_types=1);

namespace Tests\Feature\Approval;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Actions\DecideApproval;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApproverType;
use App\Domain\Approval\Exceptions\ApprovalRuleException;
use App\Domain\Approval\Models\ApprovalRule;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Request\Actions\ApproveRequest;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Shipment\Actions\CreatePickTask;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Warehouse\Models\Warehouse;
use Database\Seeders\Tenant\ApprovalDemoSeeder;
use Database\Seeders\Tenant\DemoSeeder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-APR-19 — rantai penuh dengan data demo (alur 1 + alur 9): REQ aset →
 * Kepala Gudang CKG → Manajemen → reservasi → PCK. TC-APR-21 — aturan demo
 * sama dengan docs/00-akun-uji.md §5.
 */
class ApprovalChainTest extends TenantTestCase
{
    private function akun(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function reqDemo(User $pemohon, string $kodeItem, float $qty): MaterialRequest
    {
        $req = app(SaveRequest::class)->handle(
            null,
            ['project_id' => Project::query()->where('code', 'PRJ-001')->value('id'), 'required_date' => now()->addDays(3)->toDateString()],
            [['item_id' => Item::query()->where('code', $kodeItem)->value('id'), 'qty_base' => $qty]],
            $pemohon,
        );

        $req->openLines()->first()->forceFill([
            'source_warehouse_id' => Warehouse::withoutGlobalScopes()->where('code', 'CKG')->value('id'),
            'fulfillment_source' => 'stock',
        ])->save();

        return app(SubmitRequest::class)->handle($req->refresh(), $pemohon);
    }

    private function gagal(callable $aksi, string $aturan): void
    {
        try {
            $aksi();
            $this->fail('Seharusnya ditolak '.$aturan.'.');
        } catch (ApprovalRuleException|RequestRuleException $e) {
            $this->assertSame($aturan, $e->rule, $e->getMessage());
        }
    }

    #[Test]
    public function tc_apr_19_req_aset_dua_lapis_sampai_picking(): void
    {
        (new DemoSeeder)->run();

        $indra = $this->akun('pemohon.prj001@demo.wms.test');
        $andi = $this->akun('kagudang.ckg@demo.wms.test');
        $sari = $this->akun('kagudang.bks@demo.wms.test');
        $budi = $this->akun('manajemen@demo.wms.test');

        $req = $this->reqDemo($indra, 'GENSET-5KVA', 1);
        $this->assertSame(MaterialRequestStatus::PendingApproval, $req->status);

        $s = ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::MaterialRequest, (int) $req->id)->sole();
        $this->assertSame(ApprovalDemoSeeder::REQ_BESAR, $s->rule_name, 'Barang aset → aturan REQ besar atau aset.');
        $this->assertSame([[(int) $andi->id], [(int) $budi->id]], array_column($s->steps, 'approver_user_ids'));

        $this->gagal(fn () => app(ApproveRequest::class)->handle($req, $indra), 'BR-REQ-07');
        $this->gagal(fn () => app(ApproveRequest::class)->handle($req, $sari), 'BR-APR-01');
        $this->gagal(fn () => app(ApproveRequest::class)->handle($req, $budi), 'BR-APR-01');

        $req = app(ApproveRequest::class)->handle($req, $andi);
        $this->assertSame(MaterialRequestStatus::PendingApproval, $req->status);
        $this->assertSame(0, StockReservation::query()->forDocument('material_request', (int) $req->id)->count());

        $req = app(ApproveRequest::class)->handle($req, $budi);
        $this->assertSame(MaterialRequestStatus::Approved, $req->status);
        $this->assertSame(1, StockReservation::query()->active()->forDocument('material_request', (int) $req->id)->count());

        $pck = app(CreatePickTask::class)->handle($req, $andi);
        $this->assertCount(1, $pck);
        $this->assertSame(1.0, (float) $pck[0]->lines()->sum('qty_allocated'));
        $this->assertSame(MaterialRequestStatus::InProgress, $req->refresh()->status);

        // REQ biasa: satu lapis Kepala Gudang CKG saja.
        $biasa = $this->reqDemo($indra, 'BAUT-M12', 5);
        $sb = ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::MaterialRequest, (int) $biasa->id)->sole();
        $this->assertSame(ApprovalDemoSeeder::REQ_LAINNYA, $sb->rule_name);
        app(DecideApproval::class)->approveDocument(ApprovalDocumentType::MaterialRequest, (int) $biasa->id, $andi);
        $this->assertSame(MaterialRequestStatus::Approved, $biasa->refresh()->status);
    }

    #[Test]
    public function tc_apr_21_aturan_demo_sama_dengan_akun_uji(): void
    {
        (new DemoSeeder)->run();

        // REQ ×2, RTV, ADJ ×2, OPN, PRQ (00-akun-uji §5; ADJ/OPN sejak modul Count/Adjustment, PRQ sejak modul PRQ, PO sejak Purchasing inti).
        $this->assertSame(8, ApprovalRule::count());

        $besar = ApprovalRule::query()->where('name', ApprovalDemoSeeder::REQ_BESAR)->with('steps')->sole();
        $this->assertSame(ApprovalDocumentType::MaterialRequest, $besar->document_type);
        $this->assertSame(['match' => 'any', 'line_count_min' => 21, 'ownership_models' => ['asset']], $besar->conditions);
        $this->assertSame([ApproverType::WarehouseHead, ApproverType::Role], $besar->steps->pluck('approver_type')->all());

        $this->assertSame(1, ApprovalRule::query()->where('name', ApprovalDemoSeeder::REQ_LAINNYA)->sole()->steps()->count());
        $this->assertSame(ApprovalDocumentType::VendorReturn, ApprovalRule::query()->where('name', ApprovalDemoSeeder::RTV)->sole()->document_type);

        $prq = ApprovalRule::query()->where('name', ApprovalDemoSeeder::PRQ_ONLINE)->with('steps')->sole();
        $this->assertSame(ApprovalDocumentType::PurchaseRequest, $prq->document_type);
        $this->assertSame(['match' => 'all', 'vendor_types' => ['online_marketplace']], $prq->conditions);
        $this->assertSame([ApproverType::WarehouseHead, ApproverType::Role], $prq->steps->pluck('approver_type')->all());

        // Satu-satunya kondisi nilai uang: PO (D-28, A-212).
        $po = ApprovalRule::query()->where('name', ApprovalDemoSeeder::PO_BESAR)->with('steps')->sole();
        $this->assertSame(ApprovalDocumentType::PurchaseOrder, $po->document_type);
        $this->assertEquals(['match' => 'all', 'order_value_min' => 50000000], $po->conditions);
        $this->assertSame([ApproverType::Role], $po->steps->pluck('approver_type')->all());

        (new ApprovalDemoSeeder)->run();
        $this->assertSame(8, ApprovalRule::count(), 'Seeder aman dijalankan ulang.');
        $this->assertSame(2, $besar->steps()->count());
    }
}

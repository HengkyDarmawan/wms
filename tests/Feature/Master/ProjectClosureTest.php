<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Domain\Master\Actions\ChangeProjectStatus;
use App\Domain\Master\Enums\ProjectStatus;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Support\ProjectClosureChecklist;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Enums\BinStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Transfer\Concerns\TransferFixtures;
use Tests\TenantTestCase;

/**
 * TC-MST-20 — guard penutupan proyek: saldo di salah satu Gudang Site
 * menghalangi tutup/batal; setelah kosong, proyek ditutup dan semua Gudang
 * Site-nya nonaktif (BR-PRJ-02, BR-PRJ-04, A-187).
 */
class ProjectClosureTest extends TenantTestCase
{
    use TransferFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanTransfer();
    }

    #[Test]
    public function tc_mst_20_proyek_hanya_ditutup_bila_checklist_bersih(): void
    {
        $this->stok($this->binKrw2, $this->baut, 4);
        $aksi = app(ChangeProjectStatus::class);
        $admin = $this->makeUser('company_admin');

        $butir = app(ProjectClosureChecklist::class)->blockers($this->proyek);
        $this->assertCount(1, $butir);
        $this->assertStringContainsString('Gudang Site '.$this->krw2->code, $butir[0]);

        foreach ([ProjectStatus::Closed, ProjectStatus::Cancelled] as $target) {
            try {
                $aksi->handle($this->proyek->refresh(), $target, 'SELESAI', null, $admin);
                $this->fail('Seharusnya ditolak BR-PRJ-02.');
            } catch (MasterRuleException $e) {
                $this->assertSame('BR-PRJ-02', $e->rule);
                $this->assertStringContainsString('ISU', $e->getMessage());
            }
        }

        // Sisa dijadikan pemakaian/dikeluarkan → checklist bersih.
        app(StockLedger::class)->post(new MovementRequest(item: $this->baut, qtyBase: 4, fromBinId: $this->binKrw2->id));
        $this->assertSame([], app(ProjectClosureChecklist::class)->blockers($this->proyek));

        // REQ yang masih menunggu keputusan juga menghalangi (A-187).
        $req = MaterialRequest::create([
            'number' => 'REQ/TUTUP/0001', 'project_id' => $this->proyek->id, 'requester_id' => $admin->id,
            'status' => MaterialRequestStatus::Submitted, 'required_date' => now()->addWeek()->toDateString(),
        ]);
        $this->assertStringContainsString('REQ/TUTUP/0001', implode(' ', app(ProjectClosureChecklist::class)->blockers($this->proyek)));
        $req->forceFill(['status' => MaterialRequestStatus::Cancelled])->save();

        // Draf tidak menghalangi penutupan, tetapi tidak bisa diajukan sesudahnya (BR-PRJ-01).
        $draf = MaterialRequest::create([
            'number' => 'REQ/TUTUP/0002', 'project_id' => $this->proyek->id, 'requester_id' => $admin->id,
            'status' => MaterialRequestStatus::Draft, 'required_date' => now()->addWeek()->toDateString(),
        ]);

        $proyek = $aksi->handle($this->proyek->refresh(), ProjectStatus::Closed, 'SELESAI', 'Serah terima akhir', $admin);
        $this->assertSame(ProjectStatus::Closed, $proyek->status);
        $this->assertFalse($this->krw1->refresh()->is_active, 'BR-PRJ-04');
        $this->assertFalse($this->krw2->refresh()->is_active, 'BR-PRJ-04');
        $this->assertSame(0, $this->krw2->bins()->where('bin_status', BinStatus::Active->value)->count(), 'Bin Gudang Site ikut nonaktif.');

        try {
            app(SubmitRequest::class)->handle($draf, $admin);
            $this->fail('Draf proyek tertutup tidak boleh diajukan.');
        } catch (RequestRuleException $e) {
            $this->assertSame('BR-PRJ-01', $e->rule);
        }
    }
}

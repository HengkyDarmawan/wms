<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Master\Livewire\ProjectDetail;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Shared\Support\DocumentLineage;
use App\Domain\Shipment\Models\PickTask;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Return\Concerns\ReturnFixtures;
use Tests\TenantTestCase;

/**
 * TC-DOC-01, TC-DOC-02 — dokumen terkait (asal & turunan) di layar detail dan
 * linimasa dokumen di tab Riwayat hub proyek (A-252).
 */
class DocumentLineageTest extends TenantTestCase
{
    use ReturnFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanTransfer();
    }

    /** @param  array{asal: Collection, turunan: Collection}  $r */
    private function nomor(array $r, string $arah): array
    {
        return $r[$arah]->pluck('number')->all();
    }

    #[Test]
    public function tc_doc_01_rantai_req_pck_sj_ret_dan_layar(): void
    {
        $sj = $this->terimaSj($this->terkirimKeKlien($this->baut, 10));
        $pck = PickTask::query()->withoutGlobalScopes()->sole();
        $req = MaterialRequest::query()->withoutGlobalScopes()->findOrFail($pck->source_id);
        $ret = $this->ret([['key' => 'sold:'.$sj->lines()->first()->id, 'qty_base' => 2]]);

        $kepala = $this->makeUser('warehouse_head');
        $this->actingAs($kepala);
        $rantai = app(DocumentLineage::class);

        $dariSj = $rantai->for($sj);
        $this->assertEqualsCanonicalizing([$pck->number, $req->number], $this->nomor($dariSj, 'asal'), 'SJ menampilkan PCK dan REQ asal.');
        $this->assertContains($ret->number, $this->nomor($dariSj, 'turunan'), 'RET yang merujuk SJ asal.');

        $dariReq = $rantai->for($req);
        $this->assertContains($pck->number, $this->nomor($dariReq, 'turunan'));
        $this->assertContains($sj->number, $this->nomor($dariReq, 'turunan'));

        $this->assertSame([$req->number], $this->nomor($rantai->for($pck), 'asal'));
        $this->assertSame([$sj->number], $this->nomor($rantai->for($ret), 'asal'));

        // Layar detail memuat kartu dokumen terkait bertaut.
        $this->get($this->tenantUrl('shipments/'.$sj->id))->assertOk()
            ->assertSee(__('Dokumen terkait'))->assertSee(route('requests.show', $req->id))->assertSee(route('picks.show', $pck->id));
        $this->get($this->tenantUrl('requests/'.$req->id))->assertOk()->assertSee(route('shipments.show', $sj->id));
        $this->get($this->tenantUrl('returns/'.$ret->id))->assertOk()->assertSee(route('shipments.show', $sj->id));

        // Pengguna bercakupan proyek lain tidak melihat dokumen di luar cakupannya.
        $lain = $this->makeUser('internal_requester', ScopeType::Project, $this->makeProject()->id);
        $this->actingAs($lain);
        $this->assertSame([], $this->nomor($rantai->for($sj), 'asal'));
    }

    #[Test]
    public function tc_doc_02_linimasa_dokumen_di_hub_proyek(): void
    {
        $sj = $this->terimaSj($this->terkirimKeKlien($this->baut, 10));
        $ret = $this->ret([['key' => 'sold:'.$sj->lines()->first()->id, 'qty_base' => 1]]);
        $req = MaterialRequest::query()->withoutGlobalScopes()->where('project_id', $this->proyek->id)->sole();

        $this->actingAs($this->makeUser('warehouse_head'));

        Livewire::test(ProjectDetail::class, ['project' => $this->proyek])
            ->call('pilihTab', 'riwayat')
            ->assertSee(__('Linimasa dokumen proyek'))
            ->assertSee($req->number)->assertSee($sj->number)->assertSee($ret->number)
            ->assertSee(__('Riwayat perubahan proyek'));
    }
}

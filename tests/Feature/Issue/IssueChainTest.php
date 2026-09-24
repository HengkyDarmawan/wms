<?php

declare(strict_types=1);

namespace Tests\Feature\Issue;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\User;
use App\Domain\Issue\Actions\ApproveMaterialIssue;
use App\Domain\Issue\Actions\CreateMaterialIssue;
use App\Domain\Issue\Enums\MaterialIssueStatus;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Shared\Reports\ReportRegistry;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Warehouse\Enums\BinType;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Issue\Concerns\IssueFixtures;
use Tests\TenantTestCase;

/**
 * TC-ISU-12 s.d. TC-ISU-13 — rantai penuh REQ → SJ ke Gudang Site → GRN → PUT
 * → ISU → laporan Material per Proyek (diminta, terkirim, terpakai, diretur,
 * posisi), dibaca dari kartu stok (BR-PRJ-08, A-151).
 */
class IssueChainTest extends TenantTestCase
{
    use IssueFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPemakaian();
    }

    /** @return array<string, mixed> */
    private function barisLaporan(string $kodeItem, array $filters = []): array
    {
        $baris = app(ReportRegistry::class)->find('material-per-proyek')
            ->rows($filters + ['project_id' => (string) $this->proyek->id])
            ->firstWhere('kode_item', $kodeItem);

        $this->assertNotNull($baris, 'Baris '.$kodeItem.' ada di laporan.');

        return $baris;
    }

    #[Test]
    public function tc_isu_12_rantai_req_sj_grn_site_isu_sampai_laporan_material_per_proyek(): void
    {
        // Alur 1 + 2: REQ 30 dari CKG dikirim ke Gudang Site KRW1, diterima GRN, di-put-away.
        $req = $this->kirimKeSite($this->baut, 30);
        $this->assertSame(30.0, $this->saldo($this->binKrw1, $this->baut));
        $this->assertSame(70.0, $this->saldo($this->binA, $this->baut));

        // Alur 3: pemakaian di site.
        $staf = $this->stafSite();
        $isu = $this->konfirmasi($this->isu([['key' => $this->kunciIsu($this->binKrw1, $this->baut), 'qty_base' => 12, 'work_note' => 'Pagar site']], [], $staf), $staf);
        $this->assertSame(MaterialIssueStatus::Confirmed, $isu->status);
        $this->assertSame(18.0, $this->saldo($this->binKrw1, $this->baut));
        $this->assertSame(1, StockEvent::query()->where('event_type', StockEventType::MaterialConsumed->value)->where('project_id', $this->proyek->id)->count());

        // Retur sisa 5 ke CKG (alur 4) dan barang jual-putus 20 langsung ke klien.
        $ret = $this->ret([['key' => $this->kunciSite($this->binKrw1, $this->baut), 'qty_base' => 5]]);
        $this->grnRetur($ret);
        $this->terimaSj($this->terkirimKeKlien($this->baut, 20));

        $this->actingAs($this->makeUser('warehouse_head'));
        $baris = $this->barisLaporan('BAUT-M12');

        $this->assertSame($this->proyek->code, $baris['proyek']);
        $this->assertSame(50.0, $baris['diminta'], 'REQ site 30 + REQ klien 20.');
        $this->assertSame(50.0, $baris['terkirim'], 'GRN di Gudang Site 30 + jual-putus diterima 20.');
        $this->assertSame(12.0, $baris['terpakai']);
        $this->assertSame(5.0, $baris['diretur']);
        $this->assertSame(13.0, $baris['di_site'], '30 − 12 − 5.');
        $this->assertSame(0.0, $baris['aset_proyek']);
        $this->assertSame((float) $req->lines()->sum('qty_base'), 30.0);

        // ISU pembalik yang disetujui mengurangi Terpakai dan mengembalikan stok site.
        $balik = app(CreateMaterialIssue::class)->reverse($isu, [], $this->alasan(ReasonContext::Cancel), null, $staf);
        $balik = $this->konfirmasi($balik, $this->stafSite());
        // Lapis minimum: Kepala Gudang yang mencakup Gudang Site (di sini bercakupan semua, A-150).
        $approver = User::query()->findOrFail($balik->snapshot->steps[0]['approver_user_ids'][0]);
        $this->assertContains('warehouse_head', $approver->roleCodes());
        app(ApproveMaterialIssue::class)->approve($balik, $approver);

        $baris = $this->barisLaporan('BAUT-M12');
        $this->assertSame(0.0, $baris['terpakai']);
        $this->assertSame(25.0, $baris['di_site']);

        // Penyaring item & cakupan: pemohon proyek lain tidak melihat proyek ini.
        $this->assertSame([], app(ReportRegistry::class)->find('material-per-proyek')->rows(['project_id' => (string) $this->proyek->id, 'item' => 'SEMEN'])->all());
        $this->actingAs($this->makeUser('internal_requester', ScopeType::Project, $this->makeProject()->id));
        $this->assertSame([], app(ReportRegistry::class)->find('material-per-proyek')->rows(['project_id' => (string) $this->proyek->id])->all());
    }

    #[Test]
    public function tc_isu_13_transfer_dalam_proyek_tidak_menambah_terkirim_dan_izin_laporan(): void
    {
        $this->kirimKeSite($this->baut, 10);

        // TRF KRW1 → KRW2 (dalam proyek, A-50) tidak menambah Terkirim.
        $trf = $this->trf($this->krw1, $this->krw2, [['item_id' => $this->baut->id, 'qty_base' => 4]]);
        $pck = $this->jalankanPck($this->pckTrf($trf));
        $sj = $this->sjDari($pck, $this->krw2, ['shipment_method' => 'self_delivered', 'carried_by_name' => 'PIC titik', 'vehicle_id' => null, 'driver_id' => null]);
        $grn = $this->grnTransferSelesai($this->terimaSj($sj), $this->krw2);
        $this->putSelesai($grn, $this->binKrw2);

        $this->actingAs($this->makeUser('company_admin'));
        $baris = $this->barisLaporan('BAUT-M12');
        $this->assertSame(10.0, $baris['terkirim'], 'Pemindahan antar titik proyek yang sama tidak dihitung.');
        $this->assertSame(10.0, $baris['di_site'], 'KRW1 6 + KRW2 4.');

        // Laporan tercantum untuk pemegang issue.view saja.
        $this->assertTrue(app(ReportRegistry::class)->availableTo($this->makeUser('internal_auditor'))->contains(fn ($r) => $r->key() === 'material-per-proyek'));
        $this->assertFalse(app(ReportRegistry::class)->availableTo($this->makeUser('driver'))->contains(fn ($r) => $r->key() === 'material-per-proyek'));
        $this->assertSame(BinType::Storage, $this->binKrw2->bin_type);
    }
}

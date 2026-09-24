<?php

declare(strict_types=1);

namespace Tests\Feature\Issue;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Issue\Actions\CancelMaterialIssue;
use App\Domain\Issue\Actions\CreateMaterialIssue;
use App\Domain\Issue\Enums\MaterialIssueStatus;
use App\Domain\Issue\Models\MaterialIssue;
use App\Domain\Issue\Policies\MaterialIssuePolicy;
use App\Domain\Issue\Support\IssuableStock;
use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\ProjectStatus;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\Serial;
use App\Domain\Master\Models\Uom;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Warehouse\Enums\BinStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Issue\Concerns\IssueFixtures;
use Tests\TenantTestCase;

/**
 * TC-ISU-01 s.d. TC-ISU-08 — ISU biasa: draf, guard (BR-PRJ-08, BR-PRJ-01,
 * BR-ACC-05, BR-STK-03/06/08/09, BR-LED-02/04, BR-OPN-02), konfirmasi dengan
 * kejadian `material_consumed`, batal, ubah draf (Katalog §2.9, A-117, A-119).
 */
class IssueTest extends TenantTestCase
{
    use IssueFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPemakaian();
        $this->stok($this->binKrw1, $this->baut, 20);
    }

    #[Test]
    public function tc_isu_01_draf_lalu_konfirmasi_keluar_dari_gudang_site(): void
    {
        $staf = $this->stafSite();
        $isu = $this->isu([['key' => $this->kunciIsu($this->binKrw1, $this->baut), 'qty_base' => 12, 'work_note' => 'Angkur pondasi STA 0+100']], [], $staf);

        $this->assertSame(MaterialIssueStatus::Draft, $isu->status);
        $this->assertMatchesRegularExpression('#^ISU/KRW1/\d{4}/0001$#', $isu->number, 'BR-GEN-06: segmen = Gudang Site.');
        $this->assertSame((int) $staf->id, (int) $isu->issued_by);
        $this->assertSame(20.0, $this->saldo($this->binKrw1, $this->baut), 'Draf belum menggerakkan stok.');
        $this->assertSame(0, StockMovement::query()->where('document_type', 'material_issue')->count());

        $isu = $this->konfirmasi($isu, $staf);

        $this->assertSame(MaterialIssueStatus::Confirmed, $isu->status);
        $this->assertSame((int) $staf->id, (int) $isu->confirmed_by);
        $this->assertNotNull($isu->confirmed_at);
        $this->assertSame(8.0, $this->saldo($this->binKrw1, $this->baut));

        $baris = $isu->lines()->sole();
        $gerak = StockMovement::query()->findOrFail($baris->movement_id);
        $this->assertSame((int) $this->binKrw1->id, (int) $gerak->from_bin_id);
        $this->assertNull($gerak->to_bin_id, 'Keluar dari stok (dipakai proyek).');
        $this->assertSame((int) $this->proyek->id, (int) $gerak->project_id);

        $kejadian = StockEvent::query()->where('source_type', 'material_issue')->where('source_id', $isu->id)->sole();
        $this->assertSame(StockEventType::MaterialConsumed, $kejadian->event_type);
        $this->assertSame((int) $this->proyek->id, (int) $kejadian->project_id, 'Matriks §14: penanda proyek.');
        $this->assertSame('Angkur pondasi STA 0+100', $kejadian->payload['work_note']);
        $this->assertSame($isu->number, $kejadian->payload['issue_number']);
        $this->assertSame(12.0, (float) $kejadian->payload['qty_base']);

        $this->gagalIsu(fn () => $this->konfirmasi($isu, $staf), 'BR-GEN-01');
    }

    #[Test]
    public function tc_isu_02_guard_proyek_gudang_baris_dan_cakupan(): void
    {
        $kunci = $this->kunciIsu($this->binKrw1, $this->baut);
        $baris = [['key' => $kunci, 'qty_base' => 5]];

        // BR-PRJ-08: hanya Gudang Site milik proyek itu.
        $this->gagalIsu(fn () => $this->isu($baris, ['warehouse_id' => $this->gudang->id], $this->makeUser('warehouse_staff')), 'BR-PRJ-08');
        $proyekLain = $this->makeProject();
        $siteLain = $this->buatGudangSite('SBY1', $proyekLain);
        $this->gagalIsu(fn () => $this->isu($baris, ['warehouse_id' => $siteLain->id], $this->makeUser('warehouse_staff')), 'BR-PRJ-08');

        // Tanpa baris, jumlah nol/negatif, bukan stok Gudang Site ini, melebihi saldo.
        $this->gagalIsu(fn () => $this->isu([]), 'BR-PRJ-08');
        $this->gagalIsu(fn () => $this->isu([['key' => $kunci, 'qty_base' => -3]]), 'BR-LED-02');
        $this->gagalIsu(fn () => $this->isu([['key' => $this->kunciIsu($this->binA, $this->baut), 'qty_base' => 1]]), 'BR-PRJ-08');
        $this->gagalIsu(fn () => $this->isu([['key' => $kunci, 'qty_base' => 21]]), 'BR-STK-06');
        $this->gagalIsu(fn () => $this->isu([['key' => $kunci, 'qty_base' => 15], ['key' => $kunci, 'qty_base' => 6]]), 'BR-STK-06');

        // BR-ACC-05: staf gudang lain dan pemohon proyek lain.
        $stafBks = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->bks->id);
        $this->gagalIsu(fn () => $this->isu($baris, [], $stafBks), 'BR-ACC-05');
        $pemohonLain = $this->makeUser('internal_requester', ScopeType::Project, $proyekLain->id);
        $this->gagalIsu(fn () => $this->isu($baris, [], $pemohonLain), 'BR-ACC-05');

        // Pemohon proyek boleh mencatat draf (Katalog §2.9 aktor).
        $pemohon = $this->makeUser('internal_requester', ScopeType::Project, $this->proyek->id);
        $draf = $this->isu($baris, [], $pemohon);
        $this->assertSame(MaterialIssueStatus::Draft, $draf->status);
        $this->assertFalse(app(MaterialIssuePolicy::class)->confirm($pemohon, $draf), 'Konfirmasi oleh Staf Gudang Site.');

        // BR-PRJ-01: proyek ditutup menolak ISU baru dan konfirmasi.
        $this->proyek->forceFill(['status' => ProjectStatus::Closed])->save();
        $this->gagalIsu(fn () => $this->isu($baris), 'BR-PRJ-01');
        $this->gagalIsu(fn () => $this->konfirmasi($draf), 'BR-PRJ-01');
    }

    #[Test]
    public function tc_isu_03_aset_tidak_dipakai_habis(): void
    {
        $serial = Serial::create(['item_id' => $this->genset->id, 'serial_no' => 'GNS-01', 'acquired_at' => now()->toDateString()]);
        $this->stok($this->binKrw1, $this->genset, 1, ['serial_id' => $serial->id]);

        $calon = app(IssuableStock::class)->selectable($this->krw1);
        $kunci = $this->kunciIsu($this->binKrw1, $this->genset, ['serial_id' => $serial->id]);

        $this->assertFalse($calon->has($kunci), 'BR-STK-08: aset tidak ditawarkan.');
        $this->assertTrue($calon->has($this->kunciIsu($this->binKrw1, $this->baut)));
        $this->gagalIsu(fn () => $this->isu([['key' => $kunci, 'qty_base' => 1]]), 'BR-PRJ-08');
    }

    #[Test]
    public function tc_isu_04_lot_serial_habis_pakai_dan_potongan_utuh(): void
    {
        $lot = Lot::create(['item_id' => $this->semen->id, 'lot_no' => 'LOT-S1', 'expiry_date' => now()->addMonths(6)->toDateString(), 'received_at' => now()->toDateString()]);
        $this->stok($this->binKrw1, $this->semen, 50, ['lot_id' => $lot->id]);

        $meter = $this->buatItem('METER-AIR', TrackingMode::Serial, Uom::query()->where('code', 'PCS')->value('id'), ['ownership_model' => OwnershipModel::Consumable]);
        $sn = Serial::create(['item_id' => $meter->id, 'serial_no' => 'MTR-01', 'acquired_at' => now()->toDateString()]);
        $this->stok($this->binKrw1, $meter, 1, ['serial_id' => $sn->id]);

        $potong = Piece::create(['item_id' => $this->pipa->id, 'piece_no' => 'P-ISU-1', 'length' => 6, 'is_offcut' => false]);
        $this->stok($this->binKrw1, $this->pipa, 6, ['piece_id' => $potong->id]);

        $kunciPipa = $this->kunciIsu($this->binKrw1, $this->pipa, ['piece_id' => $potong->id]);
        $kunciMeter = $this->kunciIsu($this->binKrw1, $meter, ['serial_id' => $sn->id]);

        $this->gagalIsu(fn () => $this->isu([['key' => $kunciPipa, 'qty_base' => 2]]), 'BR-STK-09');
        $this->gagalIsu(fn () => $this->isu([['key' => $kunciMeter, 'qty_base' => 0.5]]), 'BR-LED-04');

        $isu = $this->konfirmasi($this->isu([
            ['key' => $this->kunciIsu($this->binKrw1, $this->semen, ['lot_id' => $lot->id]), 'qty_base' => 20, 'work_note' => 'Lantai kerja'],
            ['key' => $kunciMeter, 'qty_base' => 1],
            ['key' => $kunciPipa, 'qty_base' => 6],
        ]));

        $this->assertSame(MaterialIssueStatus::Confirmed, $isu->status);
        $this->assertSame(30.0, $this->saldo($this->binKrw1, $this->semen));
        $this->assertSame(0.0, $this->saldo($this->binKrw1, $meter));
        $this->assertSame(0.0, $this->saldo($this->binKrw1, $this->pipa));
        $this->assertSame([(int) $lot->id], StockMovement::query()->where('document_type', 'material_issue')->where('item_id', $this->semen->id)->pluck('lot_id')->map(fn ($v) => (int) $v)->all());
        $this->assertSame(3, StockEvent::query()->where('event_type', StockEventType::MaterialConsumed->value)->where('source_id', $isu->id)->count());
    }

    #[Test]
    public function tc_isu_05_stok_tersedia_diperiksa_ulang_saat_konfirmasi(): void
    {
        $kunci = $this->kunciIsu($this->binKrw1, $this->baut);
        $draf = $this->isu([['key' => $kunci, 'qty_base' => 15]]);

        // ISU lain memakai lebih dulu.
        $this->konfirmasi($this->isu([['key' => $kunci, 'qty_base' => 10]]));
        $this->gagalIsu(fn () => $this->konfirmasi($draf), 'BR-STK-06');
        $this->assertSame(MaterialIssueStatus::Draft, $draf->refresh()->status);

        // TRF dari KRW1 memegang alokasi keras di bin (BR-STK-04): tersedia berkurang.
        $this->trf($this->krw1, $this->krw2, [['item_id' => $this->baut->id, 'qty_base' => 6]]);
        $calon = app(IssuableStock::class)->selectable($this->krw1);
        $this->assertSame(10.0, $calon[$kunci]['balance']);
        $this->assertSame(4.0, $calon[$kunci]['max']);
        $this->gagalIsu(fn () => $this->isu([['key' => $kunci, 'qty_base' => 5]]), 'BR-STK-06');
        $this->assertSame(MaterialIssueStatus::Confirmed, $this->konfirmasi($this->isu([['key' => $kunci, 'qty_base' => 4]]))->status);
    }

    #[Test]
    public function tc_isu_06_bin_dibeku_menolak_isu_baru(): void
    {
        $kunci = $this->kunciIsu($this->binKrw1, $this->baut);
        $draf = $this->isu([['key' => $kunci, 'qty_base' => 5]]);

        $this->binKrw1->forceFill(['bin_status' => BinStatus::Frozen])->save();

        $this->assertTrue(app(IssuableStock::class)->selectable($this->krw1)[$kunci]['frozen']);
        $this->gagalIsu(fn () => $this->isu([['key' => $kunci, 'qty_base' => 5]]), 'BR-OPN-02');
        $this->gagalIsu(fn () => $this->konfirmasi($draf), 'BR-OPN-02');
        $this->assertSame(20.0, $this->saldo($this->binKrw1, $this->baut));
    }

    #[Test]
    public function tc_isu_07_batal_draf_dengan_alasan_dan_isu_dikonfirmasi_tidak_bisa_dibatalkan(): void
    {
        $staf = $this->stafSite();
        $kunci = $this->kunciIsu($this->binKrw1, $this->baut);
        $draf = $this->isu([['key' => $kunci, 'qty_base' => 5]], [], $staf);
        $batal = app(CancelMaterialIssue::class);

        $this->gagalIsu(fn () => $batal->handle($draf, null, null, $staf), 'BR-GEN-11');
        $this->gagalIsu(fn () => $batal->handle($draf, $this->alasan(ReasonContext::Reject), null, $staf), 'BR-GEN-11');

        $draf = $batal->handle($draf, $this->alasan(ReasonContext::Cancel), 'Salah proyek', $staf);
        $this->assertSame(MaterialIssueStatus::Cancelled, $draf->status);
        $this->assertNotNull($draf->cancel_reason_id);
        $this->gagalIsu(fn () => $this->konfirmasi($draf, $staf), 'BR-GEN-01');

        $isu = $this->konfirmasi($this->isu([['key' => $kunci, 'qty_base' => 5]], [], $staf), $staf);
        $this->gagalIsu(fn () => $batal->handle($isu, $this->alasan(ReasonContext::Cancel), null, $staf), 'BR-GEN-04');

        // Katalog §2.9 aktor batal = pembuat (atau pemegang issue.approve, A-119).
        $draf2 = $this->isu([['key' => $kunci, 'qty_base' => 1]], [], $staf);
        $policy = app(MaterialIssuePolicy::class);
        $this->assertTrue($policy->cancel($staf, $draf2));
        $this->assertFalse($policy->cancel($this->stafSite(), $draf2));
        $this->assertTrue($policy->cancel($this->makeUser('warehouse_head'), $draf2));
    }

    #[Test]
    public function tc_isu_08_ubah_draf(): void
    {
        $pemohon = $this->makeUser('internal_requester', ScopeType::Project, $this->proyek->id);
        $kunci = $this->kunciIsu($this->binKrw1, $this->baut);
        $draf = $this->isu([['key' => $kunci, 'qty_base' => 5]], [], $pemohon);

        $policy = app(MaterialIssuePolicy::class);
        $this->assertTrue($policy->update($pemohon, $draf), 'Pembuat.');
        $this->assertTrue($policy->update($this->stafSite(), $draf), 'Staf yang akan mengonfirmasi.');
        $this->assertFalse($policy->update($this->makeUser('internal_requester', ScopeType::Project, $this->proyek->id), $draf));

        $draf = app(CreateMaterialIssue::class)->update($draf, ['notes' => 'Revisi'], [['key' => $kunci, 'qty_base' => 7, 'work_note' => 'Bekisting']], $pemohon);
        $this->assertSame(7.0, (float) $draf->lines()->sole()->qty_base);
        $this->assertSame('Bekisting', $draf->lines()->sole()->work_note);
        $this->assertSame('Revisi', $draf->notes);

        $this->gagalIsu(fn () => app(CreateMaterialIssue::class)->update($draf, [], [['key' => $kunci, 'qty_base' => 50]], $pemohon), 'BR-STK-06');

        $isu = $this->konfirmasi($draf);
        $this->assertSame(13.0, $this->saldo($this->binKrw1, $this->baut));
        $this->assertFalse($policy->update($pemohon, $isu));
        $this->gagalIsu(fn () => app(CreateMaterialIssue::class)->update($isu, [], [['key' => $kunci, 'qty_base' => 1]], $pemohon), 'BR-GEN-01');
        $this->assertSame(1, MaterialIssue::query()->count());
    }
}

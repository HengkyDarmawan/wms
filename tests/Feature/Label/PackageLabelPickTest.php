<?php

declare(strict_types=1);

namespace Tests\Feature\Label;

use App\Domain\Label\Actions\CancelLabel;
use App\Domain\Label\Actions\CreateContentLabels;
use App\Domain\Label\Enums\PackageLabelStatus;
use App\Domain\Label\Models\PackageLabelMove;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Receipt\Actions\ReceiveGoodsReceipt;
use App\Domain\Receipt\Actions\SaveGoodsReceipt;
use App\Domain\Shipment\Actions\CreatePickTask;
use App\Domain\Shipment\Actions\ProcessPickTask;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Shipment\Livewire\PickDetail;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Enums\BinType;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Label\Concerns\LabelFixtures;
use Tests\TenantTestCase;

/**
 * TC-PCK-19 s.d. TC-PCK-22, TC-SJ-21, TC-TRF-21 — barang berlabel wajib
 * dipindai saat keluar gudang (A-299): PCK menolak tanpa pindai, label Keluar
 * saat PCK selesai, nomor SJ tertempel, dan label utuh dibuka lagi di gudang
 * tujuan transfer (A-300).
 */
class PackageLabelPickTest extends TenantTestCase
{
    use LabelFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanLabel();
    }

    /** PCK REQ $qty baut yang sudah dimulai. */
    private function pckDimulai(float $qty): PickTask
    {
        $req = $this->reqDisetujui($this->makeProject(), $this->gudang, $this->baut, $qty);
        $pck = app(CreatePickTask::class)->handle($req, $this->makeUser('warehouse_head'))[0];

        return app(ProcessPickTask::class)->start($pck, $this->makeUser());
    }

    private function gagal(callable $aksi, string $aturan): string
    {
        try {
            $aksi();
            $this->fail('Seharusnya ditolak '.$aturan.'.');
        } catch (ShipmentRuleException $e) {
            $this->assertSame($aturan, $e->rule, $e->getMessage());

            return $e->getMessage();
        }
    }

    #[Test]
    public function tc_pck_19_pindai_label_induk_memilih_jumlah_isi_dan_label_isi_langsung_diklaim(): void
    {
        $grn = $this->grnBerlabel($this->baut, 36, 3, 12);
        $this->putAway($grn);
        [$a, $b, $c] = $this->labelGrn($grn)->all();
        $isi = app(CreateContentLabels::class)->handle($c, 12, $this->makeUser());

        $pck = $this->pckDimulai(20);
        $baris = $pck->lines()->sole();

        $layar = Livewire::actingAs($this->makeUser())->test(PickDetail::class, ['pickTask' => $pck])
            ->set('kodePindai', strtolower($a->code))
            ->call('pindai')
            ->assertSet('labelInduk', (int) $a->id)
            ->assertSet('labelIsi', '12')
            ->call('simpanIsiLabel')
            ->assertSet('labelInduk', null)
            ->set('kodePindai', $isi[0]->code)
            ->call('pindai')
            ->assertHasNoErrors()
            ->assertSee('Label wajib: 13 dari 20');

        $this->assertSame([['id' => (int) $a->id, 'qty' => 12.0], ['id' => (int) $isi[0]->id, 'qty' => 1.0]], $baris->refresh()->labels);

        // Dicatat 20 tetapi label baru 13: PCK tidak bisa diselesaikan.
        $layar->call('catat', $baris->id)->call('selesaikan')->assertSet('ruleCode', 'BR-LBL-04');

        // Induk kedua sebagian (7 dari 12).
        $layar->set('kodePindai', $b->code)->call('pindai')
            ->assertSet('labelIsi', '7')
            ->call('simpanIsiLabel')
            ->call('selesaikan')
            ->assertSet('ruleError', '');

        $this->assertSame('completed', $pck->refresh()->status->value);
        $this->assertSame(PackageLabelStatus::Issued, $a->refresh()->status);
        $this->assertSame(PackageLabelStatus::InStock, $b->refresh()->status);
        $this->assertSame(5.0, (float) $b->qty_remaining);
        $this->assertSame(PackageLabelStatus::Issued, $isi[0]->refresh()->status);
        $this->assertSame('Keluar (dipetik)', $a->lastMove()->eventLabel());
    }

    #[Test]
    public function tc_pck_20_wajib_dibatasi_isi_label_dan_saldo_sehingga_stok_tanpa_label_tidak_menahan(): void
    {
        // Stok lama tanpa label 50 di bin A + 12 berlabel.
        app(StockLedger::class)->post(new MovementRequest(item: $this->baut, qtyBase: 50, toBinId: $this->binA->id));
        $grn = $this->grnBerlabel($this->baut, 12, 1, 12);
        $this->putAway($grn);
        $label = $this->labelGrn($grn)->first();

        // Keluar 30: wajib min(30, 12 berlabel, 62 saldo) = 12.
        $pck = $this->pckDimulai(30);
        foreach ($pck->lines as $l) {
            app(ProcessPickTask::class)->recordLine($l, (float) $l->qty_allocated, null, null, null, $this->makeUser());
        }
        $pesan = $this->gagal(fn () => app(ProcessPickTask::class)->complete($pck->refresh(), $this->makeUser()), 'BR-LBL-04');
        $this->assertStringContainsString('baru 0 dari 12', $pesan);

        // Label yang dibatalkan (mis. barangnya keluar tanpa dipindai) tidak lagi menahan.
        app(CancelLabel::class)->handle($label, $this->alasan(ReasonContext::Cancel), null, $this->makeUser('warehouse_head'));
        app(ProcessPickTask::class)->complete($pck->refresh(), $this->makeUser());
        $this->assertSame('completed', $pck->refresh()->status->value);

        // Item tanpa label sama sekali tidak terpengaruh (kabel).
        app(StockLedger::class)->post(new MovementRequest(item: $this->kabel, qtyBase: 10, toBinId: $this->binA->id));
        $this->assertSame('completed', $this->pckSelesai($this->reqDisetujui($this->makeProject(), $this->gudang, $this->kabel, 4))->status->value);
    }

    #[Test]
    public function tc_pck_21_label_salah_item_gudang_atau_sudah_diambil_ditolak(): void
    {
        $grn = $this->grnBerlabel($this->baut, 36, 3, 12);
        $this->putAway($grn);
        [$a] = $this->labelGrn($grn)->all();
        $kabel = $this->labelGrn($this->grnBerlabel($this->kabel, 5, 1, 5))->first();

        $pck = $this->pckDimulai(12);
        $baris = $pck->lines()->with('pickTask', 'item')->sole();

        // Label item lain.
        Livewire::actingAs($this->makeUser())->test(PickDetail::class, ['pickTask' => $pck])
            ->set('kodePindai', $kabel->code)->call('pindai')
            ->assertHasErrors('kodePindai');

        // Klaim melebihi alokasi.
        $this->gagal(fn () => app(ProcessPickTask::class)->claimLabel($baris, $a, 13), 'BR-LBL-03');

        // PCK lain mengambil label yang sama lebih dulu.
        $lain = $this->pckDimulai(12);
        $barisLain = $lain->lines()->with('pickTask', 'item')->sole();
        app(ProcessPickTask::class)->claimLabel($baris, $a, 12);
        app(ProcessPickTask::class)->claimLabel($barisLain, $a, 12);
        app(ProcessPickTask::class)->complete($pck->refresh(), $this->makeUser());

        $this->gagal(fn () => app(ProcessPickTask::class)->complete($lain->refresh(), $this->makeUser()), 'BR-LBL-03');
        $this->assertSame('in_progress', $lain->refresh()->status->value, 'Penolakan label membatalkan seluruh penyelesaian PCK.');

        // Jumlah diambil tidak boleh di bawah isi label yang dipindai.
        $ketiga = $this->pckDimulai(5);
        $b3 = $ketiga->lines()->with('pickTask', 'item')->sole();
        app(ProcessPickTask::class)->claimLabel($b3, $this->labelGrn($grn)[1], 5);
        $this->gagal(fn () => app(ProcessPickTask::class)->recordLine($b3->refresh(), 3, null, null, null, $this->makeUser()), 'BR-LBL-04');
    }

    #[Test]
    public function tc_pck_22_pck_batal_tidak_menyentuh_label(): void
    {
        $grn = $this->grnBerlabel($this->baut, 12, 1, 12);
        $this->putAway($grn);
        $label = $this->labelGrn($grn)->first();

        $pck = $this->pckDimulai(12);
        app(ProcessPickTask::class)->claimLabel($pck->lines()->with('pickTask', 'item')->sole(), $label, 12);
        app(ProcessPickTask::class)->cancel($pck->refresh(), $this->alasan(ReasonContext::Cancel), $this->makeUser('warehouse_head'));

        $this->assertSame(PackageLabelStatus::InStock, $label->refresh()->status);
        $this->assertSame(12.0, (float) $label->qty_remaining);
    }

    #[Test]
    public function tc_sj_21_dan_tc_trf_21_nomor_sj_tertempel_dan_label_utuh_dibuka_di_gudang_tujuan(): void
    {
        $bks = $this->buatGudang('BKS', 'Gudang Cabang Bekasi');
        $grn = $this->grnBerlabel($this->baut, 30, 3, 10);
        $this->putAway($grn);
        [$a, $b] = $this->labelGrn($grn)->all();

        $req = $this->reqDisetujui($this->makeProject(), $this->gudang, $this->baut, 15);
        $pck = app(CreatePickTask::class)->handle($req, $this->makeUser('warehouse_head'))[0];
        $pck = app(ProcessPickTask::class)->start($pck, $this->makeUser());
        $baris = $pck->lines()->with('pickTask', 'item')->sole();
        app(ProcessPickTask::class)->claimLabel($baris, $a, 10);
        app(ProcessPickTask::class)->claimLabel($baris->refresh(), $b, 5);
        $pck = app(ProcessPickTask::class)->complete($pck->refresh(), $this->makeUser());

        $sj = $this->sjBerangkat($pck, ['destination_type' => 'warehouse', 'destination_warehouse_id' => $bks->id]);
        $this->assertSame(2, PackageLabelMove::query()->where('shipment_id', $sj->id)->count(), 'TC-SJ-21: kejadian keluar membawa nomor SJ.');

        $sj = $this->buktiTerima($sj, 15);
        $tujuan = app(SaveGoodsReceipt::class)->handle(null, [
            'receipt_type' => 'transfer', 'warehouse_id' => $bks->id, 'shipment_id' => $sj->id,
        ], [], $this->makeUser());
        app(ReceiveGoodsReceipt::class)->handle($tujuan, $this->makeUser());

        // Label utuh (A) Di gudang BKS; label sebagian (B) tetap di CKG dengan sisa 5.
        $a->refresh();
        $this->assertSame(PackageLabelStatus::InStock, $a->status);
        $this->assertSame((int) $bks->id, (int) $a->warehouse_id);
        $this->assertSame((int) $this->binSistem($bks, BinType::Receiving)->id, (int) $a->bin_id);
        $this->assertSame('Diterima di gudang tujuan', $a->lastMove()->eventLabel());
        $this->assertSame((int) $this->gudang->id, (int) $b->refresh()->warehouse_id);
        $this->assertSame(5.0, (float) $b->qty_remaining);
    }
}

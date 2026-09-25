<?php

declare(strict_types=1);

namespace Tests\Feature\Return;

use App\Domain\Asset\Actions\InspectAsset;
use App\Domain\Asset\Models\AssetHandover;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\Serial;
use App\Domain\Receipt\Actions\SaveGoodsReceipt;
use App\Domain\Receipt\Enums\GoodsReceiptStatus;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Return\Enums\GoodsReturnStatus;
use App\Domain\Return\Enums\ReturnSorting;
use App\Domain\Return\Exceptions\ReturnRuleException;
use App\Domain\Shipment\Actions\CreateShipment;
use App\Domain\Shipment\Actions\ResolveDiscrepancy;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Warehouse\Actions\EnsureSystemBins;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Return\Concerns\ReturnFixtures;
use Tests\TenantTestCase;

/**
 * TC-RET-08 s.d. TC-RET-14 — rantai retur penuh: GRN retur ke bin Retur,
 * pemilahan layak/rusak/offcut/waste, kejadian `goods_returned` /
 * `asset_returned` (Katalog §2.8, BR-RET-03/04, BR-SJ-10, matriks §14,
 * A-111–A-113).
 */
class ReturnChainTest extends TenantTestCase
{
    use ReturnFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanTransfer();
    }

    private function binRetur(): Bin
    {
        return $this->binSistem($this->gudang, BinType::Return);
    }

    private function kejadianRetur(int $retId): Collection
    {
        return StockEvent::query()->where('source_type', 'goods_return')->where('source_id', $retId)->orderBy('id')->get();
    }

    #[Test]
    public function tc_ret_08_stok_site_layak_dan_rusak_tanpa_sj(): void
    {
        $this->stok($this->binKrw1, $this->baut, 20);
        $ret = $this->ret([['key' => $this->kunciSite($this->binKrw1, $this->baut), 'qty_base' => 10]]);

        $grn = $this->grnRetur($ret);
        $this->assertSame('return', $grn->receipt_type->value);
        $this->assertSame(10.0, $this->saldo($this->binKrw1, $this->baut), 'Keluar dari bin Gudang Site.');
        $this->assertSame(10.0, $this->saldo($this->binRetur(), $this->baut), 'Masuk bin Retur gudang tujuan.');
        $this->assertSame(0, StockEvent::query()->where('source_type', 'goods_receipt')->where('source_id', $grn->id)->count(), 'A-112: kejadian saat dipilah.');
        $this->assertSame(0, StockReservation::query()->active()->forDocument('goods_return', $ret->id)->count());

        $ret = $this->pilah($ret, [$ret->lines()->sole()->id => [
            ['sorting' => 'good', 'qty' => 7, 'target_bin_id' => $this->binB->id],
            ['sorting' => 'damaged', 'qty' => 3, 'reason_code_id' => $this->alasan(ReasonContext::Damage)],
        ]]);

        $this->assertSame(GoodsReturnStatus::Sorted, $ret->status);
        $this->assertSame(7.0, $this->saldo($this->binB, $this->baut));
        $this->assertSame(3.0, $this->saldo($this->binRetur(), $this->baut, StockStatus::Damaged), 'BR-RET-04: rusak berkondisi Rusak di bin Retur.');
        $this->assertSame(0.0, $this->saldo($this->binRetur(), $this->baut));
        $this->assertSame(GoodsReceiptStatus::Completed, $grn->refresh()->status, 'A-112: GRN retur selesai bersama pemilahan.');

        $baris = $ret->lines()->orderBy('id')->get();
        $this->assertCount(2, $baris, 'A-113: bagian kedua menjadi baris hasil pilah.');
        $this->assertSame(ReturnSorting::Good, $baris[0]->sorting);
        $this->assertSame(ReturnSorting::Damaged, $baris[1]->sorting);
        $this->assertSame((int) $baris[0]->id, (int) $baris[1]->split_from_line_id);

        $kejadian = $this->kejadianRetur($ret->id);
        $this->assertCount(2, $kejadian);
        $this->assertSame(['goods_returned', 'goods_returned'], $kejadian->map(fn ($k) => $k->event_type->value)->all());
        $this->assertSame(['good', 'damaged'], $kejadian->pluck('payload.sorting')->all());
        $this->assertSame(['company', 'company'], $kejadian->pluck('payload.ownership')->all());
        $this->assertSame((int) $this->proyek->id, (int) $kejadian->first()->project_id);
    }

    #[Test]
    public function tc_ret_09_sj_balik_dari_gudang_site(): void
    {
        $this->stok($this->binKrw1, $this->baut, 8);
        $ret = $this->ret([['key' => $this->kunciSite($this->binKrw1, $this->baut), 'qty_base' => 8]], ['self_delivered' => false]);

        $this->assertSame(GoodsReturnStatus::Approved, $ret->status);
        $pck = $this->jalankanPck($ret->livePickTask());

        // SJ balik wajib ke gudang tujuan RET.
        try {
            app(CreateShipment::class)->handle([$pck->id], [
                'destination_type' => 'warehouse', 'destination_warehouse_id' => $this->bks->id,
                'shipment_method' => 'self_delivered', 'carried_by_name' => 'Fajar',
            ], $this->makeUser());
            $this->fail('SJ balik ke gudang lain seharusnya ditolak.');
        } catch (ShipmentRuleException $e) {
            $this->assertSame('BR-SJ-09', $e->rule);
        }

        $sj = $this->sjDari($pck, $this->gudang);
        $ret->refresh();
        $this->assertSame(GoodsReturnStatus::InProgress, $ret->status, 'Katalog §2.8: SJ balik disusun → diproses.');
        $this->assertSame((int) $sj->id, (int) $ret->return_shipment_id);
        $this->assertSame(8.0, $this->saldo($this->binSistem($this->krw1, BinType::InTransit), $this->baut));

        // GRN retur menunggu bukti terima SJ balik (A-82).
        try {
            $this->grnRetur($ret);
            $this->fail('GRN retur sebelum bukti terima seharusnya ditolak.');
        } catch (ReceiptRuleException $e) {
            $this->assertSame('BR-SJ-04', $e->rule);
        }

        $sj = $this->terimaSj($sj);
        $this->assertSame(0, StockEvent::query()->where('event_type', 'stock_transferred')->where('source_id', $sj->id)->count(), 'SJ balik tanpa stock_transferred (A-112).');

        // SJ balik tidak bisa diterima sebagai GRN transfer.
        try {
            app(SaveGoodsReceipt::class)->handle(null, ['receipt_type' => 'transfer', 'warehouse_id' => $this->gudang->id, 'shipment_id' => $sj->id], [], $this->makeUser());
            $this->fail('SJ balik seharusnya ditolak sebagai transfer.');
        } catch (ReceiptRuleException $e) {
            $this->assertSame('BR-RET-01', $e->rule);
        }

        $grn = $this->grnRetur($ret);
        $this->assertSame((int) $sj->id, (int) $grn->shipment_id);
        $this->assertSame(0.0, $this->saldo($this->binSistem($this->krw1, BinType::InTransit), $this->baut));
        $this->assertSame(8.0, $this->saldo($this->binRetur(), $this->baut));

        $ret = $this->pilah($ret, [$ret->lines()->sole()->id => [['sorting' => 'good', 'qty' => 8, 'target_bin_id' => $this->binA->id]]]);
        $this->assertSame(GoodsReturnStatus::Sorted, $ret->status);
        $this->assertSame(108.0, $this->saldo($this->binA, $this->baut));
    }

    #[Test]
    public function tc_ret_10_barang_jual_putus_retur_penjualan(): void
    {
        $sj = $this->terimaSj($this->terkirimKeKlien($this->baut, 20));
        $kunci = 'sold:'.$sj->lines()->first()->id;
        $this->assertSame(20.0, $this->calon()[$kunci]['max']);

        $ret = $this->ret([['key' => $kunci, 'qty_base' => 5]]);
        $this->assertSame(15.0, $this->calon()[$kunci]['max']);

        try {
            $this->ret([['key' => $kunci, 'qty_base' => 16]]);
            $this->fail('Retur melebihi yang terkirim seharusnya ditolak.');
        } catch (ReturnRuleException $e) {
            $this->assertSame('BR-RET-03', $e->rule);
        }

        $this->grnRetur($ret);
        $this->assertSame(5.0, $this->saldo($this->binRetur(), $this->baut), 'Masuk lagi dari luar ke bin Retur.');

        $ret = $this->pilah($ret, [$ret->lines()->sole()->id => [['sorting' => 'good', 'qty' => 5, 'target_bin_id' => $this->binA->id]]]);

        $k = $this->kejadianRetur($ret->id)->sole();
        $this->assertSame('goods_returned', $k->event_type->value);
        $this->assertSame('sold', $k->payload['ownership'], 'BR-RET-03: retur penjualan untuk Akuntansi.');
        $this->assertSame((int) $sj->lines()->first()->id, (int) $k->payload['origin_shipment_line_id']);
        $this->assertSame(85.0, $this->saldo($this->binA, $this->baut));
    }

    #[Test]
    public function tc_ret_11_rusak_ditinggal_ekspedisi_lalu_waste(): void
    {
        $sj = $this->terkirimKeKlien($this->baut, 10, [], 'carrier');
        $baris = $sj->lines()->first();
        $sj = $this->terimaSj($sj, [$baris->id => 2]);

        $dsc = $sj->discrepancies()->sole();
        app(ResolveDiscrepancy::class)->handle($dsc, [[
            'line_id' => $dsc->lines()->sole()->id,
            'disposition' => 'claimed',
            'claim_ref' => 'KLAIM-01',
            'reason_code_id' => $this->alasan(ReasonContext::Discrepancy),
            'client_decision' => 'not_needed',
        ]], null, $this->makeUser('warehouse_head'));

        $kunci = 'claim:'.$dsc->lines()->sole()->id;
        $this->assertSame(2.0, $this->calon()[$kunci]['max'], 'BR-RET-05: barang rusak ditinggal ekspedisi.');

        $ret = $this->ret([['key' => $kunci, 'qty_base' => 2]]);
        $this->grnRetur($ret);
        $this->assertSame(2.0, $this->saldo($this->binRetur(), $this->baut, StockStatus::Damaged), 'Masuk bin Retur berkondisi Rusak.');

        try {
            $this->pilah($ret, [$ret->lines()->sole()->id => [['sorting' => 'waste', 'qty' => 2]]]);
            $this->fail('Waste tanpa alasan seharusnya ditolak.');
        } catch (ReturnRuleException $e) {
            $this->assertSame('BR-GEN-11', $e->rule);
        }

        $this->pilah($ret, [$ret->lines()->sole()->id => [['sorting' => 'waste', 'qty' => 2, 'reason_code_id' => $this->alasan(ReasonContext::Waste)]]]);
        $this->assertSame(2.0, $this->saldo($this->binSistem($this->gudang, BinType::Waste), $this->baut, StockStatus::Damaged));
        $this->assertSame(0.0, $this->saldo($this->binRetur(), $this->baut, StockStatus::Damaged));
        $this->assertSame('waste', $this->kejadianRetur($ret->id)->sole()->payload['sorting']);
    }

    #[Test]
    public function tc_ret_12_offcut_potongan_baru_bersilsilah(): void
    {
        $this->pipa->forceFill(['min_offcut_length' => 1])->save();
        $induk = Piece::create(['item_id' => $this->pipa->id, 'piece_no' => 'P-UJI-01', 'length' => 6]);
        $this->stok($this->binA, $this->pipa, 6, ['piece_id' => $induk->id]);

        $sj = $this->terimaSj($this->terkirimKeKlien($this->pipa, 6));
        $ret = $this->ret([['key' => 'sold:'.$sj->lines()->first()->id, 'qty_base' => 6]]);
        $this->grnRetur($ret);
        $baris = $ret->lines()->sole();

        foreach ([['sorting' => 'offcut', 'qty' => 6, 'target_bin_id' => $this->binB->id, 'offcut_length' => 0.5], ['sorting' => 'offcut', 'qty' => 6, 'target_bin_id' => $this->binB->id, 'offcut_length' => 7]] as $salah) {
            try {
                $this->pilah($ret, [$baris->id => [$salah]]);
                $this->fail('Offcut di luar batas seharusnya ditolak.');
            } catch (ReturnRuleException $e) {
                $this->assertContains($e->rule, ['BR-CNV-03', 'BR-STK-09']);
            }
        }

        $this->pilah($ret, [$baris->id => [['sorting' => 'offcut', 'qty' => 6, 'target_bin_id' => $this->binB->id, 'offcut_length' => 2.5]]]);

        $baris->refresh();
        $offcut = Piece::query()->findOrFail($baris->new_piece_id);
        $this->assertTrue($offcut->is_offcut);
        $this->assertSame((int) $induk->id, (int) $offcut->parent_piece_id, 'BR-CNV-04: silsilah.');
        $this->assertSame(2.5, (float) $offcut->length);
        $this->assertTrue($induk->refresh()->is_consumed);
        $this->assertSame(2.5, $this->saldo($this->binB, $this->pipa));
        $this->assertSame(3.5, $this->saldo($this->binSistem($this->gudang, BinType::Waste), $this->pipa, StockStatus::Damaged), 'Sisa potong tidak hilang dari kartu stok.');
        $this->assertSame(0.0, $this->saldo($this->binRetur(), $this->pipa));
        $this->assertSame(['offcut', 'waste'], $this->kejadianRetur($ret->id)->pluck('payload.sorting')->all());
    }

    #[Test]
    public function tc_ret_13_aset_on_site_kembali_asset_returned(): void
    {
        app(EnsureSystemBins::class)->onSiteBin($this->krw1, $this->proyek);
        $serial = Serial::create(['item_id' => $this->genset->id, 'serial_no' => 'GNS-UJI-1']);
        $this->stok($this->binB, $this->genset, 1, ['serial_id' => $serial->id]);

        $sj = $this->terimaSj($this->terkirimKeKlien($this->genset, 1, ['line_ownership' => 'loan']));
        $onSite = Bin::query()->withoutGlobalScopes()->where('bin_type', BinType::OnSite->value)->where('project_id', $this->proyek->id)->sole();
        $this->assertSame(1.0, $this->saldo($onSite, $this->genset), 'Aset di bin On-site proyek.');

        $kunci = implode(':', ['asset', $onSite->id, $this->genset->id, $serial->id]);
        $ret = $this->ret([['key' => $kunci, 'qty_base' => 1]]);
        $this->grnRetur($ret);
        $this->assertSame(0.0, $this->saldo($onSite, $this->genset));

        // Sejak modul Aset (25-aset): aset diperiksa dulu sebelum dipilah (BR-AST-03).
        $baris = [$ret->lines()->sole()->id => [['sorting' => 'good', 'qty' => 1, 'target_bin_id' => $this->binB->id]]];
        try {
            $this->pilah($ret, $baris);
            $this->fail('Aset belum diperiksa seharusnya ditolak.');
        } catch (ReturnRuleException $e) {
            $this->assertSame('BR-AST-03', $e->rule);
        }

        Storage::fake('local');
        $ast = AssetHandover::query()->where('serial_id', $serial->id)->sole();
        app(InspectAsset::class)->handle($ast, ['condition_grade' => 'A', 'condition_score' => 90, 'component_notes' => 'Mesin: normal'],
            UploadedFile::fake()->image('periksa.jpg'), $this->makeUser('warehouse_staff'));

        $this->pilah($ret, $baris);
        $this->assertSame(1.0, $this->saldo($this->binB, $this->genset));

        $k = $this->kejadianRetur($ret->id)->sole();
        $this->assertSame('asset_returned', $k->event_type->value, 'Matriks §14: aset kembali.');
        $this->assertSame('A', $k->payload['inspection']['condition_grade'], 'Hasil pemeriksaan ikut kejadian (menggantikan inspection = null, A-116).');
    }

    #[Test]
    public function tc_ret_14_guard_pemilahan(): void
    {
        $this->stok($this->binKrw1, $this->baut, 10);
        $ret = $this->ret([['key' => $this->kunciSite($this->binKrw1, $this->baut), 'qty_base' => 10]]);
        $id = $ret->lines()->sole()->id;

        $cek = function (array $bagian, string $aturan) use ($ret, $id): void {
            try {
                $this->pilah($ret, [$id => $bagian]);
                $this->fail('Seharusnya ditolak '.$aturan.'.');
            } catch (ReturnRuleException $e) {
                $this->assertSame($aturan, $e->rule, $e->getMessage());
            }
        };

        $cek([['sorting' => 'good', 'qty' => 10, 'target_bin_id' => $this->binA->id]], 'BR-RET-04'); // belum diterima

        $this->grnRetur($ret);

        $cek([['sorting' => 'good', 'qty' => 9, 'target_bin_id' => $this->binA->id]], 'BR-RET-04');
        $cek([['sorting' => 'damaged', 'qty' => 10]], 'BR-GEN-11');
        $cek([['sorting' => 'good', 'qty' => 10, 'target_bin_id' => $this->binSistem($this->gudang, BinType::Quarantine)->id]], 'BR-RET-04');
        $cek([['sorting' => 'good', 'qty' => 10, 'target_bin_id' => $this->binBks->id]], 'BR-RET-04');
        $cek([['sorting' => 'offcut', 'qty' => 10, 'target_bin_id' => $this->binA->id, 'offcut_length' => 1]], 'BR-RET-04');
        $cek([], 'BR-RET-04');

        $this->assertSame(GoodsReturnStatus::Received, $ret->refresh()->status);
        $this->assertSame(10.0, $this->saldo($this->binRetur(), $this->baut));
    }
}

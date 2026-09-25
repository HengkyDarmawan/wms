<?php

declare(strict_types=1);

namespace Tests\Feature\Conversion;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Conversion\Actions\CreateConversion;
use App\Domain\Conversion\Enums\ConversionOutputKind;
use App\Domain\Conversion\Enums\ConversionStatus;
use App\Domain\Conversion\Models\ConversionOutput;
use App\Domain\Conversion\Policies\ConversionPolicy;
use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\Uom;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Enums\BinType;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Conversion\Concerns\ConversionFixtures;
use Tests\TenantTestCase;

/**
 * TC-CNV-01 s.d. TC-CNV-06 — konversi material tanpa aturan approval: potong
 * pipa dengan silsilah, neraca ukuran, offcut di bawah minimum menjadi waste,
 * guard proyek/gudang/input, output berlot, draf tidak memegang stok
 * (Katalog §2.10, BR-CNV-01–04, A-153–A-156).
 */
class ConversionTest extends TenantTestCase
{
    use ConversionFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanKonversi();
    }

    #[Test]
    public function tc_cnv_01_potong_pipa_selesai_dengan_silsilah_dan_kejadian(): void
    {
        $staf = $this->staf();
        $cnv = $this->cnvPotong($staf);

        $this->assertSame(ConversionStatus::Draft, $cnv->status);
        $this->assertMatchesRegularExpression('#^CNV/CKG/#', $cnv->number);
        $this->assertSame((int) $staf->id, (int) $cnv->prepared_by);
        $this->assertSame(4, $cnv->outputs()->count(), 'count = 2 menggandakan baris output per potongan.');
        $this->assertSame(6.0, (float) $cnv->total_input);
        $this->assertSame(5.0, (float) $cnv->total_output);
        $this->assertSame(0.99, (float) $cnv->total_offcut);
        $this->assertSame(0.01, (float) $cnv->total_kerf);
        $this->assertSame(6.0, $this->saldo($this->binA, $this->pipa), 'Draf belum memegang stok (A-154).');
        $this->assertTrue(app(ConversionPolicy::class)->complete($staf, $cnv), 'Tanpa aturan: selesaikan langsung.');
        $this->assertFalse(app(ConversionPolicy::class)->submit($staf, $cnv));

        $cnv = $this->selesai($cnv, $staf);

        $this->assertSame(ConversionStatus::Completed, $cnv->status);
        $this->assertSame((int) $staf->id, (int) $cnv->completed_by);
        $this->assertNotNull($cnv->completed_at);
        $this->assertSame(5.99, $this->saldo($this->binA, $this->pipa), 'Batang 6 m keluar; 2 × 2,5 m + offcut 0,99 m masuk; kerf hilang.');
        $this->assertTrue($this->batang->refresh()->is_consumed);

        $hasil = $cnv->outputs()->with('newPiece')->orderBy('id')->get();
        $offcut = $hasil->firstWhere('output_kind', ConversionOutputKind::Offcut);
        $this->assertTrue($offcut->newPiece->is_offcut);
        $this->assertSame((int) $this->batang->id, (int) $offcut->newPiece->parent_piece_id, 'BR-CNV-04: silsilah ke potongan induk.');
        $this->assertSame('conversion', $offcut->newPiece->origin_type);
        $this->assertSame((int) $cnv->id, (int) $offcut->newPiece->origin_id);
        $this->assertSame(2, $hasil->where('output_kind', ConversionOutputKind::Output)->filter(fn (ConversionOutput $o) => $o->newPiece?->parent_piece_id === $this->batang->id)->count());
        $this->assertNull($hasil->firstWhere('output_kind', ConversionOutputKind::Kerf)->movement_id, 'Kerf tanpa pergerakan.');
        $this->assertSame(3, $this->batang->offcuts()->count(), 'Silsilah dua arah: induk → anak.');

        $kejadian = StockEvent::query()->where('source_type', 'conversion')->where('source_id', $cnv->id)->get();
        $this->assertCount(4, $kejadian, 'Satu kejadian per pergerakan: 1 input + 3 hasil.');
        $this->assertTrue($kejadian->every(fn (StockEvent $e) => $e->event_type === StockEventType::MaterialConverted));
        $this->assertSame(['input', 'output', 'output', 'offcut'], $kejadian->sortBy('id')->pluck('payload.role')->all());
        $this->assertSame(0.01, (float) $kejadian->first()->payload['kerf_total']);
        $this->assertSame((int) $this->proyek->id, (int) $kejadian->first()->project_id);

        $this->gagalCnv(fn () => $this->selesai($cnv), 'BR-GEN-01');
    }

    #[Test]
    public function tc_cnv_02_neraca_ukuran_wajib_seimbang(): void
    {
        $input = [['key' => $this->kunciBatang(), 'qty_base' => 6]];

        $this->gagalCnv(fn () => $this->cnv($input, [['kind' => 'output', 'item_id' => $this->pipa->id, 'qty_base' => 2.5, 'count' => 2]]), 'BR-CNV-02');
        $this->gagalCnv(fn () => $this->cnv($input, [['kind' => 'waste', 'qty_base' => 6]]), 'BR-CNV-02');
        $this->gagalCnv(fn () => $this->cnv($input, [['kind' => 'output', 'qty_base' => 6]]), 'BR-CNV-02');
        $this->gagalCnv(fn () => $this->cnv($input, [['kind' => 'output', 'item_id' => $this->pipa->id, 'qty_base' => 0]]), 'BR-LED-02');

        // Satu satuan dasar untuk potong: baut (PCS) tidak bisa menjadi hasil potong pipa (M).
        $this->gagalCnv(fn () => $this->cnv($input, [
            ['kind' => 'output', 'item_id' => $this->pipa->id, 'qty_base' => 5],
            ['kind' => 'output', 'item_id' => $this->baut->id, 'qty_base' => 1],
        ]), 'BR-CNV-02');

        // Toleransi pembulatan satuan: selisih 0,00004 masih seimbang.
        $cnv = $this->cnv($input, [
            ['kind' => 'output', 'item_id' => $this->pipa->id, 'qty_base' => 5.99996],
        ]);
        $this->assertSame(ConversionStatus::Draft, $cnv->status);
    }

    #[Test]
    public function tc_cnv_03_offcut_di_bawah_minimum_otomatis_waste(): void
    {
        $cnv = $this->selesai($this->cnv(
            [['key' => $this->kunciBatang(), 'qty_base' => 6]],
            [
                ['kind' => 'output', 'item_id' => $this->pipa->id, 'qty_base' => 5.695],
                ['kind' => 'offcut', 'qty_base' => 0.3],
                ['kind' => 'kerf', 'qty_base' => 0.005],
            ],
        ));

        $sisa = $cnv->outputs()->where('auto_waste', true)->sole();
        $this->assertSame(ConversionOutputKind::Waste, $sisa->output_kind, 'BR-CNV-03: 0,3 < 0,5 m → waste.');
        $this->assertSame(StockStatus::Damaged, $sisa->stock_status);
        $binWaste = $this->binSistem($this->gudang, BinType::Waste);
        $this->assertSame((int) $binWaste->id, (int) $sisa->bin_id);
        $this->assertSame(0.3, $this->saldo($binWaste, $this->pipa, StockStatus::Damaged));
        $this->assertFalse(Piece::query()->findOrFail($sisa->new_piece_id)->is_offcut);
        $this->assertSame(0.3, (float) $cnv->total_waste);
        $this->assertSame(0.0, (float) $cnv->total_offcut);

        // Offcut hanya untuk item per potong.
        $this->baut->forceFill(['is_cuttable' => true])->save();
        $this->gagalCnv(fn () => $this->cnv(
            [['key' => $this->kunciCnv($this->binA, $this->baut), 'qty_base' => 10]],
            [['kind' => 'output', 'item_id' => $this->baut->id, 'qty_base' => 8], ['kind' => 'offcut', 'qty_base' => 2]],
            ['conversion_type' => 'repack'],
        ), 'BR-CNV-03');
    }

    #[Test]
    public function tc_cnv_04_guard_proyek_gudang_dan_input(): void
    {
        $input = [['key' => $this->kunciBatang(), 'qty_base' => 6]];
        $output = [['kind' => 'output', 'item_id' => $this->pipa->id, 'qty_base' => 6]];

        $this->gagalCnv(fn () => $this->cnv($input, $output, ['project_id' => null]), 'BR-CNV-01');
        $this->gagalCnv(fn () => $this->cnv($input, $output, ['conversion_type' => 'lebur']), 'BR-GEN-01');

        $lain = $this->makeProject();
        $this->gagalCnv(fn () => $this->cnv($input, $output, ['project_id' => $lain->id, 'warehouse_id' => $this->krw1->id]), 'BR-CNV-01');

        $tutup = $this->makeProject(['status' => 'closed']);
        $this->gagalCnv(fn () => $this->cnv($input, $output, ['project_id' => $tutup->id]), 'BR-PRJ-01');

        $stafBks = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->bks->id);
        $this->gagalCnv(fn () => $this->cnv($input, $output, [], $stafBks), 'BR-ACC-05');

        // Potongan dipakai utuh; jumlah melebihi stok; item tidak bisa dipotong.
        $this->gagalCnv(fn () => $this->cnv([['key' => $this->kunciBatang(), 'qty_base' => 3]], [['kind' => 'output', 'item_id' => $this->pipa->id, 'qty_base' => 3]]), 'BR-STK-09');
        $this->baut->forceFill(['is_cuttable' => true])->save();
        $this->gagalCnv(fn () => $this->cnv([['key' => $this->kunciCnv($this->binA, $this->baut), 'qty_base' => 101]], [['kind' => 'output', 'item_id' => $this->baut->id, 'qty_base' => 101]], ['conversion_type' => 'repack']), 'BR-STK-06');
        $this->baut->forceFill(['is_cuttable' => false])->save();
        $this->gagalCnv(fn () => $this->cnv([['key' => $this->kunciCnv($this->binA, $this->baut), 'qty_base' => 5]], [['kind' => 'output', 'item_id' => $this->baut->id, 'qty_base' => 5]], ['conversion_type' => 'repack']), 'BR-CNV-03');

        // Aset tidak pernah dikonversi, baik sebagai input maupun output.
        $this->gagalCnv(fn () => $this->cnv([['key' => $this->kunciCnv($this->binA, $this->genset), 'qty_base' => 1]], $output), 'BR-STK-08');
        $this->gagalCnv(fn () => $this->cnv($input, [['kind' => 'output', 'item_id' => $this->genset->id, 'qty_base' => 6]], ['conversion_type' => 'assemble']), 'BR-STK-08');

        // Bin tujuan harus bin penyimpanan gudang ini.
        $this->gagalCnv(fn () => $this->cnv($input, [['kind' => 'output', 'item_id' => $this->pipa->id, 'qty_base' => 6, 'bin_id' => $this->binBks->id]]), 'BR-STK-02');

        // Gudang Site milik proyek boleh untuk proyeknya sendiri.
        $this->potongan($this->binKrw1, 4.0);
        $site = $this->cnv(
            [['key' => $this->kunciCnv($this->binKrw1, $this->pipa, ['piece_id' => Piece::query()->latest('id')->value('id')]), 'qty_base' => 4]],
            [['kind' => 'output', 'item_id' => $this->pipa->id, 'qty_base' => 4]],
            ['warehouse_id' => $this->krw1->id],
        );
        $this->assertMatchesRegularExpression('#^CNV/KRW1/#', $site->number);
    }

    #[Test]
    public function tc_cnv_05_rakit_dengan_output_berlot_dan_ganti_kemasan_mewarisi_lot(): void
    {
        $pcs = Uom::query()->where('code', 'PCS')->value('id');
        $bracket = $this->buatItem('BRACKET-A', TrackingMode::Lot, $pcs);
        $this->baut->forceFill(['is_cuttable' => true])->save();

        $input = [['key' => $this->kunciCnv($this->binA, $this->baut), 'qty_base' => 8]];
        $output = [['kind' => 'output', 'item_id' => $bracket->id, 'qty_base' => 2]];
        $this->gagalCnv(fn () => $this->cnv($input, $output, ['conversion_type' => 'assemble']), 'BR-LED-03');

        $output[0]['lot_no'] = 'BR-2609';
        $cnv = $this->selesai($this->cnv($input, $output, ['conversion_type' => 'assemble']));

        $this->assertSame(92.0, $this->saldo($this->binA, $this->baut), 'Rakit tanpa neraca ukuran (A-156).');
        $lot = Lot::query()->where('item_id', $bracket->id)->where('lot_no', 'BR-2609')->sole();
        $this->assertSame((int) $lot->id, (int) $cnv->outputs()->sole()->lot_id);
        $this->assertSame(2.0, $this->saldo($this->binA, $bracket));

        // Ganti kemasan item berlot yang sama: lot input diwarisi.
        $this->semen->forceFill(['is_cuttable' => true])->save();
        $lotSemen = Lot::create(['item_id' => $this->semen->id, 'lot_no' => 'SM-1', 'received_at' => now()->toDateString()]);
        $this->stok($this->binA, $this->semen, 10, ['lot_id' => $lotSemen->id]);
        $kemas = $this->selesai($this->cnv(
            [['key' => $this->kunciCnv($this->binA, $this->semen, ['lot_id' => $lotSemen->id]), 'qty_base' => 10]],
            [['kind' => 'output', 'item_id' => $this->semen->id, 'qty_base' => 9, 'bin_id' => $this->binB->id], ['kind' => 'waste', 'qty_base' => 1, 'reason_code_id' => $this->alasan(ReasonContext::Waste)]],
            ['conversion_type' => 'repack'],
        ));

        $this->assertSame((int) $lotSemen->id, (int) $kemas->outputs()->where('output_kind', 'output')->sole()->lot_id);
        $this->assertSame(9.0, $this->saldo($this->binB, $this->semen), 'Output ke bin pilihan.');
        $this->assertSame(1.0, $this->saldo($this->binSistem($this->gudang, BinType::Waste), $this->semen, StockStatus::Damaged));
        $this->assertNotNull($kemas->outputs()->where('output_kind', 'waste')->sole()->reason_code_id);
        $this->gagalCnv(fn () => $this->cnv(
            [['key' => $this->kunciCnv($this->binB, $this->semen, ['lot_id' => $lotSemen->id]), 'qty_base' => 1]],
            [['kind' => 'output', 'item_id' => $this->semen->id, 'qty_base' => 0.5], ['kind' => 'waste', 'qty_base' => 0.5, 'reason_code_id' => $this->alasan(ReasonContext::Cancel)]],
            ['conversion_type' => 'repack'],
        ), 'BR-GEN-02');

        // Item berserial tidak menjadi hasil konversi.
        $serial = $this->buatItem('METER-X', TrackingMode::Serial, $pcs, ['ownership_model' => OwnershipModel::Consumable]);
        $this->gagalCnv(fn () => $this->cnv([['key' => $this->kunciCnv($this->binA, $this->baut), 'qty_base' => 1]], [['kind' => 'output', 'item_id' => $serial->id, 'qty_base' => 1]], ['conversion_type' => 'assemble']), 'BR-LED-03');
    }

    #[Test]
    public function tc_cnv_06_draf_tidak_memegang_stok_dan_diubah_pembuatnya(): void
    {
        $pembuat = $this->staf();
        $cnv = $this->cnvPotong($pembuat);

        $lain = $this->makeUser('warehouse_head');
        $pemohon = $this->makeUser('internal_requester');
        $this->assertTrue(app(ConversionPolicy::class)->update($pembuat, $cnv));
        $this->assertTrue(app(ConversionPolicy::class)->update($lain, $cnv), 'Pemegang conversion.complete boleh mengubah (A-158).');
        $this->assertFalse(app(ConversionPolicy::class)->update($pemohon, $cnv));
        $this->assertFalse(app(ConversionPolicy::class)->create($pemohon), 'Pemohon internal tidak memegang izin konversi.');

        $cnv = app(CreateConversion::class)->update($cnv, ['conversion_type' => 'cut', 'notes' => 'Revisi'], [['key' => $this->kunciBatang(), 'qty_base' => 6]], [
            ['kind' => 'output', 'item_id' => $this->pipa->id, 'qty_base' => 3, 'count' => 2],
        ], $pembuat);
        $this->assertSame(2, $cnv->outputs()->count());
        $this->assertSame('Revisi', $cnv->notes);

        // Batang keluar lewat dokumen lain sebelum CNV selesai: input diperiksa ulang.
        app(StockLedger::class)->post(new MovementRequest(
            item: $this->pipa, qtyBase: 6, fromBinId: $this->binA->id, pieceId: $this->batang->id,
        ));

        $this->gagalCnv(fn () => $this->selesai($cnv), 'BR-CNV-01');
        $this->assertSame(ConversionStatus::Draft, $cnv->refresh()->status);
    }
}

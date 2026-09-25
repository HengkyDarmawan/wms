<?php

declare(strict_types=1);

namespace Tests\Feature\Conversion;

use App\Domain\Conversion\Actions\CreateConversion;
use App\Domain\Conversion\Enums\ConversionOutputKind;
use App\Domain\Conversion\Enums\ConversionStatus;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Conversion\Policies\ConversionPolicy;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\Piece;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Enums\BinType;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Conversion\Concerns\ConversionFixtures;
use Tests\TenantTestCase;

/**
 * TC-CNV-09 dan TC-CNV-10 — CNV pembalik (A-157): salinan baris, Alasan `*`,
 * satu pembalik aktif, hasil keluar lalu input kembali dengan
 * `reverses_event_id`; ditolak bila hasil sudah dipakai (BR-CNV-05,
 * BR-GEN-03/04, BR-LED-05).
 */
class ConversionReversalTest extends TenantTestCase
{
    use ConversionFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanKonversi();
    }

    private function potongDenganWaste(): Conversion
    {
        return $this->selesai($this->cnv(
            [['key' => $this->kunciBatang(), 'qty_base' => 6]],
            [
                ['kind' => 'output', 'item_id' => $this->pipa->id, 'qty_base' => 2.5, 'count' => 2],
                ['kind' => 'offcut', 'qty_base' => 0.69],
                ['kind' => 'offcut', 'qty_base' => 0.3],
                ['kind' => 'kerf', 'qty_base' => 0.01],
            ],
        ));
    }

    #[Test]
    public function tc_cnv_09_pembalik_mengembalikan_input_dan_mengeluarkan_hasil(): void
    {
        $staf = $this->staf();
        $draf = $this->cnvPotongBaru();
        $asal = $this->potongDenganWaste();
        $binWaste = $this->binSistem($this->gudang, BinType::Waste);
        $buat = app(CreateConversion::class);

        $this->assertSame(5.69, $this->saldo($this->binA, $this->pipa));
        $this->assertSame(0.3, $this->saldo($binWaste, $this->pipa, StockStatus::Damaged));

        $this->gagalCnv(fn () => $buat->reverse($asal, null, null, $staf), 'BR-GEN-11');
        $this->gagalCnv(fn () => $buat->reverse($draf, $this->alasan(ReasonContext::Cancel), null, $staf), 'BR-GEN-03');

        $balik = $buat->reverse($asal, $this->alasan(ReasonContext::Cancel), 'Salah ukuran', $staf);
        $this->assertSame(ConversionStatus::Draft, $balik->status);
        $this->assertSame((int) $asal->id, (int) $balik->reversal_of_id);
        $this->assertSame($asal->inputs()->count(), $balik->inputs()->count());
        $this->assertSame($asal->outputs()->count(), $balik->outputs()->count());
        $this->assertFalse(app(ConversionPolicy::class)->update($staf, $balik), 'Pembalik tidak diubah lewat form.');
        $this->assertFalse(app(ConversionPolicy::class)->reverse($staf, $asal->refresh()), 'Satu pembalik aktif per CNV.');
        $this->gagalCnv(fn () => $buat->reverse($asal, $this->alasan(ReasonContext::Cancel), null, $staf), 'BR-LED-05');

        $balik = $this->selesai($balik, $staf);

        $this->assertSame(ConversionStatus::Completed, $balik->status);
        $this->assertSame(6.0, $this->saldo($this->binA, $this->pipa), 'Batang 6 m kembali; hasil keluar.');
        $this->assertSame(0.0, $this->saldo($binWaste, $this->pipa, StockStatus::Damaged));
        $this->assertFalse($this->batang->refresh()->is_consumed, 'Potongan input tersedia lagi.');
        $this->assertTrue(Piece::query()->where('origin_type', 'conversion')->where('origin_id', $asal->id)->get()->every(fn (Piece $p) => $p->is_consumed));
        $this->assertTrue($asal->refresh()->isReversed());
        $this->gagalCnv(fn () => $buat->reverse($balik, $this->alasan(ReasonContext::Cancel), null, $staf), 'BR-LED-05');

        $kejadianBalik = StockEvent::query()->where('source_type', 'conversion')->where('source_id', $balik->id)->orderBy('id')->get();
        $this->assertCount(5, $kejadianBalik, '4 hasil bergerak + 1 input; kerf tidak.');
        $this->assertTrue($kejadianBalik->every(fn (StockEvent $e) => $e->event_type === StockEventType::MaterialConverted && $e->reverses_event_id !== null));
        $this->assertSame('input', $kejadianBalik->last()->payload['role'], 'Hasil dibalik dulu, input terakhir.');

        $gerak = StockMovement::query()->findOrFail($balik->inputs()->sole()->movement_id);
        $this->assertSame((int) $asal->inputs()->sole()->movement_id, (int) $gerak->reverses_movement_id);

        // Batang yang kembali bisa dikonversi lagi.
        $lagi = $this->selesai($this->cnvPotongBaru());
        $this->assertSame(ConversionStatus::Completed, $lagi->status);
    }

    #[Test]
    public function tc_cnv_10_pembalik_ditolak_bila_hasil_sudah_dipakai(): void
    {
        $staf = $this->staf();
        $asal = $this->potongDenganWaste();
        $offcut = $asal->outputs()->where('output_kind', ConversionOutputKind::Offcut->value)->sole();

        // Offcut dipakai dokumen lain (keluar dari bin).
        app(StockLedger::class)->post(new MovementRequest(
            item: $this->pipa, qtyBase: 0.69, fromBinId: $offcut->bin_id, pieceId: $offcut->new_piece_id,
            documentType: 'material_issue', documentId: 1,
        ));

        $this->gagalCnv(fn () => app(CreateConversion::class)->reverse($asal, $this->alasan(ReasonContext::Cancel), null, $staf), 'BR-CNV-05');
        $this->assertTrue(app(ConversionPolicy::class)->reverse($staf, $asal), 'Tombol tampil; guard ditegakkan aksi.');

        // Output dipindah bin juga dianggap dipakai: pembalik yang sudah dibuat gagal saat diselesaikan.
        $asal2 = $this->selesai($this->cnv(
            [['key' => $this->kunciBatang($this->potongan($this->binA, 3.0)), 'qty_base' => 3]],
            [['kind' => 'output', 'item_id' => $this->pipa->id, 'qty_base' => 3]],
        ));
        $balik = app(CreateConversion::class)->reverse($asal2, $this->alasan(ReasonContext::Cancel), null, $staf);
        $out = $asal2->outputs()->sole();
        app(StockLedger::class)->post(new MovementRequest(
            item: $this->pipa, qtyBase: 3, fromBinId: $out->bin_id, toBinId: $this->binB->id, pieceId: $out->new_piece_id,
        ));

        $this->gagalCnv(fn () => $this->selesai($balik, $staf), 'BR-CNV-05');
        $this->assertSame(ConversionStatus::Draft, $balik->refresh()->status);
        $this->assertSame(3.0, $this->saldo($this->binB, $this->pipa));
    }

    private function cnvPotongBaru(): Conversion
    {
        return $this->cnv(
            [['key' => $this->kunciBatang(), 'qty_base' => 6]],
            [['kind' => 'output', 'item_id' => $this->pipa->id, 'qty_base' => 6]],
        );
    }
}

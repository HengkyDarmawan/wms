<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Support;

use App\Domain\Access\Models\User;
use App\Domain\Conversion\Enums\ConversionOutputKind;
use App\Domain\Conversion\Exceptions\ConversionRuleException;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Conversion\Models\ConversionInput;
use App\Domain\Conversion\Models\ConversionOutput;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;

/**
 * Menulis CNV ke kartu stok lewat `StockLedger` (P-01, BR-STK-01, matriks §14).
 *
 * CNV biasa: setiap input keluar dari bin penyimpanannya (potongan input
 * `is_consumed`), lalu setiap output/offcut/waste masuk — item per potong
 * mendapat potongan baru bersilsilah (`parent_piece_id`, `origin_type =
 * conversion`, BR-CNV-04), output berlot memakai lot warisan atau lot baru.
 * Kerf tidak bergerak. Semua pergerakan menerbitkan `material_converted`.
 *
 * CNV pembalik (A-157): hasil dibalik dulu (keluar dari bin tujuannya), baru
 * input dikembalikan; setiap kejadian menunjuk kejadian asal
 * (`reverses_event_id`, BR-GEN-03), sekali per pergerakan (BR-LED-05).
 */
class ConversionPoster
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function post(Conversion $cnv, ?User $actor): void
    {
        $cnv->loadMissing('project', 'warehouse');
        $kerf = round((float) $cnv->outputs()->where('output_kind', ConversionOutputKind::Kerf->value)->sum('qty_base'), 4);

        foreach ($cnv->inputs()->with('item.baseUom', 'piece')->orderBy('id')->get() as $input) {
            $movement = $this->kirim($cnv, $input, new MovementRequest(
                item: $input->item,
                qtyBase: round((float) $input->qty_base, 4),
                fromBinId: (int) $input->bin_id,
                stockStatus: StockStatus::Available,
                lotId: $input->lot_id,
                pieceId: $input->piece_id,
                projectId: (int) $cnv->project_id,
                documentType: 'conversion',
                documentId: (int) $cnv->id,
                documentLineId: (int) $input->id,
                documentNumber: (string) $cnv->number,
                performedBy: $actor,
                eventType: StockEventType::MaterialConverted,
                eventPayload: $this->payload($cnv, 'input', $kerf),
                notes: 'Input konversi',
            ));

            $input->piece?->forceFill(['is_consumed' => true])->save();
            $input->forceFill(['movement_id' => $movement->id])->save();
        }

        foreach ($cnv->outputs()->with('item.baseUom', 'parentInput')->orderBy('id')->get() as $output) {
            if (! $output->output_kind->movesStock()) {
                continue;
            }

            $induk = $output->parentInput;
            $lotId = $output->lot_id ?? $this->lotBaru($output);
            $pieceId = $output->item->tracksPiece() ? $this->potonganBaru($cnv, $output, $induk) : null;

            $movement = $this->kirim($cnv, $output, new MovementRequest(
                item: $output->item,
                qtyBase: round((float) $output->qty_base, 4),
                toBinId: (int) $output->bin_id,
                stockStatus: $output->stock_status ?? $output->output_kind->stockStatus() ?? StockStatus::Available,
                lotId: $lotId,
                pieceId: $pieceId,
                projectId: (int) $cnv->project_id,
                documentType: 'conversion',
                documentId: (int) $cnv->id,
                documentLineId: (int) $output->id,
                documentNumber: (string) $cnv->number,
                reasonCodeId: $output->reason_code_id !== null ? (int) $output->reason_code_id : null,
                performedBy: $actor,
                eventType: StockEventType::MaterialConverted,
                eventPayload: $this->payload($cnv, $output->output_kind->value, $kerf) + array_filter([
                    'parent_input_id' => $induk?->id,
                    'parent_piece_id' => $induk?->piece_id,
                    'parent_lot_id' => $induk?->lot_id,
                    'auto_waste' => $output->auto_waste ?: null,
                ], fn ($v) => $v !== null),
                notes: $output->output_kind->label().' konversi',
            ));

            $output->forceFill(['movement_id' => $movement->id, 'lot_id' => $lotId, 'new_piece_id' => $pieceId])->save();
        }
    }

    public function reverse(Conversion $reversal): void
    {
        $reversal->loadMissing('reversalOf');
        $asal = $reversal->reversalOf;

        foreach ($reversal->outputs()->orderBy('id')->get() as $baris) {
            $lama = $baris->reversal_of_line_id !== null ? ConversionOutput::query()->find($baris->reversal_of_line_id) : null;

            if ($lama === null || ! $lama->output_kind->movesStock()) {
                continue;
            }

            $gerak = $this->balik($reversal, $lama->movement_id, (int) $baris->id, $lama->output_kind->value);

            if ($lama->new_piece_id !== null) {
                Piece::query()->whereKey($lama->new_piece_id)->update(['is_consumed' => true]);
            }

            $baris->forceFill(['movement_id' => $gerak->id])->save();
        }

        foreach ($reversal->inputs()->orderBy('id')->get() as $baris) {
            $lama = $baris->reversal_of_line_id !== null ? ConversionInput::query()->find($baris->reversal_of_line_id) : null;

            if ($lama === null) {
                throw ConversionRuleException::rule('BR-LED-05', 'Baris input asal CNV pembalik tidak ditemukan.');
            }

            $gerak = $this->balik($reversal, $lama->movement_id, (int) $baris->id, 'input');

            if ($lama->piece_id !== null) {
                Piece::query()->whereKey($lama->piece_id)->update(['is_consumed' => false]);
            }

            $baris->forceFill(['movement_id' => $gerak->id])->save();
        }

        activity('conversion')->performedOn($reversal)
            ->withProperties(['pembalik_dari' => $asal?->number])
            ->log('Hasil dan input '.$asal?->number.' dibalik di kartu stok');
    }

    private function balik(Conversion $reversal, ?int $movementId, int $lineId, string $peran): StockMovement
    {
        $gerakAsal = $movementId !== null ? StockMovement::query()->find($movementId) : null;

        if ($gerakAsal === null) {
            throw ConversionRuleException::rule('BR-LED-05', 'Baris asal CNV pembalik belum pernah diposting.');
        }

        $kejadianAsal = StockEvent::query()->where('payload->movement_id', $gerakAsal->id)->value('event_id');

        try {
            return $this->ledger->reverse(
                $gerakAsal,
                $reversal->reason_code_id !== null ? (int) $reversal->reason_code_id : null,
                'Pembalik '.$reversal->reversalOf?->number,
                StockEventType::MaterialConverted,
                $this->payload($reversal, $peran, 0.0) + [
                    'reverses_event_id' => $kejadianAsal,
                    'reversal_of' => $reversal->reversalOf?->number,
                ],
                'conversion',
                (int) $reversal->id,
                $lineId,
                (string) $reversal->number,
            );
        } catch (LedgerException $e) {
            throw ConversionRuleException::rule($e->rule, 'Pembalikan '.$reversal->number.' gagal: '.$e->getMessage());
        }
    }

    private function kirim(Conversion $cnv, ConversionInput|ConversionOutput $baris, MovementRequest $request): StockMovement
    {
        try {
            return $this->ledger->post($request);
        } catch (LedgerException $e) {
            throw ConversionRuleException::rule($e->rule, $cnv->number.' baris '.$baris->item?->code.' gagal diposting: '.$e->getMessage());
        }
    }

    private function lotBaru(ConversionOutput $output): ?int
    {
        if (! $output->item->tracksLot()) {
            return null;
        }

        if ($output->lot_no === null || $output->lot_no === '') {
            throw ConversionRuleException::rule('BR-LED-03', 'Nomor lot output '.$output->item->code.' wajib diisi.');
        }

        return (int) Lot::query()->firstOrCreate(
            ['item_id' => $output->item_id, 'lot_no' => $output->lot_no],
            ['received_at' => now()->toDateString()],
        )->id;
    }

    /** BR-CNV-04: potongan baru bersilsilah ke potongan input induknya. */
    private function potonganBaru(Conversion $cnv, ConversionOutput $output, ?ConversionInput $induk): int
    {
        return (int) Piece::create([
            'item_id' => $output->item_id,
            'piece_no' => Piece::nextPieceNo(),
            'length' => round((float) $output->qty_base, 4),
            'is_offcut' => $output->output_kind === ConversionOutputKind::Offcut,
            'parent_piece_id' => $induk?->piece_id,
            'origin_type' => 'conversion',
            'origin_id' => $cnv->id,
        ])->id;
    }

    /** @return array<string, mixed> */
    private function payload(Conversion $cnv, string $peran, float $kerf): array
    {
        return array_filter([
            'conversion_number' => $cnv->number,
            'conversion_type' => $cnv->conversion_type?->value,
            'role' => $peran,
            'project_code' => $cnv->project?->code,
            'warehouse_id' => (int) $cnv->warehouse_id,
            'warehouse_code' => $cnv->warehouse?->code,
            'kerf_total' => $kerf > 0 ? $kerf : null,
        ], fn ($v) => $v !== null);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Adjustment\Support;

use App\Domain\Access\Models\User;
use App\Domain\Adjustment\Enums\StockAdjustmentStatus;
use App\Domain\Adjustment\Exceptions\AdjustmentRuleException;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Adjustment\Models\StockAdjustmentLine;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\Serial;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use Illuminate\Support\Facades\DB;

/**
 * Katalog §2.12 `approved → posted` (otomatis): setiap baris ADJ menjadi satu
 * pergerakan di kartu stok lewat `StockLedger` (P-01) dengan kejadian
 * `stock_adjusted` (matriks §14: alasan, `count_session_ref` bila dari OPN).
 *
 * Baris ADJ pembalik membalik pergerakan asal lewat `StockLedger::reverse()`
 * — sekali saja (BR-LED-05) — dan kejadiannya menunjuk kejadian asal
 * (`reverses_event_id`, BR-GEN-03).
 */
class AdjustmentPoster
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function post(StockAdjustment $adjustment, ?User $actor): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $actor) {
            $adjustment = StockAdjustment::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($adjustment->id);

            if ($adjustment->status !== StockAdjustmentStatus::Approved) {
                throw AdjustmentRuleException::rule('BR-GEN-01', 'Hanya ADJ berstatus Disetujui yang bisa diposting.');
            }

            $adjustment->loadMissing('reason', 'stockCount', 'reversalOf');

            foreach ($adjustment->lines()->with('item.baseUom')->orderBy('id')->get() as $line) {
                $movement = $line->reversal_of_line_id !== null
                    ? $this->balik($adjustment, $line)
                    : $this->gerak($adjustment, $line, $actor);

                $line->forceFill(['movement_id' => $movement->id])->save();
            }

            $adjustment->forceFill([
                'status' => StockAdjustmentStatus::Posted,
                'posted_at' => now(),
            ])->save();

            activity('adjustment')->performedOn($adjustment)->causedBy($actor)
                ->withProperties(['baris' => $adjustment->lines()->count()])
                ->log('ADJ diposting ke kartu stok');

            return $adjustment->refresh();
        });
    }

    private function gerak(StockAdjustment $adjustment, StockAdjustmentLine $line, ?User $actor): StockMovement
    {
        [$lotId, $serialId, $pieceId] = $this->turunan($adjustment, $line);
        $delta = (float) $line->qty_delta;
        $masuk = $delta > 0;

        return $this->ledger->post(new MovementRequest(
            item: $line->item,
            qtyBase: round(abs($delta), 4),
            fromBinId: $masuk ? null : (int) $line->bin_id,
            toBinId: $masuk ? (int) $line->bin_id : null,
            stockStatus: $line->stock_status,
            lotId: $lotId,
            serialId: $serialId,
            pieceId: $pieceId,
            documentType: 'stock_adjustment',
            documentId: (int) $adjustment->id,
            documentLineId: (int) $line->id,
            documentNumber: (string) $adjustment->number,
            reasonCodeId: (int) ($line->reason_code_id ?? $adjustment->reason_code_id),
            performedBy: $actor,
            eventType: StockEventType::StockAdjusted,
            eventPayload: $this->payload($adjustment, $masuk ? 'in' : 'out'),
            notes: $line->notes ?? $adjustment->notes,
        ));
    }

    private function balik(StockAdjustment $adjustment, StockAdjustmentLine $line): StockMovement
    {
        $asal = StockAdjustmentLine::query()->find($line->reversal_of_line_id);
        $gerakAsal = $asal?->movement_id !== null ? StockMovement::query()->find($asal->movement_id) : null;

        if ($gerakAsal === null) {
            throw AdjustmentRuleException::rule('BR-LED-05', 'Baris asal ADJ pembalik belum pernah diposting.');
        }

        $kejadianAsal = StockEvent::query()
            ->where('payload->movement_id', $gerakAsal->id)
            ->value('event_id');

        return $this->ledger->reverse(
            $gerakAsal,
            (int) $adjustment->reason_code_id,
            'Pembalik '.$adjustment->reversalOf?->number,
            StockEventType::StockAdjusted,
            $this->payload($adjustment, (float) $line->qty_delta > 0 ? 'in' : 'out') + [
                'reverses_event_id' => $kejadianAsal,
                'reversal_of' => $adjustment->reversalOf?->number,
            ],
            'stock_adjustment',
            (int) $adjustment->id,
            (int) $line->id,
            (string) $adjustment->number,
        );
    }

    /**
     * Lot, serial, atau potongan baru dibuat saat diposting (bukan saat
     * diajukan), supaya ADJ yang ditolak tidak meninggalkan turunan yatim.
     *
     * @return array{0: ?int, 1: ?int, 2: ?int}
     */
    private function turunan(StockAdjustment $adjustment, StockAdjustmentLine $line): array
    {
        $lotId = $line->lot_id;
        $serialId = $line->serial_id;
        $pieceId = $line->piece_id;

        if ($lotId === null && $line->lot_no !== null) {
            $lotId = Lot::query()->where('item_id', $line->item_id)->where('lot_no', $line->lot_no)->value('id')
                ?? Lot::create([
                    'item_id' => $line->item_id,
                    'lot_no' => $line->lot_no,
                    'expiry_date' => $line->expiry_date?->toDateString(),
                    'received_at' => now()->toDateString(),
                ])->id;
        }

        if ($serialId === null && $line->serial_no !== null) {
            $serialId = Serial::query()->where('item_id', $line->item_id)->where('serial_no', $line->serial_no)->value('id')
                ?? Serial::create([
                    'item_id' => $line->item_id,
                    'serial_no' => $line->serial_no,
                    'acquired_at' => now()->toDateString(),
                ])->id;
        }

        if ($pieceId === null && $line->piece_length !== null && (float) $line->qty_delta > 0) {
            $urut = Piece::query()->count() + 1;

            do {
                $nomor = sprintf('P-%06d', $urut++);
            } while (Piece::query()->where('piece_no', $nomor)->exists());

            $pieceId = Piece::create([
                'item_id' => $line->item_id,
                'piece_no' => $nomor,
                'length' => (float) $line->piece_length,
                'is_offcut' => false,
                'origin_type' => 'stock_adjustment',
                'origin_id' => $adjustment->id,
            ])->id;
        }

        if ($lotId !== $line->lot_id || $serialId !== $line->serial_id || $pieceId !== $line->piece_id) {
            $line->forceFill(['lot_id' => $lotId, 'serial_id' => $serialId, 'piece_id' => $pieceId])->save();
        }

        return [$lotId === null ? null : (int) $lotId, $serialId === null ? null : (int) $serialId, $pieceId === null ? null : (int) $pieceId];
    }

    /** @return array<string, mixed> */
    private function payload(StockAdjustment $adjustment, string $arah): array
    {
        return array_filter([
            'adjustment_origin' => $adjustment->origin->value,
            'direction' => $arah,
            'reason_code' => $adjustment->reason?->code,
            'count_session_ref' => $adjustment->stockCount?->number,
        ], fn ($v) => $v !== null);
    }
}

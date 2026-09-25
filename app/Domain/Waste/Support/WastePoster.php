<?php

declare(strict_types=1);

namespace App\Domain\Waste\Support;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Piece;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Waste\Exceptions\WasteRuleException;
use App\Domain\Waste\Models\WasteDisposal;

/**
 * Menutup WST di kartu stok lewat `StockLedger` (P-01, matriks §14).
 *
 * Dibuang / dijual scrap: bin Waste → keluar, potongan `is_consumed`.
 * Dipakai ulang: bin Waste → bin penyimpanan tujuan berkondisi Tersedia.
 * Setiap baris menerbitkan `waste_disposed` dengan disposisinya.
 */
class WastePoster
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function post(WasteDisposal $wst, ?User $actor): void
    {
        $wst->loadMissing('project', 'warehouse', 'targetBin');
        $pakaiUlang = $wst->disposition->returnsToStock();

        foreach ($wst->lines()->with('item.baseUom')->orderBy('id')->get() as $line) {
            try {
                $movement = $this->ledger->post(new MovementRequest(
                    item: $line->item,
                    qtyBase: round((float) $line->qty_base, 4),
                    fromBinId: (int) $line->bin_id,
                    toBinId: $pakaiUlang ? (int) $wst->target_bin_id : null,
                    stockStatus: $pakaiUlang ? StockStatus::Available : $line->stock_status,
                    fromStockStatus: $pakaiUlang ? $line->stock_status : null,
                    lotId: $line->lot_id,
                    serialId: $line->serial_id,
                    pieceId: $line->piece_id,
                    projectId: (int) $wst->project_id,
                    documentType: 'waste_disposal',
                    documentId: (int) $wst->id,
                    documentLineId: (int) $line->id,
                    documentNumber: (string) $wst->number,
                    reasonCodeId: $line->reason_code_id !== null ? (int) $line->reason_code_id : null,
                    performedBy: $actor,
                    eventType: StockEventType::WasteDisposed,
                    eventPayload: array_filter([
                        'waste_disposal_number' => $wst->number,
                        'disposition' => $wst->disposition->value,
                        'project_code' => $wst->project?->code,
                        'warehouse_id' => (int) $wst->warehouse_id,
                        'warehouse_code' => $wst->warehouse?->code,
                        'target_bin_id' => $pakaiUlang ? (int) $wst->target_bin_id : null,
                        'from_stock_status' => $line->stock_status->value,
                    ], fn ($v) => $v !== null),
                    notes: 'BA waste: '.$wst->disposition->label(),
                ));
            } catch (LedgerException $e) {
                throw WasteRuleException::rule($e->rule, $wst->number.' baris '.$line->item?->code.' gagal ditutup: '.$e->getMessage());
            }

            if (! $pakaiUlang && $line->piece_id !== null) {
                Piece::query()->whereKey($line->piece_id)->update(['is_consumed' => true]);
            }

            $line->forceFill(['movement_id' => $movement->id])->save();
        }
    }
}

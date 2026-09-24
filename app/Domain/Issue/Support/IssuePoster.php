<?php

declare(strict_types=1);

namespace App\Domain\Issue\Support;

use App\Domain\Access\Models\User;
use App\Domain\Issue\Exceptions\IssueRuleException;
use App\Domain\Issue\Models\MaterialIssue;
use App\Domain\Issue\Models\MaterialIssueLine;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;

/**
 * Menulis ISU ke kartu stok lewat `StockLedger` (P-01, BR-STK-01).
 *
 * ISU biasa: setiap baris satu pergerakan bin Gudang Site → keluar dengan
 * kejadian `material_consumed` penanda proyek (matriks §14). ISU pembalik:
 * setiap baris membalik pergerakan asalnya lewat `StockLedger::reverse()` —
 * sekali saja (BR-LED-05) — dengan kejadian `material_consumed` yang menunjuk
 * kejadian asal (`reverses_event_id`, BR-GEN-03).
 */
class IssuePoster
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function consume(MaterialIssue $issue, ?User $actor): void
    {
        $issue->loadMissing('project', 'warehouse');

        foreach ($issue->lines()->with('item.baseUom')->orderBy('id')->get() as $line) {
            $movement = $this->ledger->post(new MovementRequest(
                item: $line->item,
                qtyBase: round((float) $line->qty_base, 4),
                fromBinId: (int) $line->bin_id,
                toBinId: null,
                stockStatus: StockStatus::Available,
                lotId: $line->lot_id,
                serialId: $line->serial_id,
                pieceId: $line->piece_id,
                projectId: (int) $issue->project_id,
                documentType: 'material_issue',
                documentId: (int) $issue->id,
                documentLineId: (int) $line->id,
                documentNumber: (string) $issue->number,
                performedBy: $actor,
                eventType: StockEventType::MaterialConsumed,
                eventPayload: $this->payload($issue, $line),
                notes: $line->work_note,
            ));

            $line->forceFill(['movement_id' => $movement->id])->save();
        }
    }

    public function reverse(MaterialIssue $reversal): void
    {
        $reversal->loadMissing('project', 'warehouse', 'reversalOf');

        foreach ($reversal->lines()->with('item.baseUom')->orderBy('id')->get() as $line) {
            $asal = $line->reversal_of_line_id !== null ? MaterialIssueLine::query()->find($line->reversal_of_line_id) : null;
            $gerakAsal = $asal?->movement_id !== null ? StockMovement::query()->find($asal->movement_id) : null;

            if ($gerakAsal === null) {
                throw IssueRuleException::rule('BR-LED-05', 'Baris asal ISU pembalik belum pernah dikonfirmasi.');
            }

            $kejadianAsal = StockEvent::query()->where('payload->movement_id', $gerakAsal->id)->value('event_id');

            $movement = $this->ledger->reverse(
                $gerakAsal,
                $reversal->reason_code_id !== null ? (int) $reversal->reason_code_id : null,
                'Pembalik '.$reversal->reversalOf?->number,
                StockEventType::MaterialConsumed,
                $this->payload($reversal, $line) + [
                    'reverses_event_id' => $kejadianAsal,
                    'reversal_of' => $reversal->reversalOf?->number,
                ],
                'material_issue',
                (int) $reversal->id,
                (int) $line->id,
                (string) $reversal->number,
            );

            $line->forceFill(['movement_id' => $movement->id])->save();
        }
    }

    /** @return array<string, mixed> */
    private function payload(MaterialIssue $issue, MaterialIssueLine $line): array
    {
        return array_filter([
            'issue_number' => $issue->number,
            'project_code' => $issue->project?->code,
            'warehouse_id' => (int) $issue->warehouse_id,
            'warehouse_code' => $issue->warehouse?->code,
            'work_note' => $line->work_note,
            'direction' => (float) $line->qty_base < 0 ? 'reversal' : 'out',
        ], fn ($v) => $v !== null);
    }
}

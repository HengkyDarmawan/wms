<?php

declare(strict_types=1);

namespace App\Domain\Adjustment\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Adjustment\Enums\AdjustmentOrigin;
use App\Domain\Adjustment\Enums\StockAdjustmentStatus;
use App\Domain\Adjustment\Exceptions\AdjustmentRuleException;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Adjustment\Models\StockAdjustmentLine;
use App\Domain\Adjustment\Support\AdjustmentLines;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Stock\Support\DocumentNumber;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `adjustment.create` — ADJ manual `submitted` → `pending_approval`
 * (Katalog §2.12).
 *
 * ADJ manual **selalu** masuk mesin approval (A-09); tanpa aturan yang cocok
 * penangan memberi satu lapis Kepala Gudang. Alasan dokumen wajib dari master
 * Alasan konteks penyesuaian (BR-GEN-02).
 *
 * `reverse()` membuat **ADJ pembalik** untuk ADJ yang sudah diposting
 * (BR-GEN-03): baris sama dengan jumlah berlawanan, `reversal_of_id` menunjuk
 * ADJ asal, dan saat diposting pergerakan asal dibalik sekali saja
 * (BR-LED-05). Pembalik juga ADJ manual yang butuh approval.
 */
class CreateStockAdjustment
{
    public function __construct(
        private readonly AdjustmentLines $lines,
        private readonly DocumentNumber $nomor,
        private readonly ApprovalEngine $approval,
    ) {}

    /**
     * @param  array{warehouse_id?: int|string, reason_code_id?: int|string|null, notes?: ?string}  $header
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function handle(array $header, array $lines, ?User $actor = null): StockAdjustment
    {
        $gudang = Warehouse::query()->find((int) ($header['warehouse_id'] ?? 0));

        if ($gudang === null) {
            throw AdjustmentRuleException::field('BR-GEN-09', 'warehouse_id', 'Gudang wajib dipilih dari cakupan Anda.');
        }

        $alasan = $this->lines->headerReason($header['reason_code_id'] ?? null);
        $baris = $this->lines->normalize((int) $gudang->id, $lines);

        return DB::transaction(function () use ($gudang, $alasan, $baris, $header, $actor) {
            $adj = StockAdjustment::create([
                'number' => $this->nomor->next('ADJ', (string) $gudang->code),
                'warehouse_id' => $gudang->id,
                'origin' => AdjustmentOrigin::Manual,
                'reason_code_id' => $alasan,
                'status' => StockAdjustmentStatus::Submitted,
                'submitted_by' => $actor?->id,
                'notes' => $this->teks($header['notes'] ?? null),
            ]);

            foreach ($baris as $b) {
                StockAdjustmentLine::create($b + ['stock_adjustment_id' => $adj->id]);
            }

            activity('adjustment')->performedOn($adj)->causedBy($actor)
                ->withProperties(['baris' => count($baris)])
                ->log('ADJ manual diajukan');

            return $this->ajukan($adj, $actor);
        });
    }

    /** ADJ pembalik untuk ADJ yang sudah diposting (BR-GEN-03, BR-LED-05). */
    public function reverse(StockAdjustment $original, mixed $reasonCodeId, ?string $notes = null, ?User $actor = null): StockAdjustment
    {
        if ($original->status !== StockAdjustmentStatus::Posted) {
            throw AdjustmentRuleException::rule('BR-GEN-03', 'Hanya ADJ yang sudah diposting yang bisa dibalik.');
        }

        if ($original->reversal_of_id !== null) {
            throw AdjustmentRuleException::rule('BR-LED-05', 'ADJ pembalik tidak bisa dibalik lagi.');
        }

        if (($ada = $original->activeReversal()) !== null) {
            throw AdjustmentRuleException::rule('BR-LED-05', 'ADJ ini sudah punya pembalik '.$ada->number.'; pembalikan hanya sekali.');
        }

        if ($actor !== null && ! $actor->canAccessWarehouse((int) $original->warehouse_id)) {
            throw AdjustmentRuleException::rule('BR-GEN-09', 'Gudang ADJ ini di luar cakupan Anda.');
        }

        $alasan = $this->lines->headerReason($reasonCodeId);
        $original->loadMissing('warehouse');

        return DB::transaction(function () use ($original, $alasan, $notes, $actor) {
            $adj = StockAdjustment::create([
                'number' => $this->nomor->next('ADJ', (string) $original->warehouse->code),
                'warehouse_id' => $original->warehouse_id,
                'origin' => AdjustmentOrigin::Manual,
                'reversal_of_id' => $original->id,
                'reason_code_id' => $alasan,
                'status' => StockAdjustmentStatus::Submitted,
                'submitted_by' => $actor?->id,
                'notes' => $this->teks($notes) ?? 'Pembalik '.$original->number,
            ]);

            foreach ($original->lines()->whereNotNull('movement_id')->orderBy('id')->get() as $l) {
                StockAdjustmentLine::create([
                    'stock_adjustment_id' => $adj->id,
                    'item_id' => $l->item_id,
                    'bin_id' => $l->bin_id,
                    'lot_id' => $l->lot_id,
                    'serial_id' => $l->serial_id,
                    'piece_id' => $l->piece_id,
                    'qty_delta' => -1 * (float) $l->qty_delta,
                    'stock_status' => $l->stock_status,
                    'reversal_of_line_id' => $l->id,
                    'notes' => 'Pembalik baris '.$original->number,
                ]);
            }

            activity('adjustment')->performedOn($adj)->causedBy($actor)
                ->withProperties(['pembalik_dari' => $original->number])
                ->log('ADJ pembalik diajukan');

            return $this->ajukan($adj, $actor);
        });
    }

    /** Katalog §2.12: manual `submitted → pending_approval` selalu (A-09). */
    private function ajukan(StockAdjustment $adj, ?User $actor): StockAdjustment
    {
        $adj->forceFill(['status' => StockAdjustmentStatus::PendingApproval])->save();

        $this->approval->submit(ApprovalDocumentType::StockAdjustment, $adj, $actor);

        return $adj->refresh();
    }

    private function teks(mixed $nilai): ?string
    {
        $isi = is_scalar($nilai) ? trim((string) $nilai) : '';

        return $isi === '' ? null : mb_substr($isi, 0, 255);
    }
}

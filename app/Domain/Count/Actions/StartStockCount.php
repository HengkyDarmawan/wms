<?php

declare(strict_types=1);

namespace App\Domain\Count\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Count\Enums\CountAssignmentStatus;
use App\Domain\Count\Enums\StockCountStatus;
use App\Domain\Count\Exceptions\CountRuleException;
use App\Domain\Count\Models\CountAssignment;
use App\Domain\Count\Models\CountLine;
use App\Domain\Count\Models\StockCount;
use App\Domain\Count\Support\CountScope;
use App\Domain\Shipment\Enums\PickTaskStatus;
use App\Domain\Shipment\Models\PickTaskLine;
use App\Domain\Warehouse\Actions\ChangeBinStatus;
use App\Domain\Warehouse\Enums\BinStatus;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `count.start` — `planned → in_progress` (Katalog §2.13).
 *
 * - Bila membekukan: tidak boleh ada PCK `in_progress` di bin cakupan, dan bin
 *   tidak sedang dibeku sesi lain (BR-OPN-02). Bin lalu dibeku atas nama sesi.
 * - Snapshot saldo **fisik** per bin × item × lot/serial/potongan × kondisi,
 *   termasuk yang dicadangkan dan yang di Loading Area (BR-OPN-01).
 * - Penugasan putaran 1 dibagi bergiliran ke tim, bin berpenanda hitung
 *   lebih dulu (A-67).
 */
class StartStockCount
{
    public function __construct(
        private readonly CountScope $scope,
        private readonly ChangeBinStatus $binStatus,
    ) {}

    public function handle(StockCount $count, ?User $actor = null): StockCount
    {
        if ($count->status !== StockCountStatus::Planned) {
            throw CountRuleException::rule('BR-GEN-01', 'Hanya sesi berstatus Direncanakan yang bisa dimulai.');
        }

        $bins = $this->scope->bins($count);

        if ($bins->isEmpty()) {
            throw CountRuleException::rule('BR-OPN-01', 'Cakupan sesi tidak berisi bin fisik yang bisa dihitung.');
        }

        $tim = $count->teamIds();

        if ($tim === []) {
            throw CountRuleException::rule('BR-OPN-05', 'Tim penghitung sesi masih kosong.');
        }

        $binIds = $bins->pluck('id')->map(fn ($v) => (int) $v)->all();

        if ($count->freeze_bins) {
            $pck = PickTaskLine::query()->whereIn('bin_id', $binIds)
                ->whereHas('pickTask', fn ($q) => $q->withoutGlobalScopes()->where('status', PickTaskStatus::InProgress->value))
                ->with('bin:id,code')->first();

            if ($pck !== null) {
                throw CountRuleException::rule(
                    'BR-OPN-02',
                    'Bin '.$pck->bin?->code.' sedang dipakai tugas picking yang berjalan. Selesaikan atau batalkan PCK dulu.',
                );
            }

            $bekuLain = $bins->first(fn ($b) => $b->bin_status === BinStatus::Frozen && (int) $b->frozen_by_count_id !== (int) $count->id);

            if ($bekuLain !== null) {
                throw CountRuleException::rule('BR-OPN-02', 'Bin '.$bekuLain->code.' sedang dibeku sesi opname lain.');
            }
        }

        return DB::transaction(function () use ($count, $bins, $binIds, $tim, $actor) {
            if ($count->freeze_bins) {
                foreach ($bins as $bin) {
                    $this->binStatus->freeze($bin, 'OPN '.$count->number, (int) $count->id, $actor);
                }
            }

            foreach ($this->scope->snapshot($count, $binIds) as $saldo) {
                CountLine::create([
                    'stock_count_id' => $count->id,
                    'bin_id' => $saldo->bin_id,
                    'item_id' => $saldo->item_id,
                    'lot_id' => $saldo->lot_id,
                    'serial_id' => $saldo->serial_id,
                    'piece_id' => $saldo->piece_id,
                    'stock_status' => $saldo->stock_status,
                    'system_qty' => (float) $saldo->qty_base,
                ]);
            }

            foreach ($bins->values() as $i => $bin) {
                CountAssignment::create([
                    'stock_count_id' => $count->id,
                    'bin_id' => $bin->id,
                    'counter_user_id' => $tim[$i % count($tim)],
                    'round' => 1,
                    'status' => CountAssignmentStatus::Pending,
                ]);
            }

            $count->forceFill([
                'status' => StockCountStatus::InProgress,
                'started_at' => now(),
            ])->save();

            activity('count')->performedOn($count)->causedBy($actor)
                ->withProperties(['bin' => count($binIds), 'beku' => $count->freeze_bins])
                ->log('Sesi opname dimulai'.($count->freeze_bins ? '; bin cakupan dibeku' : ' tanpa pembekuan'));

            return $count->refresh();
        });
    }
}

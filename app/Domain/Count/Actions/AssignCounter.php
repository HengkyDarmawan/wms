<?php

declare(strict_types=1);

namespace App\Domain\Count\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Count\Exceptions\CountRuleException;
use App\Domain\Count\Models\CountAssignment;
use App\Domain\Count\Models\StockCount;

/**
 * Permission: `count.assign` — menugaskan atau mengganti penghitung satu bin
 * (Glosarium `count_assignment`, A-95).
 *
 * Hitung ulang wajib oleh orang yang berbeda dari penghitung pertama bin itu
 * (BR-OPN-05). Penghitung harus pemegang `count.record` yang boleh mengakses
 * gudang bin (BR-GEN-09); ia ditambahkan ke tim sesi.
 */
class AssignCounter
{
    public function handle(CountAssignment $assignment, int $userId, ?User $actor = null): CountAssignment
    {
        $count = StockCount::withoutGlobalScopes()->findOrFail($assignment->stock_count_id);

        if (! $count->status->isCounting()) {
            throw CountRuleException::rule('BR-GEN-01', 'Penghitung hanya bisa diatur saat sesi berjalan atau hitung ulang.');
        }

        if ($assignment->isDone()) {
            throw CountRuleException::rule('BR-OPN-05', 'Bin ini sudah selesai dihitung; penugasannya tidak bisa diganti.');
        }

        $user = User::query()->find($userId);
        $assignment->loadMissing('bin');

        if ($user === null || ! $user->is_active || $user->client_id !== null
            || ! $user->hasPermission('count.record')
            || ! $user->canAccessWarehouse((int) $assignment->bin->warehouse_id)) {
            throw CountRuleException::field('BR-GEN-09', 'counter', 'Pengguna ini tidak bisa menghitung di gudang bin tersebut.');
        }

        if ($assignment->round === 2) {
            $pertama = CountAssignment::query()->where('stock_count_id', $count->id)
                ->where('bin_id', $assignment->bin_id)->where('round', 1)->value('counter_user_id');

            if ((int) $pertama === (int) $user->id) {
                throw CountRuleException::field('BR-OPN-05', 'counter', 'Hitung ulang wajib oleh penghitung yang berbeda dari hitungan pertama.');
            }
        }

        $lama = $assignment->counter_user_id;
        $assignment->forceFill(['counter_user_id' => $user->id])->save();

        $tim = $count->teamIds();

        if (! in_array((int) $user->id, $tim, true)) {
            $tim[] = (int) $user->id;
            $count->forceFill(['team_user_ids' => $tim])->save();
        }

        activity('count')->performedOn($count)->causedBy($actor)
            ->withProperties(['bin' => $assignment->bin->code, 'putaran' => $assignment->round, 'dari' => $lama, 'ke' => $user->id])
            ->log('Penghitung bin '.$assignment->bin->code.' (putaran '.$assignment->round.') ditetapkan: '.$user->name);

        return $assignment->refresh();
    }
}

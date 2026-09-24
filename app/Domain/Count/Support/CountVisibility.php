<?php

declare(strict_types=1);

namespace App\Domain\Count\Support;

use App\Domain\Access\Models\User;
use App\Domain\Count\Enums\StockCountStatus;
use App\Domain\Count\Models\CountAssignment;
use App\Domain\Count\Models\StockCount;

/**
 * Hitung buta (Glosarium §8 `blind_count`, Blueprint §9, A-103).
 *
 * Angka sistem dan hasil hitungan orang lain disembunyikan dari siapa pun
 * yang masih punya penugasan hitung terbuka di sesi itu. Selama sesi
 * berjalan hanya perekonsiliasi dan approver (yang tidak sedang menghitung)
 * yang melihat angka; setelah sesi masuk rekonsiliasi, pemegang
 * `count.view` juga.
 */
class CountVisibility
{
    public function canSeeNumbers(StockCount $count, User $user): bool
    {
        $masihMenghitung = CountAssignment::query()
            ->where('stock_count_id', $count->id)
            ->where('counter_user_id', $user->id)
            ->pending()
            ->exists();

        if ($masihMenghitung) {
            return false;
        }

        if (in_array($count->status, [StockCountStatus::Reconciling, StockCountStatus::Approved, StockCountStatus::Closed], true)) {
            return $user->hasPermission('count.view');
        }

        return $user->hasPermission('count.reconcile') || $user->hasPermission('count.approve');
    }
}

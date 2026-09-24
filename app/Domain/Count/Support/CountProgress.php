<?php

declare(strict_types=1);

namespace App\Domain\Count\Support;

use App\Domain\Access\Models\User;
use App\Domain\Count\Enums\CountAssignmentStatus;
use App\Domain\Count\Enums\StockCountStatus;
use App\Domain\Count\Enums\VarianceClass;
use App\Domain\Count\Models\CountAssignment;
use App\Domain\Count\Models\CountLine;
use App\Domain\Count\Models\StockCount;

/**
 * Menilai sesi setiap kali satu penugasan selesai dihitung.
 *
 * - Putaran 1 selesai semua → setiap baris diklasifikasi (BR-OPN-04). Bila ada
 *   selisih **sedang**, sesi otomatis ke `recount` (Katalog §2.13) dan bin
 *   yang bersangkutan mendapat penugasan putaran 2 kepada anggota tim yang
 *   **berbeda** dari penghitung pertama (BR-OPN-05, A-99).
 * - Putaran 2 selesai semua → baris hitung ulang memakai hasil putaran 2 dan
 *   diklasifikasi ulang. Tidak ada putaran 3; sisa selisih diputus di
 *   rekonsiliasi.
 */
class CountProgress
{
    public function __construct(private readonly VarianceClassifier $classifier) {}

    public function evaluate(StockCount $count, ?User $actor): StockCount
    {
        $count->refresh();

        if (! $count->status->isCounting()) {
            return $count;
        }

        $putaran1Selesai = ! CountAssignment::query()->where('stock_count_id', $count->id)
            ->where('round', 1)->pending()->exists();

        if (! $putaran1Selesai) {
            return $count;
        }

        if ($count->status === StockCountStatus::InProgress && ! $this->adaPutaran2($count)) {
            $this->klasifikasi($count, 1);

            $binUlang = CountLine::query()->where('stock_count_id', $count->id)
                ->where('variance_class', VarianceClass::Moderate->value)
                ->distinct()->pluck('bin_id')->map(fn ($v) => (int) $v)->all();

            if ($binUlang !== []) {
                CountLine::query()->where('stock_count_id', $count->id)
                    ->where('variance_class', VarianceClass::Moderate->value)
                    ->update(['is_recount' => true, 'updated_at' => now()]);

                $this->buatPutaran2($count, $binUlang);

                $count->forceFill(['status' => StockCountStatus::Recount])->save();

                activity('count')->performedOn($count)->causedBy($actor)
                    ->withProperties(['bin' => count($binUlang)])
                    ->log('Selisih sedang ditemukan: hitung ulang oleh penghitung berbeda (BR-OPN-05)');
            } else {
                activity('count')->performedOn($count)->causedBy($actor)
                    ->log('Semua bin terhitung; siap direkonsiliasi');
            }

            return $count->refresh();
        }

        $putaran2Selesai = ! CountAssignment::query()->where('stock_count_id', $count->id)
            ->where('round', 2)->pending()->exists();

        if ($count->status === StockCountStatus::Recount && $putaran2Selesai) {
            $this->klasifikasi($count, 2);

            activity('count')->performedOn($count)->causedBy($actor)
                ->log('Hitung ulang selesai; siap direkonsiliasi');
        }

        return $count->refresh();
    }

    /**
     * Mengisi angka akhir dan kelas selisih. Putaran 2 hanya menyentuh baris
     * hitung ulang dan baris temuan baru yang dicatat saat hitung ulang.
     */
    public function klasifikasi(StockCount $count, int $round): void
    {
        $baris = CountLine::query()->with('item.category')->where('stock_count_id', $count->id)
            ->when($round === 2, fn ($q) => $q->where(fn ($w) => $w->where('is_recount', true)->orWhereNotNull('counted_qty_r2')))
            ->get();

        foreach ($baris as $l) {
            $akhir = $l->counted_qty_r2 !== null ? (float) $l->counted_qty_r2 : ($l->counted_qty_r1 !== null ? (float) $l->counted_qty_r1 : null);

            if ($akhir === null) {
                continue;
            }

            $hasil = $this->classifier->classify((float) $l->system_qty, $akhir, $l->item);

            $l->forceFill([
                'final_qty' => $akhir,
                'variance_qty' => $hasil['variance'],
                'variance_pct' => $hasil['pct'],
                'variance_class' => $hasil['class'],
            ])->save();
        }
    }

    private function adaPutaran2(StockCount $count): bool
    {
        return CountAssignment::query()->where('stock_count_id', $count->id)->where('round', 2)->exists();
    }

    /** @param  array<int, int>  $binIds */
    private function buatPutaran2(StockCount $count, array $binIds): void
    {
        $tim = $count->teamIds();
        $beban = array_fill_keys($tim, 0);

        $pertama = CountAssignment::query()->where('stock_count_id', $count->id)->where('round', 1)
            ->whereIn('bin_id', $binIds)->pluck('counter_user_id', 'bin_id')->all();

        foreach ($binIds as $binId) {
            $kecuali = (int) ($pertama[$binId] ?? 0);
            $calon = array_filter($tim, fn (int $u) => $u !== $kecuali && $this->bolehMenghitung($u));
            $pilih = null;

            foreach ($calon as $u) {
                if ($pilih === null || $beban[$u] < $beban[$pilih]) {
                    $pilih = $u;
                }
            }

            if ($pilih !== null) {
                $beban[$pilih]++;
            }

            CountAssignment::create([
                'stock_count_id' => $count->id,
                'bin_id' => $binId,
                'counter_user_id' => $pilih,
                'round' => 2,
                'status' => CountAssignmentStatus::Pending,
            ]);
        }
    }

    private function bolehMenghitung(int $userId): bool
    {
        $user = User::query()->find($userId);

        return $user !== null && $user->is_active && $user->client_id === null && $user->hasPermission('count.record');
    }
}

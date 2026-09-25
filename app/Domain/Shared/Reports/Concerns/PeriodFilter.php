<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports\Concerns;

use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Carbon;

/**
 * Penyaring standar laporan (16-shared §9): rentang tanggal di zona company
 * (BR-GEN-07) dan pilihan gudang. Bawaan rentang = bulan berjalan.
 */
trait PeriodFilter
{
    /** @return array<string, array{label: string, options?: array<int|string, string>}> */
    protected function penyaringPeriode(): array
    {
        return ['date_from' => ['label' => 'Dari tanggal'], 'date_to' => ['label' => 'Sampai tanggal']];
    }

    /** @return array{label: string, options: array<int, string>} */
    protected function penyaringGudang(): array
    {
        return ['label' => 'Gudang', 'options' => Warehouse::query()->orderBy('code')->get(['id', 'code', 'name'])
            ->mapWithKeys(fn (Warehouse $w) => [$w->id => $w->code.' — '.$w->name])->all()];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Carbon, 1: Carbon} awal & akhir (UTC) untuk query
     */
    protected function periode(array $filters): array
    {
        $tz = tenant()?->timezone ?? 'Asia/Jakarta';
        $dari = $this->tanggal($filters['date_from'] ?? null, $tz) ?? now($tz)->startOfMonth();
        $sampai = ($this->tanggal($filters['date_to'] ?? null, $tz) ?? now($tz))->endOfDay();

        return [$dari->clone()->utc(), $sampai->clone()->utc()];
    }

    protected function tanggal(mixed $nilai, string $tz): ?Carbon
    {
        $isi = is_scalar($nilai) ? trim((string) $nilai) : '';

        if ($isi === '') {
            return null;
        }

        try {
            return Carbon::parse($isi, $tz)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Gudang terpilih bila ada dan masih dalam cakupan user (BR-ACC-05). @return array<int, int>|null */
    protected function gudangDipilih(array $filters): ?array
    {
        $izin = auth()->user()?->accessibleWarehouseIds();
        $pilih = (int) ($filters['warehouse_id'] ?? 0);

        if ($pilih > 0) {
            return $izin === null || in_array($pilih, $izin, true) ? [$pilih] : [];
        }

        return $izin;
    }
}

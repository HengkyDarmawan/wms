<?php

declare(strict_types=1);

namespace App\Domain\Master\Support;

use App\Domain\Master\Models\CompanySetting;
use App\Domain\Master\Models\Holiday;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Hari kerja company (A-270): hari kerja per minggu dari pengaturan
 * `work_days_per_week` (5 = Senin–Jumat, 6 = Senin–Sabtu, 7 = setiap hari;
 * bawaan 6) dikurangi hari libur aktif di `holidays`.
 *
 * Libur nasional & cuti bersama suatu tahun diisi otomatis sekali saat tahun
 * itu pertama kali dipakai (kunci `holiday_years_filled`), jadi yang sudah
 * dinonaktifkan Admin tidak muncul lagi.
 */
class WorkCalendar
{
    public const SETTING = 'work_days_per_week';

    public const FILLED = 'holiday_years_filled';

    /** @var array<int, array<string, true>> tahun => tanggal libur aktif */
    private array $libur = [];

    public function workDaysPerWeek(): int
    {
        return max(5, min(7, (int) CompanySetting::get(self::SETTING, 6)));
    }

    public function isHoliday(CarbonInterface $date): bool
    {
        return isset($this->liburTahun((int) $date->year)[$date->toDateString()]);
    }

    public function isWorkday(CarbonInterface $date): bool
    {
        return $date->dayOfWeekIso <= $this->workDaysPerWeek() && ! $this->isHoliday($date);
    }

    /** Mundur `$days` hari kerja dari `$from` (jam tetap), untuk batas "lebih lama dari N hari kerja". */
    public function subWorkdays(CarbonInterface $from, int $days): CarbonInterface
    {
        $tanggal = $from->copy();
        $sisa = max(0, $days);
        $jaga = 0;

        while ($sisa > 0 && $jaga++ < 3660) {
            $tanggal = $tanggal->subDay();

            if ($this->isWorkday($tanggal)) {
                $sisa--;
            }
        }

        return $tanggal;
    }

    /**
     * Isi libur nasional & cuti bersama satu tahun dari SKB; tanggal yang sudah
     * ada (aktif atau nonaktif) tidak diubah. Mengembalikan jumlah yang ditambah.
     */
    public function fillNational(int $year, ?int $userId = null): int
    {
        if (! NationalHolidays::has($year)) {
            return 0;
        }

        $tambah = DB::transaction(function () use ($year, $userId) {
            $ada = Holiday::query()->whereYear('date', $year)->pluck('date')->map(fn ($d) => $d->toDateString())->all();
            $n = 0;

            foreach (NationalHolidays::for($year) as $h) {
                if (in_array($h['date'], $ada, true)) {
                    continue;
                }

                Holiday::create(['date' => $h['date'], 'name' => $h['name'], 'kind' => $h['kind'], 'created_by' => $userId]);
                $n++;
            }

            $sudah = array_map('intval', (array) CompanySetting::get(self::FILLED, []));

            if (! in_array($year, $sudah, true)) {
                $sudah[] = $year;
                sort($sudah);
                CompanySetting::put(self::FILLED, $sudah);
            }

            return $n;
        });

        unset($this->libur[$year]);

        return $tambah;
    }

    /** @return array<string, true> */
    private function liburTahun(int $year): array
    {
        if (! isset($this->libur[$year])) {
            $sudah = array_map('intval', (array) CompanySetting::get(self::FILLED, []));

            if (NationalHolidays::has($year) && ! in_array($year, $sudah, true)) {
                $this->fillNational($year);
            }

            $this->libur[$year] = Holiday::query()->where('is_active', true)->whereYear('date', $year)
                ->pluck('date')->mapWithKeys(fn ($d) => [$d->toDateString() => true])->all();
        }

        return $this->libur[$year];
    }
}

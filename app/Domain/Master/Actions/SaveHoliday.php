<?php

declare(strict_types=1);

namespace App\Domain\Master\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\HolidayKind;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\Holiday;
use App\Domain\Master\Support\WorkCalendar;
use Carbon\Carbon;

/**
 * Permission: `company_setting.manage` — kalender libur company (A-270):
 * tambah libur company, aktif/nonaktifkan libur (tanpa hapus), dan isi libur
 * nasional & cuti bersama satu tahun dari SKB.
 */
class SaveHoliday
{
    public function __construct(private readonly WorkCalendar $kalender) {}

    /** @param  array{date?: mixed, name?: mixed}  $data */
    public function add(array $data, ?User $actor = null): Holiday
    {
        $galat = [];
        $tanggal = null;

        try {
            $tanggal = trim((string) ($data['date'] ?? '')) === '' ? null : Carbon::parse((string) $data['date'])->toDateString();
        } catch (\Throwable) {
            $tanggal = null;
        }

        $nama = mb_substr(trim((string) ($data['name'] ?? '')), 0, 120);

        if ($tanggal === null) {
            $galat['date'] = 'Tanggal libur wajib diisi.';
        }

        if ($nama === '') {
            $galat['name'] = 'Nama libur wajib diisi, mis. "Libur akhir tahun kantor".';
        }

        if ($galat !== []) {
            throw MasterRuleException::fields($galat, 'BR-GEN-11');
        }

        $ada = Holiday::query()->whereDate('date', $tanggal)->first();

        if ($ada !== null) {
            throw MasterRuleException::fields(['date' => 'Tanggal '.Carbon::parse($tanggal)->format('d/m/Y').' sudah ada di kalender ('.$ada->name.($ada->is_active ? '' : ', nonaktif').'); aktifkan atau nonaktifkan dari daftar.'], 'A-270');
        }

        return Holiday::create(['date' => $tanggal, 'name' => $nama, 'kind' => HolidayKind::Company, 'created_by' => $actor?->id]);
    }

    public function setActive(Holiday $holiday, bool $active, ?User $actor = null): Holiday
    {
        $holiday->forceFill(['is_active' => $active])->save();

        activity('master')->performedOn($holiday)->causedBy($actor)
            ->log(($active ? 'Libur diaktifkan: ' : 'Libur dinonaktifkan (hari kerja): ').$holiday->date->format('d/m/Y').' '.$holiday->name);

        return $holiday;
    }

    public function fillNational(int $year, ?User $actor = null): int
    {
        return $this->kalender->fillNational($year, $actor?->id);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Master\Livewire;

use App\Domain\Master\Actions\SaveHoliday;
use App\Domain\Master\Livewire\Concerns\HandlesMasterRules;
use App\Domain\Master\Models\Holiday;
use App\Domain\Master\Support\NationalHolidays;
use App\Domain\Master\Support\WorkCalendar;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Kartu *Kalender libur* di Pengaturan company (A-270): libur nasional & cuti
 * bersama terisi otomatis per tahun, bisa dinonaktifkan (company tetap
 * bekerja) atau ditambah libur company.
 */
class HolidayCalendar extends Component
{
    use HandlesMasterRules;

    public int $tahun;

    /** @var array{date: string, name: string} */
    public array $form = ['date' => '', 'name' => ''];

    public function mount(): void
    {
        $this->authorize('company_setting.view');
        $this->tahun = (int) now()->year;
    }

    public function updatedTahun(): void
    {
        $this->tahun = max(2000, min(2100, $this->tahun));
    }

    public function isiNasional(SaveHoliday $action): void
    {
        $this->authorize('company_setting.manage');
        $n = $action->fillNational($this->tahun, auth()->user());
        $this->dispatch('pesan', teks: $n > 0 ? __(':n libur nasional & cuti bersama :tahun ditambahkan.', ['n' => $n, 'tahun' => $this->tahun]) : __('Libur nasional :tahun sudah lengkap.', ['tahun' => $this->tahun]));
    }

    public function tambah(SaveHoliday $action): void
    {
        $this->authorize('company_setting.manage');
        $this->resetValidation();

        if ($this->jalankan(fn () => $action->add($this->form, auth()->user()))) {
            $this->form = ['date' => '', 'name' => ''];
            $this->dispatch('pesan', teks: __('Libur company ditambahkan.'));
        }
    }

    public function aturAktif(int $id, bool $aktif, SaveHoliday $action): void
    {
        $this->authorize('company_setting.manage');
        $action->setActive(Holiday::query()->findOrFail($id), $aktif, auth()->user());
    }

    public function render(): View
    {
        $kalender = app(WorkCalendar::class);
        // Memicu pengisian otomatis tahun ini bila datanya tersedia.
        $kalender->isHoliday(now()->setDate($this->tahun, 1, 1));

        return view('livewire.master.holiday-calendar', [
            'libur' => Holiday::query()->whereYear('date', $this->tahun)->orderBy('date')->get(),
            'tersedia' => NationalHolidays::has($this->tahun),
            'tahunData' => NationalHolidays::years(),
            'hariKerja' => $kalender->workDaysPerWeek(),
            'bolehUbah' => auth()->user()?->hasPermission('company_setting.manage') ?? false,
        ]);
    }
}

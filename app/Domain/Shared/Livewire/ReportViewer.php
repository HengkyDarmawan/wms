<?php

declare(strict_types=1);

namespace App\Domain\Shared\Livewire;

use App\Domain\Shared\Reports\Report;
use App\Domain\Shared\Reports\ReportRegistry;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Satu layar untuk seluruh laporan (Access §9, Master §9, Warehouse §9).
 *
 * Laporan didefinisikan sebagai data di {@see ReportRegistry}, jadi menambah
 * laporan baru tidak menambah layar baru.
 */
class ReportViewer extends Component
{
    #[Locked]
    public string $reportKey;

    /** @var array<string, string> */
    #[Url(except: [])]
    public array $filters = [];

    public function mount(string $reportKey): void
    {
        $laporan = app(ReportRegistry::class)->find($reportKey);

        $this->authorizeReport($laporan);

        $this->reportKey = $reportKey;

        foreach (array_keys($laporan->filters()) as $kunci) {
            $this->filters[$kunci] ??= '';
        }
    }

    public function bersihkanFilter(): void
    {
        $this->filters = array_map(fn () => '', $this->filters);
    }

    public function render(): View
    {
        $laporan = app(ReportRegistry::class)->find($this->reportKey);

        $this->authorizeReport($laporan);

        $baris = $laporan->rows($this->filters);

        return view('livewire.shared.report-viewer', [
            'laporan' => $laporan,
            'kolom' => $laporan->columns(),
            'penyaring' => $laporan->filters(),
            // Layar dibatasi supaya laporan besar tidak membuat halaman berat;
            // ekspor Excel tetap memuat seluruh baris.
            'baris' => $baris->take(500),
            'jumlahTotal' => $baris->count(),
        ]);
    }

    private function authorizeReport(Report $laporan): void
    {
        abort_unless(auth()->user()?->hasPermission($laporan->permission()) ?? false, 403);
    }
}

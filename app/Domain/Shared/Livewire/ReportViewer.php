<?php

declare(strict_types=1);

namespace App\Domain\Shared\Livewire;

use App\Domain\Access\Models\User;
use App\Domain\Shared\Livewire\Concerns\CariPilihan;
use App\Domain\Shared\Pilihan\Pilihan;
use App\Domain\Shared\Reports\Report;
use App\Domain\Shared\Reports\ReportRegistry;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Satu layar untuk seluruh laporan (Access §9, Master §9, Warehouse §9).
 *
 * Laporan didefinisikan sebagai data di {@see ReportRegistry}, jadi menambah
 * laporan baru tidak menambah layar baru.
 *
 * Penyaring berpilihan memakai `<x-pilih>` (A-396): `cari` dimuat sekaligus,
 * `server` (proyek, vendor) dicari ke server lewat `Report::pilihanPenyaring`.
 */
class ReportViewer extends Component
{
    use CariPilihan;

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
        $penyaring = $laporan->filters();

        // Isian awal penyaring cari-server: ±30 pertama + label nilai terpilih.
        $opsiServer = [];
        foreach ($penyaring as $kunci => $definisi) {
            if ($definisi['server'] ?? false) {
                $opsiServer[$kunci] = $this->pilihanPenyaring($laporan, $kunci)?->awalDengan($this->filters[$kunci] ?? '') ?? [];
            }
        }

        return view('livewire.shared.report-viewer', [
            'laporan' => $laporan,
            'kolom' => $laporan->columns(),
            'penyaring' => $penyaring,
            'opsiServer' => $opsiServer,
            // Layar dibatasi supaya laporan besar tidak membuat halaman berat;
            // ekspor Excel tetap memuat seluruh baris.
            'baris' => $baris->take(500),
            'jumlahTotal' => $baris->count(),
        ]);
    }

    protected function pilihanServer(string $model): ?Pilihan
    {
        if (! preg_match('/^filters\.([a-z_]+)$/', $model, $m)) {
            return null;
        }

        $laporan = app(ReportRegistry::class)->find($this->reportKey);

        return $this->bolehBaca($laporan) ? $this->pilihanPenyaring($laporan, $m[1]) : null;
    }

    /**
     * Laporan hanya untuk pengguna internal (route `internal`); akun Klien
     * tidak pernah mendapat pilihan dari server, meskipun izinnya cocok (A-396).
     */
    private function pilihanPenyaring(Report $laporan, string $kunci): ?Pilihan
    {
        $user = auth()->user();

        return $user instanceof User && ! $user->isClient() ? $laporan->pilihanPenyaring($kunci) : null;
    }

    private function bolehBaca(Report $laporan): bool
    {
        return auth()->user()?->hasPermission($laporan->permission()) ?? false;
    }

    private function authorizeReport(Report $laporan): void
    {
        abort_unless($this->bolehBaca($laporan), 403);
    }
}

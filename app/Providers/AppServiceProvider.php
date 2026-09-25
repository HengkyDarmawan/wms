<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Shared\Livewire\ReportViewer;
use App\Http\Middleware\EnsureClientPortal;
use App\Http\Middleware\EnsureInternalArea;
use App\Http\Middleware\EnsureSubscriptionState;
use App\Http\Middleware\InitializeTenancyBySubdomain;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Harus di register(), bukan boot(): Livewire mendaftarkan route update
        // bawaannya saat boot() hanya bila belum ada yang menetapkannya. Dengan
        // menetapkannya lebih awal, route bawaan tanpa middleware tenant tidak
        // pernah lahir sehingga tidak ada jalur pintas yang tertinggal.
        $this->secureLivewireEndpoint();
    }

    public function boot(): void
    {
        // BR-GEN-07 / NFR-07: waktu disimpan UTC, ditampilkan di zona company.
        // Dipakai di view: `$model->created_at?->lokal()->format('d/m/Y H:i')`.
        Carbon::macro('lokal', function () {
            /** @var Carbon $this */
            return $this->copy()->timezone(tenant()?->timezone ?? 'Asia/Jakarta');
        });

        // Paginasi memakai markup Bootstrap 5 agar serasi dengan template NexaDash (D-05).
        Paginator::useBootstrapFive();

        // P-04/P-01: melarang penulisan atribut yang tidak dideklarasikan saat pengembangan.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Layar laporan dipakai seluruh modul, jadi didaftarkan di sini (AD-02).
        Livewire::component('shared.report-viewer', ReportViewer::class);
    }

    /**
     * Livewire mendaftarkan `POST /livewire/update` sendiri dengan grup `web` saja,
     * sehingga seluruh aksi Livewire berjalan di luar middleware tenant: tanpa
     * inisialisasi tenant (query jatuh ke database pusat), tanpa gerbang langganan
     * (BR-SUB-02, BR-SUB-03), dan tanpa pemisahan area internal & portal (BR-PRJ-07).
     *
     * Route itu kita daftarkan ulang dengan susunan yang sama seperti
     * `routes/tenant.php`: tenancy lebih dulu supaya sesi dibaca dari database
     * company (BR-ACC-06), baru grup `web`, lalu gerbang langganan.
     *
     * `EnsureInternalArea` dan `EnsureClientPortal` melekat pada halaman asal,
     * bukan pada endpoint update, jadi keduanya didaftarkan sebagai middleware
     * persisten agar Livewire menjalankannya ulang memakai route halaman asal.
     */
    protected function secureLivewireEndpoint(): void
    {
        Livewire::setUpdateRoute(
            fn ($handle, $path) => Route::post($path, $handle)->middleware([
                InitializeTenancyBySubdomain::class,
                'web',
                EnsureSubscriptionState::class,
            ]),
        );

        Livewire::addPersistentMiddleware([
            EnsureInternalArea::class,
            EnsureClientPortal::class,
        ]);
    }
}

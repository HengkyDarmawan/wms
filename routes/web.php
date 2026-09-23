<?php

declare(strict_types=1);

use App\Http\Controllers\Platform\PlatformDashboardController;
use App\Http\Controllers\Platform\PlatformLoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Route pusat (domain platform)
|--------------------------------------------------------------------------
|
| Hanya melayani landing page, login Super Admin, callback SSO (F3), dan
| pemilih company (A-48). Data operasional company ada di routes/tenant.php.
|
| Route di sini DIBATASI domain pusat supaya tidak bentrok dengan route tenant
| yang memakai URI sama (mis. "/") di subdomain company.
|
*/

$centralDomains = (array) config('tenancy.central_domains', ['wms.test']);

foreach ($centralDomains as $index => $domain) {
    Route::domain($domain)->group(function () use ($index): void {
        // Nama route cukup dipasang sekali; domain lain hanya alias.
        $utama = $index === 0;

        $home = Route::get('/', fn () => view('central.welcome'));

        if ($utama) {
            $home->name('central.home');
        }

        // Login Super Admin (Blueprint §4.1). Guard `platform` dan tabel
        // `platform_users` sudah ada sejak modul Access, tetapi sebelum ini
        // tidak punya route masuk sehingga akun seeder tidak bisa dipakai.
        Route::middleware('guest:platform')->group(function () use ($utama): void {
            $show = Route::get('/admin/login', [PlatformLoginController::class, 'show']);
            $store = Route::post('/admin/login', [PlatformLoginController::class, 'store'])
                ->middleware('throttle:login');

            if ($utama) {
                $show->name('platform.login');
                $store->name('platform.login.store');
            }
        });

        Route::middleware('auth:platform')->group(function () use ($utama): void {
            $dashboard = Route::get('/admin', PlatformDashboardController::class);
            $logout = Route::post('/admin/logout', [PlatformLoginController::class, 'destroy']);

            if ($utama) {
                $dashboard->name('platform.dashboard');
                $logout->name('platform.logout');
            }
        });
    });
}

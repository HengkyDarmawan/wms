<?php

declare(strict_types=1);

use App\Http\Controllers\Access\TwoFactorSetupController;
use App\Http\Controllers\Central\LandingController;
use App\Http\Controllers\Platform\CompanyController;
use App\Http\Controllers\Platform\PaymentController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\PlatformDashboardController;
use App\Http\Controllers\Platform\PlatformLoginController;
use App\Http\Controllers\Platform\PlatformTwoFactorController;
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

        // Landing page produk (30-landing-page). "Masuk ke company Anda" hanya
        // mengalihkan ke subdomain company lewat GET tanpa mengubah data (A-221).
        $home = Route::get('/', [LandingController::class, 'show']);
        $masuk = Route::get('/masuk', [LandingController::class, 'enter'])->middleware('throttle:60,1');

        if ($utama) {
            $home->name('central.home');
            $masuk->name('central.enter');
        }

        // Login Super Admin (Blueprint §4.1). Guard `platform` dan tabel
        // `platform_users` sudah ada sejak modul Access, tetapi sebelum ini
        // tidak punya route masuk sehingga akun seeder tidak bisa dipakai.
        Route::middleware('guest:platform')->group(function () use ($utama): void {
            $show = Route::get('/admin/login', [PlatformLoginController::class, 'show']);
            $store = Route::post('/admin/login', [PlatformLoginController::class, 'store'])
                ->middleware('throttle:login');

            // 2FA Super Admin (A-200).
            $tantang = Route::get('/admin/two-factor', [PlatformTwoFactorController::class, 'challenge']);
            $periksa = Route::post('/admin/two-factor', [PlatformTwoFactorController::class, 'verify'])
                ->middleware('throttle:login');

            if ($utama) {
                $show->name('platform.login');
                $store->name('platform.login.store');
                $tantang->name('platform.two-factor');
                $periksa->name('platform.two-factor.store');
            }
        });

        Route::middleware('auth:platform')->group(function () use ($utama): void {
            // Nama route hanya di domain pertama; domain lain alias tanpa nama.
            $r = fn ($route, string $nama) => $utama ? $route->name($nama) : $route;

            $r(Route::get('/admin', PlatformDashboardController::class), 'platform.dashboard');
            $r(Route::post('/admin/logout', [PlatformLoginController::class, 'destroy']), 'platform.logout');

            // Modul Platform penuh (17-platform-login §6). Semua perubahan lewat POST (NFR-02).
            $r(Route::get('/admin/companies/create', [CompanyController::class, 'create']), 'platform.companies.create');
            $r(Route::post('/admin/companies', [CompanyController::class, 'store']), 'platform.companies.store');
            $r(Route::get('/admin/companies/{company}', [CompanyController::class, 'show'])->whereNumber('company'), 'platform.companies.show');
            $r(Route::post('/admin/companies/{company}/provision', [CompanyController::class, 'provision'])->whereNumber('company'), 'platform.companies.provision');
            $r(Route::post('/admin/companies/{company}/suspend', [CompanyController::class, 'suspend'])->whereNumber('company'), 'platform.companies.suspend');
            $r(Route::post('/admin/companies/{company}/reactivate', [CompanyController::class, 'reactivate'])->whereNumber('company'), 'platform.companies.reactivate');
            $r(Route::post('/admin/companies/{company}/flags', [CompanyController::class, 'flag'])->whereNumber('company'), 'platform.companies.flag');
            $r(Route::post('/admin/companies/{company}/support', [CompanyController::class, 'support'])->whereNumber('company'), 'platform.companies.support');

            $r(Route::get('/admin/payments', [PaymentController::class, 'index']), 'platform.payments.index');
            $r(Route::post('/admin/payments/{payment}/verify', [PaymentController::class, 'verify'])->whereNumber('payment'), 'platform.payments.verify');
            $r(Route::post('/admin/payments/{payment}/reject', [PaymentController::class, 'reject'])->whereNumber('payment'), 'platform.payments.reject');
            $r(Route::get('/admin/payments/{payment}/proof', [PaymentController::class, 'proof'])->whereNumber('payment'), 'platform.payments.proof');

            $r(Route::get('/admin/plans', [PlanController::class, 'index']), 'platform.plans.index');
            $r(Route::get('/admin/plans/create', [PlanController::class, 'create']), 'platform.plans.create');
            $r(Route::post('/admin/plans', [PlanController::class, 'store']), 'platform.plans.store');
            $r(Route::get('/admin/plans/{plan}/edit', [PlanController::class, 'edit'])->whereNumber('plan'), 'platform.plans.edit');
            $r(Route::post('/admin/plans/{plan}', [PlanController::class, 'update'])->whereNumber('plan'), 'platform.plans.update');

            // Keamanan akun Super Admin — 2FA (A-200); aksi pengaturan sama dengan profil tenant.
            $r(Route::get('/admin/security', [PlatformTwoFactorController::class, 'security']), 'platform.security');
            $r(Route::post('/admin/security/two-factor', [TwoFactorSetupController::class, 'begin']), 'platform.two-factor.begin');
            $r(Route::post('/admin/security/two-factor/confirm', [TwoFactorSetupController::class, 'confirm']), 'platform.two-factor.confirm');
            $r(Route::post('/admin/security/two-factor/cancel', [TwoFactorSetupController::class, 'cancel']), 'platform.two-factor.cancel');
            $r(Route::post('/admin/security/two-factor/disable', [TwoFactorSetupController::class, 'disable']), 'platform.two-factor.disable');
            $r(Route::post('/admin/security/two-factor/recovery-codes', [TwoFactorSetupController::class, 'regenerate']), 'platform.two-factor.regenerate');
        });
    });
}

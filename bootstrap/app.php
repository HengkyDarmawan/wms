<?php

use App\Http\Middleware\EnsureClientPortal;
use App\Http\Middleware\EnsureInternalArea;
use App\Http\Middleware\EnsureSubscriptionState;
use App\Http\Middleware\InitializeTenancyBySubdomain;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            /*
             * Route tenant (subdomain company). Tenancy diinisialisasi SEBELUM
             * StartSession supaya sesi dibaca dari database company, bukan pusat
             * (Arsitektur §3, BR-ACC-06).
             */
            Route::middleware([
                InitializeTenancyBySubdomain::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                ValidateCsrfToken::class,
                SubstituteBindings::class,
                EnsureSubscriptionState::class,
            ])->group(base_path('routes/tenant.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => InitializeTenancyBySubdomain::class,
            'subscription' => EnsureSubscriptionState::class,
            'portal' => EnsureClientPortal::class,
            'internal' => EnsureInternalArea::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

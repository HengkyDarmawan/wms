<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Platform\Support\SingleCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A-417: dalam mode satu company, rute pusat (Super Admin `/admin`, webhook)
 * berbagi host dengan aplikasi company. Sesi pusat (database pusat) diberi
 * nama cookie sendiri supaya login Super Admin dan login company di host yang
 * sama tidak saling menimpa. Dipasang di depan grup `web` (sebelum StartSession).
 */
class SeparatePlatformSessionCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        if (SingleCompany::enabled()) {
            // Idempoten: nama dasar disimpan sekali (uji menjalankan beberapa request dalam satu proses).
            $dasar = config('session.cookie_base') ?? config('session.cookie');
            config(['session.cookie_base' => $dasar, 'session.cookie' => $dasar.'-platform']);
            // Store sesi bisa sudah dibuat saat boot; namanya diubah langsung juga.
            app('session')->driver()->setName($dasar.'-platform');
        }

        return $next($request);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Access\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * BR-PRJ-07: /portal hanya untuk user klien. User internal yang membuka portal
 * dikembalikan ke back-office (TC-ACC-18).
 */
class EnsureClientPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user !== null && ! $user->isClient()) {
            abort(403, __('Halaman ini khusus untuk user klien.'));
        }

        return $next($request);
    }
}

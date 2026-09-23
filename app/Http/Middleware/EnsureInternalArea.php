<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Access\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * BR-PRJ-07: back-office hanya untuk user internal. User klien diarahkan ke /portal
 * (TC-ACC-17).
 */
class EnsureInternalArea
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user !== null && $user->isClient()) {
            return redirect()->route('portal.dashboard');
        }

        return $next($request);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Access\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * BR-PRJ-07: back-office hanya untuk user internal. User klien diarahkan ke /portal
 * (TC-ACC-17).
 *
 * BR-ACC-01 per request (A-425): user yang tidak lagi punya penugasan berlaku
 * (mis. auditor eksternal lewat tanggal akhir, Tim site berakhir) dikeluarkan
 * dari sesinya, bukan dibiarkan login tanpa izin.
 */
class EnsureInternalArea
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user !== null && ($keluar = self::keluarkanBilaAksesHabis($request, $user)) !== null) {
            return $keluar;
        }

        if ($user !== null && $user->isClient()) {
            return redirect()->route('portal.dashboard');
        }

        return $next($request);
    }

    /** Dipakai juga oleh EnsureClientPortal: sama dengan syarat login BR-ACC-01. */
    public static function keluarkanBilaAksesHabis(Request $request, User $user): ?Response
    {
        if ($user->validAssignments()->isNotEmpty() || $user->hasRoleCode('company_admin')) {
            return null;
        }

        $klien = $user->isClient();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($klien ? 'portal.login' : 'login')
            ->withErrors(['email' => __('Masa akses Anda sudah berakhir. Hubungi Admin Company bila masih perlu masuk.')]);
    }
}

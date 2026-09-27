<?php

declare(strict_types=1);

namespace App\Http\Controllers\Access;

use App\Domain\Access\Actions\ImpersonateUser;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\Impersonation;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Masuk sebagai" (10-access §6.8, A-260). Halaman pemilih untuk Admin Company;
 * mulai/ganti dan kembali hanya lewat POST (transisi tidak lewat GET).
 */
class ImpersonationController extends Controller
{
    public function index(): View
    {
        abort_unless(Impersonation::enabled() && Impersonation::allowed(), Impersonation::enabled() ? 403 : 404);

        return view('access.impersonate.index');
    }

    public function store(Request $request, User $user, ImpersonateUser $action): RedirectResponse
    {
        abort_unless(Impersonation::enabled(), 404);
        abort_unless(Impersonation::allowed(), 403);

        try {
            $target = $action->handle($request, $user);
        } catch (AccessRuleException $e) {
            return back()->with('impersonate_error', $e->getMessage());
        }

        return redirect()->route($target->isClient() ? 'portal.dashboard' : 'dashboard')
            ->with('status', __('Anda sekarang masuk sebagai :nama.', ['nama' => $target->name]));
    }

    public function destroy(Request $request, ImpersonateUser $action): RedirectResponse
    {
        if (! Impersonation::active()) {
            return redirect()->route('dashboard');
        }

        $admin = $action->stop($request);

        if ($admin === null) {
            return redirect()->route('login');
        }

        return redirect()->route('impersonate.index')
            ->with('status', __('Anda kembali sebagai :nama.', ['nama' => $admin->name]));
    }
}

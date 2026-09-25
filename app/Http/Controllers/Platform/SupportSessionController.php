<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domain\Access\Actions\StartSupportSession;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Platform\Models\Company;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pintu masuk akses dukungan di subdomain company (BR-SUB-04, A-180). Tautan
 * bertanda tangan dari layar Super Admin membuka halaman konfirmasi (GET);
 * sesi baru dibuka lewat POST ke tautan yang sama (NFR-02).
 */
class SupportSessionController extends Controller
{
    public function show(Request $request, int $supportAccess): View
    {
        return view('platform.support.enter', ['action' => $request->fullUrl()]);
    }

    public function store(Request $request, int $supportAccess, StartSupportSession $action): RedirectResponse
    {
        /** @var Company $company */
        $company = tenant();

        try {
            $action->handle($request, $company, $supportAccess, (int) $request->query('admin'), (string) $request->query('nonce'));
        } catch (AccessRuleException $e) {
            abort(403, $e->getMessage());
        }

        return redirect()->route('dashboard');
    }
}

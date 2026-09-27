<?php

declare(strict_types=1);

namespace App\Http\Controllers\WhatsApp;

use App\Domain\Platform\Models\Company;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/**
 * Pengalih tombol URL WhatsApp (A-276): template Meta hanya boleh satu basis
 * URL, jadi tombol menunjuk `https://<pusat>/buka/<kode company>/<path>` dan
 * pengalih ini meneruskan ke subdomain company. Hanya path relatif; company
 * tak dikenal → 404. Login tetap diminta di subdomain company.
 */
class WhatsAppRedirectController extends Controller
{
    public function __invoke(string $kode, ?string $path = null): RedirectResponse
    {
        $company = Company::query()->where('code', mb_strtoupper($kode))->orWhere('subdomain', mb_strtolower($kode))->first();

        abort_if($company === null, 404);

        $path = ltrim((string) $path, '/');
        abort_if(str_contains($path, '//') || str_contains($path, '..') || preg_match('#^[A-Za-z0-9/_\-\.]*$#', $path) !== 1, 404);

        return redirect()->away($company->url($path));
    }
}

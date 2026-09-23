<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Platform\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Identifikasi tenant lewat kolom `companies.subdomain` (A-01, Arsitektur §3.1).
 * Tidak memakai tabel `domains` bawaan paket agar sesuai ERD 08a.
 *
 * Subdomain yang tidak dikenal menghasilkan 404 bermerek (NFR-01, TC-ACC-22).
 */
class InitializeTenancyBySubdomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $company = $this->resolveCompany($request->getHost());

        if ($company === null) {
            abort(404, __('Company tidak dikenal.'));
        }

        tenancy()->initialize($company);

        // Zona waktu tampilan mengikuti company; penyimpanan tetap UTC (BR-GEN-07).
        config(['app.company_timezone' => $company->timezone]);

        return $next($request);
    }

    private function resolveCompany(string $host): ?Company
    {
        $subdomain = $this->extractSubdomain($host);

        if ($subdomain === null) {
            return null;
        }

        return Company::query()->where('subdomain', $subdomain)->first();
    }

    private function extractSubdomain(string $host): ?string
    {
        $centralDomains = (array) config('tenancy.central_domains', []);

        if (in_array($host, $centralDomains, true)) {
            return null;
        }

        $found = null;

        foreach ($centralDomains as $domain) {
            $suffix = '.'.$domain;

            if (! str_ends_with($host, $suffix)) {
                continue;
            }

            $candidate = substr($host, 0, -strlen($suffix));

            // Ambil kecocokan dengan sisa subdomain terpendek (domain pusat terpanjang).
            if ($candidate !== '' && ($found === null || strlen($candidate) < strlen($found))) {
                $found = $candidate;
            }
        }

        if ($found === null || str_contains($found, '.')) {
            return null;
        }

        return $found;
    }
}

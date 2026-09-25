<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Domain\Platform\Actions\CreateCompany;
use App\Domain\Platform\Models\Plan;
use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Landing page produk di domain pusat (30-landing-page, Blueprint §18).
 *
 * Tidak ada pendaftaran mandiri: company dibuat Super Admin (A-176), jadi
 * ajakan utamanya "Minta demo" (A-220). Formulir "Masuk ke company Anda"
 * hanya mengarahkan ke halaman masuk di subdomain company (A-221).
 */
class LandingController extends Controller
{
    /** Pola subdomain sama dengan pembuatan company (A-176). */
    private const SUBDOMAIN_PATTERN = '/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/';

    public function show(Request $request): View
    {
        return view('central.landing', [
            'plans' => $this->plans(),
            'salesEmail' => (string) config('wms.sales_email'),
            'centralDomain' => $this->centralDomain(),
            'companyPort' => $this->portSuffix($request),
        ]);
    }

    /**
     * GET murni: memvalidasi subdomain lalu mengalihkan ke halaman masuk
     * company. Tidak mengubah data dan tidak memeriksa apakah company ada,
     * supaya daftar pelanggan tidak bisa ditebak dari halaman publik (A-221).
     */
    public function enter(Request $request): RedirectResponse
    {
        $company = $request->query('company', '');
        $as = $request->query('as', 'team');

        $input = [
            'company' => is_string($company) ? strtolower(trim($company)) : '',
            'as' => is_string($as) ? $as : '',
        ];

        $validator = Validator::make($input, [
            'company' => ['required', 'max:63', 'regex:'.self::SUBDOMAIN_PATTERN, 'not_in:'.implode(',', CreateCompany::RESERVED_SUBDOMAINS)],
            'as' => ['required', 'in:team,portal'],
        ], [
            'company.required' => __('Isi alamat company Anda.'),
            'company.max' => __('Alamat company paling banyak 63 karakter.'),
            'company.regex' => __('Alamat company hanya huruf kecil, angka, dan tanda hubung.'),
            'company.not_in' => __('Alamat itu bukan alamat company.'),
            'as.*' => __('Pilih masuk sebagai tim company atau klien.'),
        ]);

        if ($validator->fails()) {
            return redirect()->to(route('central.home').'#masuk')
                ->withErrors($validator)
                ->withInput($input);
        }

        $path = $input['as'] === 'portal' ? '/portal/login' : '/login';

        return redirect()->away($this->companyUrl($request, $input['company']).$path);
    }

    /**
     * Paket aktif untuk bagian Paket. Harga yang tampil hanya harga langganan
     * platform (D-02), bukan nilai barang (D-07). Bila database pusat tidak bisa
     * dibaca, landing tetap tampil tanpa daftar paket.
     *
     * @return Collection<int, Plan>
     */
    private function plans(): Collection
    {
        try {
            return Plan::query()
                ->where('is_active', true)
                ->orderBy('monthly_price')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'monthly_price', 'trial_days', 'storage_quota_mb']);
        } catch (QueryException $e) {
            report($e);

            return new Collection;
        }
    }

    private function centralDomain(): string
    {
        return (string) config('tenancy.central_domains.0', 'wms.test');
    }

    /** Skema & port mengikuti permintaan; host selalu `<subdomain>.<domain pusat utama>`. */
    private function companyUrl(Request $request, string $subdomain): string
    {
        $port = $this->portSuffix($request);

        return $request->getScheme().'://'.$subdomain.'.'.$this->centralDomain().($port === '' ? '' : ':'.$port);
    }

    /** Port non-bawaan permintaan (mis. "8000" saat `artisan serve`), kosong untuk 80/443. */
    private function portSuffix(Request $request): string
    {
        $scheme = $request->getScheme();
        $port = (int) $request->getPort();
        $bawaan = $port === 0 || ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);

        return $bawaan ? '' : (string) $port;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Platform\Support;

use App\Domain\Platform\Models\Company;

/**
 * A-417 — **mode satu company**: `WMS_SINGLE_COMPANY=<subdomain>` membuat
 * semua host membuka satu company (tanpa subdomain, baris hosts, atau SSL
 * wildcard). Mesin multi-company tidak berubah; mengosongkan setelan
 * mengembalikan identifikasi per subdomain (A-01).
 */
final class SingleCompany
{
    public static function enabled(): bool
    {
        return self::subdomain() !== null;
    }

    public static function subdomain(): ?string
    {
        $nilai = config('access.single_company');

        return is_string($nilai) && trim($nilai) !== '' ? mb_strtolower(trim($nilai)) : null;
    }

    public static function company(): ?Company
    {
        $sub = self::subdomain();

        return $sub === null ? null : Company::query()->where('subdomain', $sub)->first();
    }

    /** Host dari APP_URL — alamat tunggal aplikasi dalam mode ini. */
    public static function host(): string
    {
        return (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost');
    }
}

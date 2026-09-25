<?php

declare(strict_types=1);

namespace App\Domain\Platform\Support;

use App\Domain\Platform\Enums\CompanyStatus;
use App\Domain\Platform\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Perulangan job harian per company: hanya company yang databasenya dipakai
 * (bukan `provisioning` yang belum punya database, bukan `terminated`), dan
 * galat satu company tidak menghentikan company lain.
 */
class OperatingCompanies
{
    /**
     * @param  array<int, int|string>|null  $ids  pilihan `--tenants`; null = semua yang beroperasi
     * @param  callable(Company): void  $kerja
     */
    public static function each(?array $ids, callable $kerja, ?Command $perintah = null): void
    {
        $daftar = Company::query()
            ->when($ids !== null && $ids !== [], fn ($q) => $q->whereIn('id', $ids))
            ->whereNotIn('status', [CompanyStatus::Provisioning->value, CompanyStatus::Terminated->value])
            ->pluck('id')->all();

        tenancy()->runForMultiple($daftar, function (Company $company) use ($kerja, $perintah): void {
            try {
                $kerja($company);
            } catch (\Throwable $e) {
                Log::error('Job harian gagal untuk company '.$company->code.': '.$e->getMessage());
                $perintah?->error('Company '.$company->getTenantKey().': gagal — '.$e->getMessage());
            }
        });
    }
}

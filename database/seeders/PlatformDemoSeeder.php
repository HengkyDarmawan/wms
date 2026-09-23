<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Platform\Enums\CompanyStatus;
use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Domain\Platform\Models\Company;
use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\Subscription;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Company demo `DEMO` di database pusat — docs/00-akun-uji.md §2.
 *
 * HANYA untuk dev, demo, dan staging. Membuat company memicu pembuatan database
 * tenant beserta migrasinya (TenancyServiceProvider, Arsitektur §3.5), jadi
 * seeder ini menolak berjalan di produksi.
 */
class PlatformDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('PlatformDemoSeeder tidak boleh dijalankan di produksi.');
        }

        $plan = Plan::query()->where('code', 'standard')->first();

        if ($plan === null) {
            throw new RuntimeException('Paket `standard` belum ada. Jalankan PlatformSeeder lebih dulu.');
        }

        $company = Company::query()->where('code', 'DEMO')->first();

        if ($company === null) {
            $company = Company::create([
                'code' => 'DEMO',
                'name' => 'PT Demo Konstruksi',
                'subdomain' => 'demo',
                'db_name' => config('tenancy.database.prefix').'demo',
                'timezone' => 'Asia/Jakarta',
                'status' => CompanyStatus::Active,
                'plan_id' => $plan->id,
            ]);
        }

        // Langganan yang sudah ada tidak dikembalikan menjadi trial saat seeder
        // dijalankan ulang; status langganan adalah data hidup, bukan acuan.
        $langganan = Subscription::query()->where('company_id', $company->getTenantKey())->first();

        if ($langganan === null) {
            Subscription::create([
                'company_id' => $company->getTenantKey(),
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Trial,
                'trial_ends_at' => now()->addDays(14),
                'current_period_start' => now()->toDateString(),
                'current_period_end' => now()->addDays(14)->toDateString(),
            ]);
        }

        $this->command?->info('Company demo siap: '.$company->db_name.'.');
    }
}

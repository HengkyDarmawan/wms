<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder bawaan `php artisan db:seed` untuk database pusat.
 *
 * Di produksi hanya data acuan yang dibuat. Company demo beserta database
 * tenantnya hanya lahir di lingkungan non-produksi; untuk produksi pakai
 * {@see ProductionSeeder} yang tidak pernah menyentuh data contoh.
 *
 * Data tenant diisi lewat `tenants:seed`
 * ({@see \Database\Seeders\Tenant\TenantDatabaseSeeder}).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PlatformSeeder::class);

        if (app()->environment('production')) {
            $this->command?->warn('Lingkungan produksi: company demo dilewati.');

            return;
        }

        $this->call(PlatformDemoSeeder::class);
    }
}

<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;

/**
 * Seeder bawaan setiap company baru (dipanggil `tenants:seed`).
 * Hanya data referensi; data contoh ada di DemoSeeder.
 */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ReferenceSeeder::class);
        $this->call(MasterReferenceSeeder::class);
        $this->call(WarehouseReferenceSeeder::class);
    }
}

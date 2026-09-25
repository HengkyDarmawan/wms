<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Platform\Actions\ProvisionCompany;
use Database\Seeders\Tenant\TenantDatabaseSeeder;
use Illuminate\Database\Seeder;

/**
 * Seeder produksi — docs/00-akun-uji.md §6.
 *
 * Hanya data acuan: paket langganan dan Super Admin dari `.env`. Tidak pernah
 * membuat company demo, akun contoh, maupun data operasional.
 *
 * Jalankan: `php83 artisan db:seed --class=Database\Seeders\ProductionSeeder`
 *
 * Data acuan tenant (permission, role bawaan, satuan, alasan, kategori
 * penyimpanan) diisi saat Super Admin membuat company lewat
 * {@see ProvisionCompany}, yang menjalankan
 * {@see TenantDatabaseSeeder}.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PlatformSeeder::class);
    }
}

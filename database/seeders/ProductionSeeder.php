<?php

declare(strict_types=1);

namespace Database\Seeders;

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
 * penyimpanan) dibuat otomatis saat company baru lahir lewat
 * {@see \Database\Seeders\Tenant\TenantDatabaseSeeder}.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PlatformSeeder::class);
    }
}

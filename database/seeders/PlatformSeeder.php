<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\PlatformUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Data acuan database pusat: paket langganan dan akun Super Admin.
 * Aman dijalankan di produksi — lihat docs/00-akun-uji.md §1.
 *
 * Company demo TIDAK dibuat di sini. Membuat company memicu pembuatan database
 * tenant, jadi `php artisan db:seed` di produksi dulu ikut membuat company DEMO
 * beserta databasenya. Data contoh sekarang ada di {@see PlatformDemoSeeder}.
 */
class PlatformSeeder extends Seeder
{
    /**
     * Password dev dari docs/00-akun-uji.md §1. Hanya dipakai di lingkungan
     * lokal dan pengujian; di luar itu password wajib datang dari `.env`.
     */
    private const DEV_PASSWORD = 'Wms#2026!Admin';

    public function run(): void
    {
        Plan::updateOrCreate(
            ['code' => 'standard'],
            [
                'name' => 'Standar',
                'monthly_price' => 0,
                'wa_quota' => 1000,
                'storage_quota_mb' => 10240,
                'is_active' => true,
            ],
        );

        $email = (string) env('PLATFORM_ADMIN_EMAIL', 'superadmin@wms.test');
        $admin = PlatformUser::query()->where('email', $email)->first();

        if ($admin === null) {
            PlatformUser::create([
                'email' => $email,
                'name' => (string) env('PLATFORM_ADMIN_NAME', 'Super Admin Platform'),
                'password' => Hash::make($this->resolvePassword()),
            ]);

            $this->command?->info('Super Admin dibuat: '.$email);
        } else {
            // Password yang sudah dipakai tidak ditimpa diam-diam saat seeder
            // dijalankan ulang; ganti password adalah tindakan sadar lewat `.env`.
            $this->command?->info('Super Admin sudah ada, password tidak diubah: '.$email);
        }

        $this->command?->info('Pusat siap: paket langganan dan Super Admin.');
    }

    /**
     * Di luar lokal dan pengujian, password wajib datang dari `.env`. Seeder
     * berhenti dengan galat, bukan diam-diam memakai password yang tertulis
     * di repo dan di dokumentasi.
     */
    private function resolvePassword(): string
    {
        $password = (string) env('PLATFORM_ADMIN_PASSWORD', '');

        if ($password !== '') {
            return $password;
        }

        if (app()->environment(['local', 'testing'])) {
            return self::DEV_PASSWORD;
        }

        throw new RuntimeException(
            'PLATFORM_ADMIN_PASSWORD wajib diisi di .env sebelum menjalankan seeder di lingkungan ini.',
        );
    }
}

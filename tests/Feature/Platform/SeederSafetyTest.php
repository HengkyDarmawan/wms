<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Domain\Platform\Models\Company;
use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\PlatformUser;
use Database\Seeders\PlatformSeeder;
use Database\Seeders\ProductionSeeder;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-PLT-01 — seeder produksi tidak boleh membuat data contoh.
 *
 * `php artisan db:seed` dulu membuat company DEMO beserta database tenantnya,
 * menimpa password Super Admin dengan nilai yang tertanam di kode, dan
 * mengembalikan langganan apa pun menjadi trial 14 hari.
 */
class SeederSafetyTest extends TenantTestCase
{
    #[Test]
    public function tc_plt_01_seeder_produksi_tidak_membuat_company_demo(): void
    {
        $sebelum = Company::query()->count();

        (new ProductionSeeder)->run();

        $this->assertSame($sebelum, Company::query()->count(), 'Seeder produksi tidak boleh membuat company.');
        $this->assertNull(Company::query()->where('code', 'DEMO')->first());
    }

    #[Test]
    public function tc_plt_01b_seeder_produksi_membuat_paket_dan_super_admin(): void
    {
        (new PlatformSeeder)->run();

        $this->assertNotNull(Plan::query()->where('code', 'standard')->first());
        $this->assertNotNull(PlatformUser::query()->where('email', 'superadmin@wms.test')->first());
    }

    #[Test]
    public function tc_plt_01c_menjalankan_ulang_tidak_menimpa_password_super_admin(): void
    {
        $admin = PlatformUser::create([
            'email' => 'superadmin@wms.test',
            'name' => 'Super Admin Platform',
            'password' => Hash::make('PasswordProduksiAsli#1'),
        ]);

        (new PlatformSeeder)->run();

        $this->assertTrue(
            Hash::check('PasswordProduksiAsli#1', $admin->refresh()->password),
            'Password Super Admin yang sudah dipakai tidak boleh ditimpa seeder.',
        );
    }
}

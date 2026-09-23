<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Domain\Platform\Models\PlatformUser;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-PLT-02 — Super Admin bisa masuk di domain pusat.
 *
 * Guard `platform` dan tabel `platform_users` sudah ada sejak modul Access,
 * tetapi tidak pernah punya route masuk: akun yang dibuat seeder tidak bisa
 * dipakai sama sekali.
 */
class PlatformLoginTest extends TenantTestCase
{
    private function centralUrl(string $path = '/'): string
    {
        return 'http://'.config('tenancy.central_domains.0').'/'.ltrim($path, '/');
    }

    private function superAdmin(string $password = 'Rahasia#2026!'): PlatformUser
    {
        return PlatformUser::create([
            'email' => 'superadmin@wms.test',
            'name' => 'Super Admin Platform',
            'password' => Hash::make($password),
        ]);
    }

    #[Test]
    public function tc_plt_02_super_admin_bisa_masuk_dan_keluar(): void
    {
        $admin = $this->superAdmin();

        $this->get($this->centralUrl('admin/login'))->assertOk();

        $this->post($this->centralUrl('admin/login'), [
            'email' => 'superadmin@wms.test',
            'password' => 'Rahasia#2026!',
        ])->assertRedirect($this->centralUrl('admin'));

        $this->assertAuthenticatedAs($admin, 'platform');
        $this->assertNotNull($admin->refresh()->last_login_at);

        $this->get($this->centralUrl('admin'))->assertOk();

        $this->post($this->centralUrl('admin/logout'))
            ->assertRedirect($this->centralUrl('admin/login'));

        $this->assertGuest('platform');
    }

    #[Test]
    public function tc_plt_02b_password_salah_ditolak_tanpa_membocorkan_akun(): void
    {
        $this->superAdmin();

        $salah = $this->post($this->centralUrl('admin/login'), [
            'email' => 'superadmin@wms.test',
            'password' => 'salah',
        ]);

        $tidakAda = $this->post($this->centralUrl('admin/login'), [
            'email' => 'bukan-siapa-siapa@wms.test',
            'password' => 'salah',
        ]);

        $salah->assertSessionHasErrors('email');
        $tidakAda->assertSessionHasErrors('email');

        // Pesannya sama persis, jadi keberadaan akun tidak bocor (NFR-02).
        $this->assertSame(
            $salah->getSession()->get('errors')->first('email'),
            $tidakAda->getSession()->get('errors')->first('email'),
        );

        $this->assertGuest('platform');
    }

    #[Test]
    public function tc_plt_02c_beranda_super_admin_menuntut_login(): void
    {
        $this->get($this->centralUrl('admin'))->assertRedirect();

        $this->assertGuest('platform');
    }
}

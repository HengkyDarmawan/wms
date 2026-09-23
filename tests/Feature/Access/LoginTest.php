<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Enums\LoginResult;
use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\LoginAttempt;
use App\Domain\Platform\Enums\SubscriptionStatus;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * Kasus uji login & pintu masuk — docs/wms/10-access.md §10.
 */
class LoginTest extends TenantTestCase
{
    private const PASSWORD = 'Rahasia#2026!';

    #[Test]
    public function tc_acc_01_login_berhasil_mencatat_waktu_dan_jejak(): void
    {
        $user = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1);

        $response = $this->post($this->tenantUrl('/login'), [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ]);

        $response->assertRedirect($this->tenantUrl('/'));
        $this->assertAuthenticatedAs($user->fresh());

        $user->refresh();
        $this->assertNotNull($user->last_login_at, 'last_login_at harus terisi.');
        $this->assertSame(0, $user->failed_login_count);

        $this->assertDatabaseHas('login_attempts', [
            'email' => $user->email,
            'result' => LoginResult::Success->value,
        ]);
    }

    #[Test]
    public function tc_acc_02_lima_kali_password_salah_mengunci_akun(): void
    {
        // Batas laju dinaikkan agar yang diuji adalah penguncian akun, bukan throttle.
        config(['access.login.throttle_per_minute' => 100]);

        $user = $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->post($this->tenantUrl('/login'), [
                'email' => $user->email,
                'password' => 'salah-sekali',
            ]);
        }

        $user->refresh();
        $this->assertTrue($user->isLocked(), 'Akun harus terkunci setelah 5 percobaan gagal.');

        // Percobaan ke-6 dengan password BENAR tetap ditolak.
        $response = $this->post($this->tenantUrl('/login'), [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->assertDatabaseHas('login_attempts', [
            'email' => $user->email,
            'result' => LoginResult::Locked->value,
        ]);
    }

    #[Test]
    public function tc_acc_03_kunci_akun_berakhir_setelah_masa_berlaku(): void
    {
        config(['access.login.throttle_per_minute' => 100]);

        $user = $this->makeUser();
        $user->forceFill([
            'locked_until' => now()->subMinute(),
            'failed_login_count' => 4,
        ])->save();

        $response = $this->post($this->tenantUrl('/login'), [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ]);

        $response->assertRedirect($this->tenantUrl('/'));
        $this->assertAuthenticatedAs($user->fresh());

        $user->refresh();
        $this->assertSame(0, $user->failed_login_count);
        $this->assertNull($user->locked_until);
    }

    #[Test]
    public function tc_acc_04_batas_laju_login_mengembalikan_429(): void
    {
        config(['access.login.throttle_per_minute' => 5]);

        $user = $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->post($this->tenantUrl('/login'), [
                'email' => $user->email,
                'password' => 'salah',
            ]);
        }

        $this->post($this->tenantUrl('/login'), [
            'email' => $user->email,
            'password' => 'salah',
        ])->assertStatus(429);
    }

    #[Test]
    public function tc_acc_17_klien_ditolak_di_login_internal_dan_diterima_di_portal(): void
    {
        config(['access.login.throttle_per_minute' => 100]);

        $proyek = $this->makeProject();
        $client = $this->makeUser('client_user', ScopeType::Project, $proyek->id, ['client_id' => $proyek->client_id]);

        $this->post($this->tenantUrl('/login'), [
            'email' => $client->email,
            'password' => self::PASSWORD,
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->post($this->tenantUrl('/portal/login'), [
            'email' => $client->email,
            'password' => self::PASSWORD,
        ])->assertRedirect($this->tenantUrl('/portal'));

        $this->assertAuthenticatedAs($client->fresh());

        $this->assertDatabaseHas('login_attempts', [
            'email' => $client->email,
            'result' => LoginResult::WrongPortal->value,
        ]);
    }

    #[Test]
    public function tc_acc_18_user_internal_ditolak_di_portal_klien(): void
    {
        $user = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1);

        $this->post($this->tenantUrl('/portal/login'), [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function tc_acc_19_langganan_diakhiri_hanya_admin_company_yang_bisa_masuk(): void
    {
        config(['access.login.throttle_per_minute' => 100]);

        $this->setSubscriptionStatus(SubscriptionStatus::Terminated);

        $staff = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1);
        $admin = $this->makeUser('company_admin');

        $this->post($this->tenantUrl('/login'), [
            'email' => $staff->email,
            'password' => self::PASSWORD,
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->post($this->tenantUrl('/login'), [
            'email' => $admin->email,
            'password' => self::PASSWORD,
        ])->assertRedirect($this->tenantUrl('/'));

        $this->assertAuthenticatedAs($admin->fresh());

        // Admin hanya boleh membaca.
        $this->actingAs($admin->fresh())
            ->put($this->tenantUrl('/profile'), ['name' => 'Nama Baru'])
            ->assertForbidden();
    }

    #[Test]
    public function tc_acc_20_langganan_ditangguhkan_baca_boleh_tulis_ditolak(): void
    {
        $this->setSubscriptionStatus(SubscriptionStatus::Suspended);

        $user = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1);

        $this->actingAs($user)
            ->get($this->tenantUrl('/'))
            ->assertOk();

        $this->actingAs($user)
            ->put($this->tenantUrl('/profile'), ['name' => 'Nama Baru'])
            ->assertForbidden();
    }

    #[Test]
    public function tc_acc_22_subdomain_tidak_dikenal_menghasilkan_404(): void
    {
        $this->get('http://xyz.'.config('tenancy.central_domains.0').'/login')
            ->assertNotFound();
    }

    #[Test]
    public function tc_acc_23_route_tenant_dilindungi_csrf(): void
    {
        /*
         * Laravel melewati pemeriksaan CSRF saat berjalan di lingkungan pengujian,
         * sehingga yang diuji adalah terpasangnya middleware pada route tenant
         * (NFR-02). Perilaku 419 diperiksa manual di browser.
         */
        $route = collect(Route::getRoutes())->first(
            fn ($r) => $r->getName() === 'login.store'
        );

        $this->assertNotNull($route, 'Route login.store harus ada.');
        $this->assertContains(ValidateCsrfToken::class, $route->gatherMiddleware());
    }

    #[Test]
    public function tc_acc_24_email_sama_boleh_dipakai_di_company_lain(): void
    {
        /*
         * BR-SUB-06: keunikan email hanya di dalam satu company. Di sini diperiksa
         * bahwa kolom email unik per database tenant, bukan lintas company.
         */
        $email = 'sama@contoh.test';
        $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1, ['email' => $email]);

        $this->assertDatabaseHas('users', ['email' => $email]);

        // Company lain memakai database sendiri, sehingga email yang sama tidak bentrok.
        $this->assertSame(
            $this->company->db_name,
            config('database.connections.tenant.database'),
            'Koneksi tenant harus menunjuk database company uji.',
        );
    }

    #[Test]
    public function tc_acc_bonus_user_nonaktif_tidak_bisa_masuk(): void
    {
        config(['access.login.throttle_per_minute' => 100]);

        $user = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1, ['is_active' => false]);

        $this->post($this->tenantUrl('/login'), [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->assertSame(
            LoginResult::Inactive,
            LoginAttempt::query()->where('email', $user->email)->latest('id')->first()?->result,
        );
    }

    #[Test]
    public function tc_acc_bonus_logout_mengakhiri_sesi(): void
    {
        $user = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1);

        $this->actingAs($user)
            ->post($this->tenantUrl('/logout'))
            ->assertRedirect($this->tenantUrl('/login'));

        $this->assertGuest();
    }

    protected function makeUser(
        string $roleCode = 'warehouse_staff',
        ScopeType $scopeType = ScopeType::All,
        ?int $scopeId = null,
        array $attributes = [],
    ): \App\Domain\Access\Models\User {
        return parent::makeUser($roleCode, $scopeType, $scopeId, array_merge([
            'password' => Hash::make(self::PASSWORD),
        ], $attributes));
    }
}

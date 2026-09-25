<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Domain\Access\Actions\GrantSupportAccess;
use App\Domain\Access\Actions\ManageTwoFactor;
use App\Domain\Access\Actions\StartSupportSession;
use App\Domain\Access\Enums\LoginResult;
use App\Domain\Access\Support\TotpVerifier;
use App\Domain\Platform\Enums\CompanyStatus;
use App\Domain\Platform\Models\FeatureFlag;
use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\PlatformAuditLog;
use App\Domain\Platform\Models\PlatformLoginAttempt;
use App\Domain\Platform\Models\PlatformUser;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-PLT-08 s.d. TC-PLT-11 & TC-PLT-13 — penguncian login Super Admin (A-182), paket,
 * penangguhan manual & flag fitur (A-179, A-183), dan akses dukungan
 * hanya-baca (BR-SUB-04, A-180), 2FA Super Admin (A-200).
 */
class PlatformAdminTest extends TenantTestCase
{
    private function centralUrl(string $path): string
    {
        return 'http://'.config('tenancy.central_domains.0').'/'.ltrim($path, '/');
    }

    private function superAdmin(string $email = 'sa@wms.test'): PlatformUser
    {
        return PlatformUser::create(['email' => $email, 'name' => 'Super Admin '.$email, 'password' => Hash::make('Rahasia#2026!')]);
    }

    #[Test]
    public function tc_plt_08_login_super_admin_terkunci_setelah_gagal_berulang(): void
    {
        config(['access.login.max_attempts' => 3, 'access.login.throttle_per_minute' => 100]);
        $sa = $this->superAdmin();

        foreach (range(1, 3) as $i) {
            $this->post($this->centralUrl('admin/login'), ['email' => 'sa@wms.test', 'password' => 'salah'])->assertSessionHasErrors('email');
        }

        $this->assertTrue($sa->refresh()->isLocked());
        $this->post($this->centralUrl('admin/login'), ['email' => 'sa@wms.test', 'password' => 'Rahasia#2026!'])->assertSessionHasErrors('email');
        $this->assertGuest('platform');
        $this->assertSame(2, PlatformLoginAttempt::query()->where('email', 'sa@wms.test')->where('result', LoginResult::Locked->value)->count());

        $sa->forceFill(['locked_until' => now()->subMinute()])->save();
        $this->post($this->centralUrl('admin/login'), ['email' => 'sa@wms.test', 'password' => 'Rahasia#2026!'])->assertRedirect($this->centralUrl('admin'));
        $this->assertSame(0, $sa->refresh()->failed_login_count);
        $this->assertSame(1, PlatformLoginAttempt::query()->where('result', LoginResult::Success->value)->count());
    }

    #[Test]
    public function tc_plt_13_super_admin_menyalakan_2fa_lalu_masuk_dengan_kode(): void
    {
        $sa = $this->superAdmin();

        // Keamanan akun: mulai → konfirmasi dengan kode autentikator → kode pemulihan tampil sekali.
        $this->actingAs($sa, 'platform')->get($this->centralUrl('admin/security'))->assertOk()->assertSee(__('Atur dua langkah'))->assertSee(__('2FA mati'));
        $this->actingAs($sa, 'platform')->post($this->centralUrl('admin/security/two-factor'))->assertRedirect();
        $rahasia = (string) session('access.2fa.setup_secret');
        $this->actingAs($sa, 'platform')->post($this->centralUrl('admin/security/two-factor/confirm'), ['code' => '12345'])->assertSessionHasErrors('code');
        $this->actingAs($sa, 'platform')->post($this->centralUrl('admin/security/two-factor/confirm'), ['code' => app(TotpVerifier::class)->currentCode($rahasia)])
            ->assertSessionHas('recovery_codes');
        $pemulihan = session('recovery_codes');
        $this->assertTrue($sa->refresh()->hasTwoFactorEnabled());
        $this->assertTrue(PlatformAuditLog::query()->where('description', 'like', 'Verifikasi dua langkah diaktifkan%')->exists());

        // Keluar, lalu masuk: password benar belum cukup.
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->post($this->centralUrl('admin/login'), ['email' => 'sa@wms.test', 'password' => 'Rahasia#2026!'])->assertRedirect($this->centralUrl('admin/two-factor'));
        $this->assertGuest('platform');
        $this->get($this->centralUrl('admin/two-factor'))->assertOk()->assertSee(__('Verifikasi dua langkah'));

        $this->post($this->centralUrl('admin/two-factor'), ['code' => '12345'])->assertSessionHasErrors('code');
        $this->assertGuest('platform');
        $this->assertSame(1, $sa->fresh()->failed_login_count, 'Kode salah ikut hitungan kunci akun.');

        // Kode pemulihan sekali pakai (huruf kecil tetap diterima).
        $this->post($this->centralUrl('admin/two-factor'), ['code' => mb_strtolower($pemulihan[0])])->assertRedirect($this->centralUrl('admin'));
        $this->assertAuthenticatedAs($sa->fresh(), 'platform');
        $this->assertSame(0, $sa->fresh()->failed_login_count);

        // Kode TOTP tidak bisa dipakai ulang di jendelanya (A-205).
        $masukDenganKode = function (string $kode) {
            $this->app['auth']->forgetGuards();
            $this->flushSession();
            $this->post($this->centralUrl('admin/login'), ['email' => 'sa@wms.test', 'password' => 'Rahasia#2026!']);

            return $this->post($this->centralUrl('admin/two-factor'), ['code' => $kode]);
        };
        // Kode konfirmasi sudah terpakai; pakai kode langkah berikutnya (masih dalam jendela).
        $kodeKini = app(TotpVerifier::class)->currentCode($rahasia, time() + 30);
        $masukDenganKode($kodeKini)->assertRedirect($this->centralUrl('admin'));
        $masukDenganKode($kodeKini)->assertSessionHasErrors('code');
        $this->assertGuest('platform');
        $this->assertSame(count($pemulihan) - 1, app(ManageTwoFactor::class)->remainingRecoveryCodes($sa->fresh()));
    }

    #[Test]
    public function tc_plt_09_paket_dibuat_diubah_dan_dinonaktifkan(): void
    {
        $sa = $this->superAdmin();
        $this->get($this->centralUrl('admin/plans'))->assertRedirect();

        $this->actingAs($sa, 'platform')->post($this->centralUrl('admin/plans'), ['code' => 'pro', 'name' => '', 'monthly_price' => 750000, 'trial_days' => 30])
            ->assertSessionHasErrors('name');
        $this->actingAs($sa, 'platform')->post($this->centralUrl('admin/plans'), ['code' => 'Pro', 'name' => 'Profesional', 'monthly_price' => 750000, 'trial_days' => 30, 'is_active' => 1])
            ->assertRedirect($this->centralUrl('admin/plans'));

        $plan = Plan::query()->where('code', 'pro')->sole();
        $this->assertSame(30, $plan->trial_days);

        $this->actingAs($sa, 'platform')->post($this->centralUrl('admin/plans/'.$plan->id), ['code' => 'pro', 'name' => 'Profesional', 'monthly_price' => 800000, 'trial_days' => 14])
            ->assertRedirect();
        $plan->refresh();
        $this->assertSame(800000.0, (float) $plan->monthly_price);
        $this->assertFalse($plan->is_active, 'Kotak Aktif tidak dicentang = nonaktif; paket tidak dihapus (P-03).');
        $this->actingAs($sa, 'platform')->get($this->centralUrl('admin/plans'))->assertOk()->assertSee('Profesional')->assertSee('800.000');
        $this->actingAs($sa, 'platform')->get($this->centralUrl('admin/companies/create'))->assertOk()->assertDontSee('Profesional');
    }

    #[Test]
    public function tc_plt_10_penangguhan_manual_dan_flag_fitur(): void
    {
        $sa = $this->superAdmin();
        $url = $this->centralUrl('admin/companies/'.$this->company->id);

        $this->actingAs($sa, 'platform')->get($url)->assertOk()->assertSee($this->company->name);
        $this->actingAs($sa, 'platform')->post($url.'/suspend', ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($sa, 'platform')->post($url.'/suspend', ['reason' => 'Pelanggaran ketentuan'])->assertSessionHasNoErrors();
        $this->assertSame(CompanyStatus::Suspended, $this->company->refresh()->status);
        $this->assertSame('Pelanggaran ketentuan', $this->company->getAttribute('status_reason'));

        $staf = $this->makeUser('warehouse_staff');
        $this->actingAs($staf)->put($this->tenantUrl('/profile'), ['name' => 'X'])->assertForbidden();

        $this->actingAs($sa, 'platform')->post($url.'/reactivate')->assertSessionHasNoErrors();
        $this->assertSame(CompanyStatus::Active, $this->company->refresh()->status);
        $this->actingAs($sa, 'platform')->post($url.'/reactivate')->assertSessionHasErrors('platform');

        $this->actingAs($sa, 'platform')->post($url.'/flags', ['key' => 'whatsapp', 'enabled' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($this->company->refresh()->isFeatureEnabled('whatsapp'));
        $this->actingAs($sa, 'platform')->post($url.'/flags', ['key' => 'whatsapp', 'enabled' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($this->company->isFeatureEnabled('whatsapp'));
        $this->actingAs($sa, 'platform')->post($url.'/flags', ['key' => 'teleport', 'enabled' => 1])->assertSessionHasErrors('platform');
        $this->assertSame(1, FeatureFlag::query()->where('company_id', $this->company->id)->count());
    }

    #[Test]
    public function tc_plt_11_akses_dukungan_membuka_sesi_hanya_baca_sampai_dicabut(): void
    {
        $sa = $this->superAdmin();
        $lain = $this->superAdmin('lain@wms.test');
        $admin = $this->makeUser('company_admin');
        $url = $this->centralUrl('admin/companies/'.$this->company->id.'/support');

        // Tanpa izin: ditolak (BR-SUB-04).
        $this->actingAs($sa, 'platform')->post($url)->assertSessionHasErrors('platform');

        $akses = app(GrantSupportAccess::class)->handle($this->company, $sa->id, now()->subMinute(), now()->addDay(), 'Bantu cek saldo', $admin);

        // Izin milik Super Admin lain tidak bisa dipakai.
        $this->actingAs($lain, 'platform')->post($url)->assertSessionHasErrors('platform');

        $tautan = $this->actingAs($sa, 'platform')->post($url)->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith($this->tenantUrl('support/enter/'.$akses->id), $tautan);

        // Permintaan berikutnya datang ke subdomain company (guard `web`), bukan ke pusat.
        $this->app['auth']->shouldUse('web');

        // Tanda tangan dirusak → 403; asli → halaman konfirmasi, POST membuka sesi.
        $this->get($tautan.'x')->assertForbidden();
        $this->get($tautan)->assertOk()->assertSee(__('Masuk sebagai dukungan'));
        $this->post($tautan)->assertRedirect($this->tenantUrl('/'));
        $this->assertAuthenticatedAs($admin->fresh(), 'web');

        // Tautan sekali pakai: dipakai ulang ditolak.
        $this->post($tautan)->assertForbidden();

        $sesi = [StartSupportSession::SESSION_KEY => $akses->id, StartSupportSession::SESSION_ADMIN => $sa->name];
        $this->withSession($sesi)->get($this->tenantUrl('/'))->assertOk()->assertSee(__('Mode akses dukungan (hanya-baca)', []), false);
        $this->withSession($sesi)->put($this->tenantUrl('/profile'), ['name' => 'Diubah dukungan'])->assertForbidden();
        $this->assertNotSame('Diubah dukungan', $admin->fresh()->name);

        // Dicabut → sesi gugur.
        app(GrantSupportAccess::class)->revoke($akses, $admin);
        $this->withSession($sesi)->get($this->tenantUrl('/'))->assertForbidden();
        $this->assertGuest('web');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Support\TotpVerifier;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-ACC-09 — verifikasi dua langkah opsional (Blueprint §13).
 */
class TwoFactorTest extends TenantTestCase
{
    private const PASSWORD = 'Rahasia#2026!';

    #[Test]
    public function tc_acc_09_login_dengan_2fa_meminta_kode_dan_menolak_kode_salah(): void
    {
        config(['access.login.throttle_per_minute' => 100]);

        $totp = app(TotpVerifier::class);
        $secret = $totp->generateSecret();

        $user = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1, [
            'password' => Hash::make(self::PASSWORD),
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        // Password benar tetapi belum masuk: diarahkan ke halaman 2FA.
        $this->post($this->tenantUrl('/login'), [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertRedirect($this->tenantUrl('/two-factor'));

        $this->assertGuest();

        $this->get($this->tenantUrl('/two-factor'))
            ->assertOk()
            ->assertSee('Verifikasi dua langkah');

        // Kode salah ditolak.
        $this->post($this->tenantUrl('/two-factor'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();

        // Kode benar menyelesaikan login.
        $kode = $totp->currentCode($secret);
        $this->post($this->tenantUrl('/two-factor'), ['code' => $kode])
            ->assertRedirect($this->tenantUrl('/'));

        $this->assertAuthenticatedAs($user->fresh());

        // A-205: kode yang sama tidak bisa dipakai ulang di jendelanya.
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->post($this->tenantUrl('/login'), ['email' => $user->email, 'password' => self::PASSWORD]);
        $this->post($this->tenantUrl('/two-factor'), ['code' => $kode])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    #[Test]
    public function tc_acc_09b_kode_pemulihan_bisa_dipakai_sekali(): void
    {
        config(['access.login.throttle_per_minute' => 100]);

        $totp = app(TotpVerifier::class);
        $secret = $totp->generateSecret();
        $recovery = ['kode-pemulihan-1', 'kode-pemulihan-2'];

        $user = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1, [
            'password' => Hash::make(self::PASSWORD),
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($recovery)),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->post($this->tenantUrl('/login'), [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertRedirect($this->tenantUrl('/two-factor'));

        $this->post($this->tenantUrl('/two-factor'), ['code' => 'kode-pemulihan-1'])
            ->assertRedirect($this->tenantUrl('/'));

        $this->assertAuthenticatedAs($user->fresh());

        $sisa = json_decode(Crypt::decryptString($user->refresh()->two_factor_recovery_codes), true);
        $this->assertSame(['kode-pemulihan-2'], $sisa, 'Kode pemulihan yang dipakai harus dihapus.');
    }

    #[Test]
    public function tc_acc_09c_verifier_totp_menerima_kode_dalam_jendela_waktu(): void
    {
        $totp = new TotpVerifier;
        $secret = $totp->generateSecret();
        $now = time();

        $this->assertTrue($totp->verify($secret, $totp->currentCode($secret, $now), $now));
        // Toleransi satu periode sebelumnya (jam perangkat tidak persis sama).
        $this->assertTrue($totp->verify($secret, $totp->currentCode($secret, $now - 30), $now));
        $this->assertFalse($totp->verify($secret, '123456', $now));
    }
}

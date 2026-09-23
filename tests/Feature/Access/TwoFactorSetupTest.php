<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Actions\ManageTwoFactor;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Support\TotpVerifier;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-ACC-30 — pengaturan verifikasi dua langkah di profil (10-access §6.2).
 *
 * Sebelum ini `TwoFactorController`, route `/two-factor`, dan `TotpVerifier`
 * sudah ada tetapi tidak ada satu baris pun yang menulis `two_factor_secret`,
 * sehingga seluruh jalur 2FA adalah kode mati.
 */
class TwoFactorSetupTest extends TenantTestCase
{
    #[Test]
    public function tc_acc_30_dua_langkah_bisa_diaktifkan_lewat_profil(): void
    {
        $user = $this->makeUser('warehouse_staff');
        $aksi = app(ManageTwoFactor::class);
        $totp = app(TotpVerifier::class);

        $this->assertFalse($user->hasTwoFactorEnabled());

        $siap = $aksi->begin($user);

        $this->assertNotSame('', $siap['secret']);
        $this->assertStringContainsString('otpauth://', $siap['uri']);

        // Belum aktif sampai dikonfirmasi, supaya user tidak mengunci dirinya.
        $this->assertFalse($user->refresh()->hasTwoFactorEnabled());

        $kodePemulihan = $aksi->confirm($user, $totp->currentCode($siap['secret']));

        $this->assertCount(8, $kodePemulihan);
        $this->assertTrue($user->refresh()->hasTwoFactorEnabled());
        $this->assertSame(8, $aksi->remainingRecoveryCodes($user));

        // Rahasia dan kode pemulihan tersimpan terenkripsi.
        $this->assertNotSame($siap['secret'], $user->two_factor_secret);
        $this->assertSame($siap['secret'], Crypt::decryptString($user->two_factor_secret));
    }

    #[Test]
    public function tc_acc_30b_kode_salah_menolak_pengaktifan(): void
    {
        $user = $this->makeUser('warehouse_staff');
        $aksi = app(ManageTwoFactor::class);

        $aksi->begin($user);

        try {
            $aksi->confirm($user->refresh(), '000000');
            $this->fail('Kode salah seharusnya ditolak.');
        } catch (AccessRuleException $e) {
            $this->assertStringContainsString('tidak cocok', $e->getMessage());
        }

        $this->assertFalse($user->refresh()->hasTwoFactorEnabled());
    }

    #[Test]
    public function tc_acc_30c_mematikan_dua_langkah_menuntut_password(): void
    {
        $user = $this->makeUser('warehouse_staff');
        $user->forceFill(['password' => Hash::make('Rahasia#2026!')])->save();

        $aksi = app(ManageTwoFactor::class);
        $totp = app(TotpVerifier::class);

        $siap = $aksi->begin($user);
        $aksi->confirm($user->refresh(), $totp->currentCode($siap['secret']));

        try {
            $aksi->disable($user->refresh(), 'password-salah');
            $this->fail('Password salah seharusnya ditolak.');
        } catch (AccessRuleException $e) {
            $this->assertStringContainsString('Password salah', $e->getMessage());
        }

        $this->assertTrue($user->refresh()->hasTwoFactorEnabled());

        $aksi->disable($user->refresh(), 'Rahasia#2026!');

        $this->assertFalse($user->refresh()->hasTwoFactorEnabled());
        $this->assertNull($user->two_factor_secret);
        $this->assertSame(0, $aksi->remainingRecoveryCodes($user));
    }

    #[Test]
    public function tc_acc_30d_kode_pemulihan_bisa_diganti(): void
    {
        $user = $this->makeUser('warehouse_staff');
        $aksi = app(ManageTwoFactor::class);
        $totp = app(TotpVerifier::class);

        $siap = $aksi->begin($user);
        $lama = $aksi->confirm($user->refresh(), $totp->currentCode($siap['secret']));

        $baru = $aksi->regenerateRecoveryCodes($user->refresh());

        $this->assertCount(8, $baru);
        $this->assertSame([], array_intersect($lama, $baru), 'Kode lama tidak boleh muncul lagi.');
    }

    #[Test]
    public function tc_acc_30e_layar_profil_menampilkan_pengaturan_dua_langkah(): void
    {
        $user = $this->makeUser('warehouse_staff');

        $this->actingAs($user)
            ->get($this->tenantUrl('profile'))
            ->assertOk()
            ->assertSee('Verifikasi dua langkah')
            ->assertSee('Atur dua langkah');

        // Menyiapkan rahasia lewat HTTP menaruh kode QR di sesi.
        $this->actingAs($user)
            ->post($this->tenantUrl('profile/two-factor'))
            ->assertRedirect();

        $this->assertNotNull($user->refresh()->two_factor_secret);
        $this->assertNotNull(session('access.2fa.setup_qr'));
        $this->assertStringContainsString('<svg', (string) session('access.2fa.setup_qr'));
    }

    #[Test]
    public function tc_acc_30f_pengaturan_yang_belum_selesai_bisa_dibatalkan(): void
    {
        $user = $this->makeUser('warehouse_staff');
        $aksi = app(ManageTwoFactor::class);

        $aksi->begin($user);

        $this->assertNotNull($user->refresh()->two_factor_secret);

        $aksi->cancel($user->refresh());

        $this->assertNull($user->refresh()->two_factor_secret);
        $this->assertFalse($user->hasTwoFactorEnabled());
    }
}

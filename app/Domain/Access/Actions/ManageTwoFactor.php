<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\TotpVerifier;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\Platform\Support\PlatformAudit;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Permission: `profile.update` — menyalakan dan mematikan verifikasi dua
 * langkah untuk diri sendiri (10-access §6.2, NFR-04).
 *
 * Sebelum ini seluruh jalur 2FA adalah kode mati: `TwoFactorController`, route
 * `/two-factor`, dan `TotpVerifier` sudah ada, tetapi tidak ada satu baris pun
 * yang pernah menulis `two_factor_secret`, sehingga `hasTwoFactorEnabled()`
 * selalu false.
 *
 * Rahasia dan kode pemulihan disimpan terenkripsi; keduanya tidak pernah
 * ditampilkan lagi setelah dikonfirmasi.
 *
 * Dipakai juga untuk Super Admin (`PlatformUser`, A-200); jejaknya ke audit log pusat.
 */
class ManageTwoFactor
{
    private const JUMLAH_KODE_PEMULIHAN = 8;

    public function __construct(private readonly TotpVerifier $totp) {}

    /**
     * Langkah 1 — menyiapkan rahasia baru. Belum aktif sampai dikonfirmasi
     * dengan kode yang benar, supaya user tidak mengunci dirinya sendiri.
     *
     * @return array{secret: string, uri: string}
     */
    public function begin(User|PlatformUser $user): array
    {
        if ($user->hasTwoFactorEnabled()) {
            throw new AccessRuleException('Verifikasi dua langkah sudah aktif.');
        }

        $secret = $this->totp->generateSecret();

        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        return [
            'secret' => $secret,
            'uri' => $this->totp->otpauthUri($secret, $user->email, (string) (tenant()?->name ?? config('app.name'))),
        ];
    }

    /**
     * Langkah 2 — konfirmasi dengan kode dari aplikasi autentikator.
     * Mengembalikan kode pemulihan sekali pakai yang hanya tampil sekali ini.
     *
     * @return array<int, string>
     */
    public function confirm(User|PlatformUser $user, string $code): array
    {
        if ($user->two_factor_secret === null) {
            throw new AccessRuleException('Mulai pengaturan dua langkah dulu.');
        }

        if ($user->two_factor_confirmed_at !== null) {
            throw new AccessRuleException('Verifikasi dua langkah sudah aktif.');
        }

        $secret = Crypt::decryptString($user->two_factor_secret);

        $langkah = $this->totp->matchedStep($secret, trim($code));

        if ($langkah === null) {
            throw new AccessRuleException('Kode verifikasi tidak cocok. Periksa jam perangkat Anda.');
        }

        $kode = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            // Kode konfirmasi tidak bisa dipakai lagi untuk masuk (A-205).
            'two_factor_last_step' => $langkah,
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($kode)),
        ])->save();

        $this->catat($user, 'Verifikasi dua langkah diaktifkan');

        return $kode;
    }

    /** Membatalkan pengaturan yang belum dikonfirmasi. */
    public function cancel(User|PlatformUser $user): void
    {
        if ($user->two_factor_confirmed_at !== null) {
            throw new AccessRuleException('Verifikasi dua langkah sudah aktif; matikan lewat aksi tersendiri.');
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ])->save();
    }

    /** Mematikan 2FA menuntut password, bukan sekadar sesi yang sedang terbuka. */
    public function disable(User|PlatformUser $user, string $currentPassword): void
    {
        if (! $user->hasTwoFactorEnabled()) {
            throw new AccessRuleException('Verifikasi dua langkah belum aktif.');
        }

        if (! Hash::check($currentPassword, (string) $user->password)) {
            throw new AccessRuleException('Password salah.');
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();

        $this->catat($user, 'Verifikasi dua langkah dimatikan');
    }

    /**
     * Mengganti seluruh kode pemulihan; yang lama langsung tidak berlaku.
     *
     * @return array<int, string>
     */
    public function regenerateRecoveryCodes(User|PlatformUser $user): array
    {
        if (! $user->hasTwoFactorEnabled()) {
            throw new AccessRuleException('Verifikasi dua langkah belum aktif.');
        }

        $kode = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($kode)),
        ])->save();

        $this->catat($user, 'Kode pemulihan dua langkah diganti');

        return $kode;
    }

    /** Sisa kode pemulihan yang belum terpakai. */
    public function remainingRecoveryCodes(User|PlatformUser $user): int
    {
        if ($user->two_factor_recovery_codes === null) {
            return 0;
        }

        $kode = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true);

        return is_array($kode) ? count($kode) : 0;
    }

    /** Kode saat masuk: TOTP, atau kode pemulihan yang langsung dihabiskan. */
    public function verifyLogin(User|PlatformUser $user, string $code): bool
    {
        if (! $user->hasTwoFactorEnabled()) {
            return false;
        }

        $code = trim($code);

        // Baris user dikunci: kode TOTP tidak bisa dipakai ulang di jendelanya
        // (A-205) dan kode pemulihan tidak terpakai dua kali oleh permintaan bersamaan.
        return $user->getConnection()->transaction(function () use ($user, $code): bool {
            $segar = $user->newQuery()->lockForUpdate()->find($user->getKey());

            if ($segar === null) {
                return false;
            }

            $langkah = $this->totp->matchedStep(Crypt::decryptString($segar->two_factor_secret), $code);

            if ($langkah !== null) {
                if ($segar->two_factor_last_step !== null && $langkah <= (int) $segar->two_factor_last_step) {
                    return false;
                }

                $segar->forceFill(['two_factor_last_step' => $langkah])->save();
                $user->setRawAttributes($segar->getAttributes(), true);

                return true;
            }

            // Kode pemulihan tidak peka huruf besar-kecil.
            $kode = $segar->two_factor_recovery_codes === null ? [] : (json_decode(Crypt::decryptString($segar->two_factor_recovery_codes), true) ?: []);
            $indeks = array_search(mb_strtoupper($code), array_map(fn ($k) => mb_strtoupper((string) $k), $kode), true);

            if ($indeks === false) {
                return false;
            }

            unset($kode[$indeks]);
            $segar->forceFill(['two_factor_recovery_codes' => Crypt::encryptString(json_encode(array_values($kode)))])->save();
            $user->setRawAttributes($segar->getAttributes(), true);

            return true;
        });
    }

    private function catat(User|PlatformUser $user, string $pesan): void
    {
        if ($user instanceof PlatformUser) {
            PlatformAudit::record($pesan.' (Super Admin)', $user, $user);

            return;
        }

        activity('access')->performedOn($user)->causedBy($user)->log($pesan);
    }

    /** @return array<int, string> */
    private function generateRecoveryCodes(): array
    {
        $jumlah = (int) config('access.two_factor.recovery_codes', self::JUMLAH_KODE_PEMULIHAN);
        $kode = [];

        for ($i = 0; $i < max(1, $jumlah); $i++) {
            // Dipisah strip agar mudah disalin dan dibaca ulang.
            $kode[] = Str::upper(Str::random(5).'-'.Str::random(5));
        }

        return $kode;
    }
}

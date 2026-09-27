<?php

declare(strict_types=1);

namespace App\Domain\Access\Support;

use App\Domain\Access\Models\User;
use Illuminate\Contracts\Session\Session;

/**
 * Pembaca sesi "Masuk sebagai" (A-260). Admin Company asli disimpan di sesi
 * selama ia bekerja atas nama user lain; kelas ini dipakai spanduk, header,
 * pencatat audit, dan aksi ImpersonateUser.
 */
class Impersonation
{
    public const SESSION_IMPERSONATOR_ID = 'impersonator_id';

    public const SESSION_IMPERSONATOR_NAME = 'impersonator_name';

    /** Sakelar fitur (A-260): bawaan menyala di luar production. */
    public static function enabled(): bool
    {
        return (bool) config('access.impersonation.enabled');
    }

    public static function active(): bool
    {
        $session = self::session();

        return $session !== null && $session->has(self::SESSION_IMPERSONATOR_ID);
    }

    public static function impersonatorId(): ?int
    {
        return self::active() ? (int) self::session()->get(self::SESSION_IMPERSONATOR_ID) : null;
    }

    public static function impersonatorName(): ?string
    {
        return self::active() ? (string) self::session()->get(self::SESSION_IMPERSONATOR_NAME) : null;
    }

    /** Admin asli yang sedang bekerja atas nama user lain. */
    public static function impersonator(): ?User
    {
        $id = self::impersonatorId();

        return $id === null ? null : User::query()->find($id);
    }

    /**
     * Admin asli (bila sedang impersonasi) atau user yang login — izin
     * `user.impersonate` selalu diperiksa terhadap orang ini.
     */
    public static function actor(): ?User
    {
        $actor = self::impersonator() ?? auth()->user();

        return $actor instanceof User ? $actor : null;
    }

    /** Pemegang `user.impersonate` yang masih aktif. */
    public static function allowed(): bool
    {
        $actor = self::actor();

        return $actor !== null && $actor->is_active && $actor->hasPermission('user.impersonate');
    }

    private static function session(): ?Session
    {
        $request = request();

        return $request->hasSession() ? $request->session() : null;
    }
}

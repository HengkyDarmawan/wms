<?php

declare(strict_types=1);

namespace App\Domain\Access\Support;

use App\Domain\Access\Models\User;

/**
 * A-418 — **Coba demo**: kartu di halaman login/halaman depan untuk masuk
 * sekali klik sebagai pengguna AKTIF pertama per peran. Hanya bila
 * `WMS_DEMO_LOGIN=true` (bawaan mati); peran tanpa pengguna aktif tidak tampil.
 */
final class DemoLogin
{
    /** @var array<string, string> kode peran => label */
    public const PERAN = [
        'company_admin' => 'Admin Company',
        'warehouse_head' => 'Kepala Gudang',
        'warehouse_staff' => 'Staf Gudang',
        'internal_requester' => 'Pemohon Internal',
    ];

    public static function enabled(): bool
    {
        return (bool) config('access.demo_login', false);
    }

    /** Pengguna internal aktif pertama (tidak terkunci) yang memegang peran itu dengan penugasan berlaku. */
    public static function pengguna(string $peran): ?User
    {
        if (! array_key_exists($peran, self::PERAN)) {
            return null;
        }

        return User::query()->where('is_active', true)->whereNull('client_id')->orderBy('id')->get()
            ->first(fn (User $u) => ! $u->isLocked() && $u->hasRoleCode($peran));
    }

    /** @return array<int, array{peran: string, label: string, nama: string}> */
    public static function pilihan(): array
    {
        if (! self::enabled()) {
            return [];
        }

        $hasil = [];

        foreach (self::PERAN as $peran => $label) {
            if (($u = self::pengguna($peran)) !== null) {
                $hasil[] = ['peran' => $peran, 'label' => $label, 'nama' => (string) $u->name];
            }
        }

        return $hasil;
    }
}

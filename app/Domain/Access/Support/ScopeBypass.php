<?php

declare(strict_types=1);

namespace App\Domain\Access\Support;

/**
 * Menjalankan satu aksi tanpa pembatasan cakupan `ScopedToUser` (BR-ACC-05).
 *
 * Hanya untuk aksi yang sudah diotorisasi policy dan harus menyentuh data di
 * luar cakupan pelakunya — mis. penerima di gudang tujuan mengisi bukti terima
 * (A-312): aksinya membaca gudang, bin, dan PCK gudang asal. Halaman penerima
 * bertoken sudah berjalan tanpa user, jadi perilakunya sama.
 */
final class ScopeBypass
{
    private static int $kedalaman = 0;

    /**
     * @template T
     *
     * @param  callable(): T  $aksi
     * @return T
     */
    public static function run(callable $aksi): mixed
    {
        self::$kedalaman++;

        try {
            return $aksi();
        } finally {
            self::$kedalaman--;
        }
    }

    public static function active(): bool
    {
        return self::$kedalaman > 0;
    }
}

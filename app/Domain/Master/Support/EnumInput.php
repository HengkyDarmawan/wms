<?php

declare(strict_types=1);

namespace App\Domain\Master\Support;

use App\Domain\Master\Exceptions\MasterRuleException;
use BackedEnum;

/**
 * Mengubah masukan pengguna menjadi enum tanpa meledak.
 *
 * `Enum::from()` melempar `ValueError` yang tidak tertangkap di mana pun, jadi
 * satu nilai `<select>` yang diubah dari browser cukup untuk memunculkan galat
 * 500. Di sini nilai yang tidak dikenal menjadi pelanggaran aturan biasa yang
 * tampil sebagai pesan di field-nya.
 */
class EnumInput
{
    /**
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enum
     * @param  T|null  $default  dipakai bila masukan kosong
     * @return T
     */
    public static function required(string $enum, mixed $value, ?BackedEnum $default, string $field): BackedEnum
    {
        $hasil = self::optional($enum, $value, $field) ?? $default;

        if ($hasil === null) {
            throw MasterRuleException::fields([$field => 'Pilihan wajib diisi.'], 'BR-GEN-11');
        }

        return $hasil;
    }

    /**
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T|null
     */
    public static function optional(string $enum, mixed $value, string $field): ?BackedEnum
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof $enum) {
            return $value;
        }

        if ($value instanceof BackedEnum) {
            throw MasterRuleException::fields([$field => 'Pilihan tidak dikenal.'], 'AD-14');
        }

        if (! is_string($value) && ! is_int($value)) {
            throw MasterRuleException::fields([$field => 'Pilihan tidak dikenal.'], 'AD-14');
        }

        $hasil = $enum::tryFrom(is_int($value) ? $value : (string) $value);

        if ($hasil === null) {
            throw MasterRuleException::fields([$field => 'Pilihan tidak dikenal.'], 'AD-14');
        }

        return $hasil;
    }
}

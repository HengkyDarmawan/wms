<?php

declare(strict_types=1);

namespace App\Domain\Master\Support;

use App\Domain\Master\Exceptions\MasterRuleException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * BR-MST-01: kode master huruf besar, tanpa spasi, unik per company, dan tidak
 * bisa diubah setelah baris dibuat.
 */
class MasterCode
{
    /** Membakukan kode: huruf besar, spasi jadi strip, karakter lain dibuang. */
    public static function normalize(string $code): string
    {
        return Str::of($code)
            ->trim()
            ->upper()
            ->replaceMatches('/\s+/', '-')
            ->replaceMatches('/[^A-Z0-9._\-]/', '')
            ->value();
    }

    /**
     * Mengembalikan kode baku untuk disimpan. Pada baris yang sudah ada, kode
     * lama dipertahankan dan perubahan ditolak.
     *
     * @param  string  $column  nama kolom kode pada tabel tersebut
     */
    public static function resolve(?Model $existing, string $input, string $label, string $column = 'code'): string
    {
        $kode = self::normalize($input);

        if ($existing !== null && $existing->exists) {
            $lama = (string) $existing->getAttribute($column);

            if ($kode !== '' && $kode !== $lama) {
                throw MasterRuleException::rule('BR-MST-01', 'Kode '.$label.' tidak bisa diubah setelah dibuat.');
            }

            return $lama;
        }

        if ($kode === '') {
            throw MasterRuleException::rule('BR-GEN-11', 'Kode '.$label.' wajib diisi.');
        }

        return $kode;
    }
}

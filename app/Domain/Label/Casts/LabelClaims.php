<?php

declare(strict_types=1);

namespace App\Domain\Label\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Klaim label kemasan di baris PCK/ISU (A-299): selalu `list<array{id: int,
 * qty: float}>` — satu tempat penyeragam tipe, karena JSON dan Livewire
 * mengubah 12.0 menjadi 12. Kosong disimpan null.
 *
 * @implements CastsAttributes<list<array{id: int, qty: float}>|null, mixed>
 */
class LabelClaims implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        $isi = is_string($value) ? json_decode($value, true) : $value;

        return is_array($isi) && $isi !== [] ? self::normalize($isi) : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        $isi = is_array($value) ? self::normalize($value) : [];

        return $isi === [] ? null : json_encode($isi, JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * @param  array<int, mixed>  $claims
     * @return list<array{id: int, qty: float}>
     */
    public static function normalize(array $claims): array
    {
        $hasil = [];

        foreach ($claims as $c) {
            if (is_array($c) && is_numeric($c['id'] ?? null) && is_numeric($c['qty'] ?? null)) {
                $hasil[] = ['id' => (int) $c['id'], 'qty' => round((float) $c['qty'], 4)];
            }
        }

        return $hasil;
    }
}

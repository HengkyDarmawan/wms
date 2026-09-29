<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Support;

use App\Domain\Master\Models\Vendor;
use App\Domain\Shared\Pilihan\Pilihan;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pilihan vendor untuk layar Pembelian (A-393): query daftar lama tiap layar
 * dipakai apa adanya (tanpa diperluas), dicari ke server lewat kode & nama.
 * Opsi = nama, jenis vendor sebagai badge, kode sebagai teks kecil. Tanpa
 * harga (D-07).
 */
final class PilihanVendor
{
    /** @param  Builder<Vendor>  $query */
    public static function dari(Builder $query, bool $denganJenis = true): Pilihan
    {
        return Pilihan::dari($query, ['code', 'name'], fn (Vendor $v) => [
            'value' => (int) $v->id,
            'text' => $v->name,
            'badge' => $denganJenis ? $v->vendor_type?->label() : null,
            'sub' => $v->code,
        ]);
    }
}

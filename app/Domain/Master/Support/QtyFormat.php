<?php

declare(strict_types=1);

namespace App\Domain\Master\Support;

use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\ItemUomConversion;

/**
 * Format jumlah yang sama di layar dan cetakan (A-293): angka Indonesia tanpa
 * nol di belakang koma, dan uraian kemasan "9 DUS 8 BOX" dari jumlah satuan
 * dasar. Stok selalu disimpan dalam satuan dasar; kemasan hanya tampilan.
 */
class QtyFormat
{
    public static function number(mixed $value, bool $signed = false): string
    {
        $angka = (float) $value;
        $teks = rtrim(rtrim(number_format(abs($angka), 4, ',', '.'), '0'), ',');

        if ($signed && $angka > 0) {
            return '+'.$teks;
        }

        return ($angka < 0 ? '−' : '').$teks;
    }

    public static function withUnit(mixed $value, ?string $unit): string
    {
        return trim(self::number($value).' '.(string) $unit);
    }

    /**
     * Uraian kemasan secara serakah dari kemasan terbesar, sisanya dalam
     * satuan dasar: 116 BOX dengan DUS = 12 → "9 DUS 8 BOX". `null` bila item
     * tidak punya kemasan, bila jumlahnya kurang dari satu kemasan terkecil,
     * atau item per potong (panjang tidak diuraikan).
     *
     * Butuh relasi `activeConversions.uom` dan `baseUom` (dimuat bila belum).
     */
    public static function packaging(?Item $item, mixed $qtyBase): ?string
    {
        if ($item === null || $item->tracksPiece()) {
            return null;
        }

        $kemasan = $item->activeConversions
            ->filter(fn (ItemUomConversion $k) => ! $k->is_nominal_piece
                && (int) $k->uom_id !== (int) $item->base_uom_id
                && (float) $k->qty_base > 1)
            ->sortByDesc(fn (ItemUomConversion $k) => (float) $k->qty_base);

        $sisa = abs((float) $qtyBase);
        $bagian = [];

        foreach ($kemasan as $k) {
            $faktor = (float) $k->qty_base;
            $n = (int) floor($sisa / $faktor + 1e-6);

            if ($n > 0) {
                $bagian[] = self::number($n).' '.$k->uom?->code;
                $sisa = round($sisa - $n * $faktor, 4);
            }
        }

        if ($bagian === []) {
            return null;
        }

        if ($sisa > 0.00005) {
            $bagian[] = self::number($sisa).' '.$item->baseUom?->code;
        }

        return ((float) $qtyBase < 0 ? '−' : '').implode(' ', $bagian);
    }
}

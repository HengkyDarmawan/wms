<?php

declare(strict_types=1);

namespace App\Domain\Template\Support;

/** Format angka dan identitas barang yang sama di semua cetakan. */
class PrintFormat
{
    /** Jumlah dengan pemisah Indonesia, tanpa nol di belakang koma. */
    public static function qty(mixed $value, bool $signed = false): string
    {
        $angka = (float) $value;
        $teks = rtrim(rtrim(number_format(abs($angka), 4, ',', '.'), '0'), ',');

        if ($signed && $angka > 0) {
            return '+'.$teks;
        }

        return ($angka < 0 ? '−' : '').$teks;
    }

    /** "Lot L-01", "SN 123", atau "P-000123 (6 m)" untuk baris berpelacakan. */
    public static function tracking(?object $lot, ?object $serial, ?object $piece): string
    {
        return implode(' · ', array_filter([
            $lot ? __('Lot').' '.$lot->lot_no : null,
            $serial ? __('SN').' '.$serial->serial_no : null,
            $piece ? $piece->piece_no.' ('.self::qty($piece->length).')' : null,
        ]));
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Template\Support;

use App\Domain\Master\Models\Item;
use App\Domain\Master\Support\QtyFormat;

/** Format angka dan identitas barang yang sama di semua cetakan. */
class PrintFormat
{
    /** Jumlah dengan pemisah Indonesia, tanpa nol di belakang koma. */
    public static function qty(mixed $value, bool $signed = false): string
    {
        return QtyFormat::number($value, $signed);
    }

    /** A-293: uraian kemasan "9 DUS 8 BOX" di samping satuan dasar, atau null. */
    public static function kemasan(?Item $item, mixed $qtyBase): ?string
    {
        return QtyFormat::packaging($item, $qtyBase);
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

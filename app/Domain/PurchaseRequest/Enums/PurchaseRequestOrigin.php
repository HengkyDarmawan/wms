<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Enums;

/** Katalog §3 `purchase_request_origin` — asal PRQ (BR-REQ-05, BR-REQ-11). */
enum PurchaseRequestOrigin: string
{
    case Backorder = 'backorder';
    case Manual = 'manual';
    case ReorderPoint = 'reorder_point';

    public function label(): string
    {
        return match ($this) {
            self::Backorder => 'Dari backorder REQ',
            self::Manual => 'Manual',
            self::ReorderPoint => 'Titik pesan ulang',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $hasil = [];

        foreach (self::cases() as $case) {
            $hasil[$case->value] = $case->label();
        }

        return $hasil;
    }
}

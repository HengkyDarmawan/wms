<?php

declare(strict_types=1);

namespace App\Domain\Return\Enums;

/**
 * Katalog Status §3 `return_ownership` — penanda kepemilikan baris retur
 * (ERD `goods_return_lines.ownership`, BR-RET-03, A-26).
 *
 * `sold` = barang jual-putus yang sudah diterima klien (retur penjualan di
 * Akuntansi); `company` = stok company dari Gudang Site, aset, atau barang
 * rusak yang ditinggal ekspedisi.
 */
enum ReturnOwnership: string
{
    case Sold = 'sold';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::Sold => 'Jual putus (retur penjualan)',
            self::Company => 'Milik company',
        };
    }
}

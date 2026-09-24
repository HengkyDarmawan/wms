<?php

declare(strict_types=1);

namespace App\Domain\Return\Enums;

/**
 * Dari mana barang retur berasal — **turunan** dari kolom baris, bukan kolom
 * sendiri (A-110). Labelnya mengikuti tiga sub-tampilan Stok On-site
 * (BR-PRJ-05) ditambah barang rusak yang ditinggal ekspedisi (BR-SJ-10).
 */
enum ReturnSource: string
{
    case SiteStock = 'site_stock';
    case OnSiteAsset = 'on_site_asset';
    case DeliveredToClient = 'delivered_to_client';
    case CarrierLeftDamaged = 'carrier_left_damaged';

    public function label(): string
    {
        return match ($this) {
            self::SiteStock => 'Di Gudang Site',
            self::OnSiteAsset => 'Aset di Proyek',
            self::DeliveredToClient => 'Terkirim ke Klien',
            self::CarrierLeftDamaged => 'Rusak ditinggal ekspedisi',
        };
    }

    /** Barang ada di bin company (Gudang Site atau On-site) dan keluar dari sana saat GRN retur. */
    public function leavesCompanyBin(): bool
    {
        return $this === self::SiteStock || $this === self::OnSiteAsset;
    }

    /** BR-RET-05: yang boleh diajukan Klien. */
    public function allowedForClient(): bool
    {
        return $this !== self::SiteStock;
    }
}

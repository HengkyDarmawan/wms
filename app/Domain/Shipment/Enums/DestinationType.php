<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Enums;

/** BR-SJ-04 — tujuan menentukan apa yang terjadi pada stok saat barang diterima. */
enum DestinationType: string
{
    case ProjectClient = 'project_client';
    case SiteWarehouse = 'site_warehouse';
    case Warehouse = 'warehouse';
    case Vendor = 'vendor';

    public function label(): string
    {
        return match ($this) {
            self::ProjectClient => 'Proyek klien',
            self::SiteWarehouse => 'Gudang Site',
            self::Warehouse => 'Gudang lain',
            self::Vendor => 'Vendor',
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

    /**
     * Tujuan gudang: barang tetap milik gudang asal sampai GRN tujuan mencatat
     * penerimaannya, jadi ia menunggu di Dalam Perjalanan (BR-SJ-04).
     */
    public function staysInTransitUntilReceipt(): bool
    {
        return $this === self::SiteWarehouse || $this === self::Warehouse;
    }

    /** @return array<string, string> kolom tujuan yang wajib diisi */
    public function requiredFields(): array
    {
        return match ($this) {
            self::ProjectClient => ['destination_project_id' => 'Proyek tujuan'],
            self::SiteWarehouse, self::Warehouse => ['destination_warehouse_id' => 'Gudang tujuan'],
            self::Vendor => ['destination_vendor_id' => 'Vendor tujuan'],
        };
    }
}

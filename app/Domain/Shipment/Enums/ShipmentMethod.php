<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Enums;

/**
 * BR-SJ-07 — cara kirim menentukan kolom apa yang wajib diisi.
 *
 * Ongkos kirim tidak dicatat WMS (D-07); yang dicatat hanya siapa membawa dan
 * dengan apa, karena itulah yang dipakai menelusuri barang saat hilang.
 */
enum ShipmentMethod: string
{
    case OwnFleet = 'own_fleet';
    case Carrier = 'carrier';
    case SelfDelivered = 'self_delivered';

    public function label(): string
    {
        return match ($this) {
            self::OwnFleet => 'Kendaraan sendiri',
            self::Carrier => 'Ekspedisi',
            self::SelfDelivered => 'Dibawa sendiri',
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
     * Kolom yang wajib diisi untuk cara kirim ini.
     *
     * @return array<string, string>  nama kolom => label untuk pesan kesalahan
     */
    public function requiredFields(): array
    {
        return match ($this) {
            self::OwnFleet => ['vehicle_id' => 'Kendaraan', 'driver_id' => 'Driver'],
            self::Carrier => ['carrier_id' => 'Ekspedisi', 'tracking_no' => 'Nomor resi'],
            self::SelfDelivered => ['carried_by_name' => 'Nama pembawa'],
        };
    }

    /**
     * BR-SJ-10: barang rusak wajib dibawa balik saat itu juga, kecuali dibawa
     * ekspedisi — ekspedisi tidak bisa dipaksa menunggu pemilahan.
     */
    public function damagedGoodsReturnImmediately(): bool
    {
        return $this !== self::Carrier;
    }
}

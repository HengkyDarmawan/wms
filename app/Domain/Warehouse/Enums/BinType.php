<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Enums;

/**
 * Katalog Status §3 `bin_type` (Blueprint §6.3, A-29).
 *
 * `in_transit` dan `on_site` adalah **lokasi virtual**, bukan status saldo:
 * barang yang sedang dikirim tetap berada di sebuah bin (BR-STK-02).
 */
enum BinType: string
{
    case Storage = 'storage';
    case Receiving = 'receiving';
    case Staging = 'staging';
    case Quarantine = 'quarantine';
    case Return = 'return';
    case Waste = 'waste';
    case InTransit = 'in_transit';
    case OnSite = 'on_site';

    public function label(): string
    {
        return match ($this) {
            self::Storage => 'Penyimpanan',
            self::Receiving => 'Penerimaan',
            self::Staging => 'Loading Area',
            self::Quarantine => 'Karantina QC',
            self::Return => 'Retur',
            self::Waste => 'Waste',
            self::InTransit => 'Dalam Perjalanan',
            self::OnSite => 'On-site Proyek',
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

    /** Bin virtual tidak punya rak; barang di dalamnya tidak bisa diambil langsung. */
    public function isVirtual(): bool
    {
        return $this === self::InTransit || $this === self::OnSite;
    }

    /** BR-WH-02: bin yang dibuat otomatis untuk setiap gudang baru. */
    public function isSystemDefault(): bool
    {
        return in_array($this, [
            self::Receiving,
            self::Staging,
            self::Quarantine,
            self::Return,
            self::Waste,
            self::InTransit,
        ], true);
    }

    /** BR-WH-03: hanya bin on_site yang terikat proyek. */
    public function requiresProject(): bool
    {
        return $this === self::OnSite;
    }

    /** Akhiran kode untuk bin sistem, mis. CKG-RCV. */
    public function codeSuffix(): string
    {
        return match ($this) {
            self::Receiving => 'RCV',
            self::Staging => 'STG',
            self::Quarantine => 'QC',
            self::Return => 'RET',
            self::Waste => 'WST',
            self::InTransit => 'TRANSIT',
            self::OnSite => 'ONSITE',
            self::Storage => 'STO',
        };
    }
}

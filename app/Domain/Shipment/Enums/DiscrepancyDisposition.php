<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Enums;

/** BR-SJ-10 — nasib barang yang kurang atau rusak. */
enum DiscrepancyDisposition: string
{
    case ReturnedToWarehouse = 'returned_to_warehouse';
    case Adjusted = 'adjusted';
    case Claimed = 'claimed';
    case Reship = 'reship';

    public function label(): string
    {
        return match ($this) {
            self::ReturnedToWarehouse => 'Kembali ke gudang',
            self::Adjusted => 'Disesuaikan (hilang)',
            self::Claimed => 'Diklaim ke ekspedisi',
            self::Reship => 'Kirim pengganti',
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

    /** Disposisi yang mengeluarkan barang dari pembukuan menuntut alasan. */
    public function requiresReason(): bool
    {
        return $this === self::Adjusted;
    }

    /** Klaim ekspedisi hanya bermakna bila nomor klaimnya dicatat. */
    public function requiresClaimRef(): bool
    {
        return $this === self::Claimed;
    }

    /** Barang kembali secara fisik ke bin Retur gudang asal. */
    public function returnsStock(): bool
    {
        return $this === self::ReturnedToWarehouse;
    }
}

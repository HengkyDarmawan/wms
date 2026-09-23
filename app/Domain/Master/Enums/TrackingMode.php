<?php

declare(strict_types=1);

namespace App\Domain\Master\Enums;

/**
 * Katalog Status §3 `tracking_mode` (D-13). Kombinasi yang sah dengan strategi
 * pengambilan, kedaluwarsa, dan model kepemilikan diatur
 * [matriks BR §15](../../../../docs/wms/05-aturan-bisnis.md) dan BR-STK-11.
 */
enum TrackingMode: string
{
    case None = 'none';
    case Lot = 'lot';
    case Serial = 'serial';
    case Piece = 'piece';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Tanpa pelacakan',
            self::Lot => 'Batch / Lot',
            self::Serial => 'Serial number',
            self::Piece => 'Per potong (ukuran)',
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
     * Strategi pengambilan yang sah untuk mode ini (matriks BR §15).
     *
     * @return array<int, RemovalStrategy>
     */
    public function allowedRemovalStrategies(): array
    {
        return match ($this) {
            self::None => [RemovalStrategy::Fifo, RemovalStrategy::Manual],
            self::Lot => [RemovalStrategy::Fifo, RemovalStrategy::Fefo, RemovalStrategy::Manual],
            // Serial diambil manual; FIFO hanya saran urutan.
            self::Serial => [RemovalStrategy::Manual, RemovalStrategy::Fifo],
            self::Piece => [RemovalStrategy::OffcutFirst, RemovalStrategy::Manual],
        };
    }

    /** BR-STK-12: kedaluwarsa hanya untuk lot dan serial. */
    public function allowsExpiry(): bool
    {
        return $this === self::Lot || $this === self::Serial;
    }

    /**
     * Model kepemilikan yang sah (BR-STK-08: aset wajib serial).
     *
     * @return array<int, OwnershipModel>
     */
    public function allowedOwnershipModels(): array
    {
        return $this === self::Serial
            ? [OwnershipModel::Consumable, OwnershipModel::Asset, OwnershipModel::Both]
            : [OwnershipModel::Consumable];
    }

    /** Item per potong memakai satuan dasar berkategori panjang (BR-STK-09). */
    public function requiresLengthBaseUom(): bool
    {
        return $this === self::Piece;
    }
}

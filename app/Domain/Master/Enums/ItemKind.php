<?php

declare(strict_types=1);

namespace App\Domain\Master\Enums;

use App\Domain\Master\Models\Item;

/**
 * A-283: *Jenis barang* — satu pilihan di form item yang menggantikan isian
 * teknis (mode pelacakan, model kepemilikan, sifat baris, kedaluwarsa,
 * strategi pengambilan). Nilainya **diturunkan** dari kolom item, bukan
 * kolom tersendiri: item yang kombinasinya di luar tiga jenis ini disebut
 * *Jenis khusus* dan tetap bisa dibuka (P-03).
 */
enum ItemKind: string
{
    case Standard = 'standard';
    case Expiring = 'expiring';
    case SerialTool = 'serial_tool';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Barang biasa',
            self::Expiring => 'Barang berkedaluwarsa',
            self::SerialTool => 'Alat bernomor seri',
        };
    }

    /** Teks bantuan di bawah pilihan: arti + contoh, tanpa istilah teknis. */
    public function hint(): string
    {
        return match ($this) {
            self::Standard => 'Habis dipakai atau dibeli putus oleh proyek. Memotong termasuk pemakaian; sisa yang kembali dicatat lewat retur. Contoh: paku, karet, pipa, besi.',
            self::Expiring => 'Habis dipakai dan punya tanggal kedaluwarsa. Dicatat per batch; yang paling dekat kedaluwarsa diambil lebih dulu. Contoh: semen, cat, lem.',
            self::SerialTool => 'Dipinjamkan ke proyek dan pasti kembali. Setiap unit punya nomor seri. Contoh: genset, mesin las, alat berat.',
        };
    }

    public function trackingMode(): TrackingMode
    {
        return match ($this) {
            self::Standard => TrackingMode::None,
            self::Expiring => TrackingMode::Lot,
            self::SerialTool => TrackingMode::Serial,
        };
    }

    public function ownershipModel(): OwnershipModel
    {
        return $this === self::SerialTool ? OwnershipModel::Asset : OwnershipModel::Consumable;
    }

    public function hasExpiry(): bool
    {
        return $this === self::Expiring;
    }

    /** Strategi untuk item baru; FEFO hanya bila saklar `fefo` menyala (BR-STK-12). */
    public function defaultStrategy(bool $fefoOn): RemovalStrategy
    {
        return match ($this) {
            self::Standard => RemovalStrategy::Fifo,
            self::Expiring => $fefoOn ? RemovalStrategy::Fefo : RemovalStrategy::Fifo,
            self::SerialTool => RemovalStrategy::Manual,
        };
    }

    /**
     * Saklar fitur company yang harus menyala agar jenis ini bisa dipilih (P-08 lapis 1).
     *
     * @return list<string>
     */
    public function requiredFeatures(): array
    {
        return match ($this) {
            self::Standard => [],
            self::Expiring => ['lot', 'expiry'],
            self::SerialTool => ['serial'],
        };
    }

    /** Null = Jenis khusus. Strategi pengambilan tidak ikut menentukan. */
    public static function classify(TrackingMode $mode, OwnershipModel $ownership, bool $hasExpiry): ?self
    {
        foreach (self::cases() as $jenis) {
            if ($jenis->trackingMode() === $mode
                && $jenis->ownershipModel() === $ownership
                && $jenis->hasExpiry() === $hasExpiry) {
                return $jenis;
            }
        }

        return null;
    }

    public static function fromItem(Item $item): ?self
    {
        return self::classify(
            $item->tracking_mode ?? TrackingMode::None,
            $item->ownership_model ?? OwnershipModel::Consumable,
            (bool) $item->has_expiry,
        );
    }

    /** Nilai kolom `jenis_barang` impor Excel: biasa / kedaluwarsa / alat (atau nilai kode). */
    public static function fromImport(?string $nilai): ?self
    {
        return match (mb_strtolower(trim((string) $nilai))) {
            'biasa', 'barang biasa', 'standard' => self::Standard,
            'kedaluwarsa', 'berkedaluwarsa', 'barang berkedaluwarsa', 'expiring' => self::Expiring,
            'alat', 'alat bernomor seri', 'serial_tool' => self::SerialTool,
            default => null,
        };
    }
}

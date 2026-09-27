<?php

declare(strict_types=1);

namespace App\Domain\Master\Support;

use App\Domain\Master\Enums\ItemKind;
use App\Domain\Master\Enums\RemovalStrategy;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\FeatureSetting;

/**
 * Pembacaan saklar fitur stok (P-08 lapis 1, A-284) di satu tempat, supaya
 * layar dan aksi memakai aturan yang sama.
 *
 * Mematikan saklar menyembunyikan pilihan dan layar khususnya serta menolak
 * data **baru** yang memakainya (BR-GEN-12); item dan stok yang sudah ada tetap
 * berjalan (P-03).
 */
class StockFeatures
{
    public static function on(string $key): bool
    {
        return FeatureSetting::enabled($key);
    }

    public static function piece(): bool
    {
        return self::on('piece');
    }

    public static function qc(): bool
    {
        return self::on('qc');
    }

    public static function fefo(): bool
    {
        return self::on('fefo');
    }

    public static function kindAvailable(ItemKind $jenis): bool
    {
        foreach ($jenis->requiredFeatures() as $kunci) {
            if (! self::on($kunci)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Pilihan Jenis barang di form. Jenis item yang sedang dibuka tetap tampil
     * walau saklarnya sudah dimatikan, agar item lama tetap bisa disimpan.
     *
     * @return array<string, ItemKind>
     */
    public static function kindOptions(?ItemKind $saatIni = null): array
    {
        $hasil = [];

        foreach (ItemKind::cases() as $jenis) {
            if ($jenis === $saatIni || self::kindAvailable($jenis)) {
                $hasil[$jenis->value] = $jenis;
            }
        }

        return $hasil;
    }

    /**
     * Saklar yang mati tetapi dibutuhkan kombinasi teknis ini.
     *
     * @return list<string>
     */
    public static function inactiveFor(TrackingMode $mode, bool $hasExpiry, ?RemovalStrategy $strategi): array
    {
        $butuh = match ($mode) {
            TrackingMode::Lot => ['lot'],
            TrackingMode::Serial => ['serial'],
            TrackingMode::Piece => ['piece'],
            TrackingMode::None => [],
        };

        if ($hasExpiry) {
            $butuh[] = 'expiry';
        }

        if ($strategi === RemovalStrategy::Fefo) {
            $butuh[] = 'fefo';
        }

        return array_values(array_filter($butuh, fn (string $kunci) => ! self::on($kunci)));
    }
}

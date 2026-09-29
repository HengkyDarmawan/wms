<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Support;

/**
 * Kode pendek bin (K-I, A-352) — hanya tampilan: "B001 · L5 · 01"
 * (rak · tingkat · petak). Kode lengkap tetap disimpan, dicari, dan dicetak
 * di QR (BR-WH-01).
 *
 * Awalan zona ("A · R01 · L1 · 01") hanya dipakai bila kode rak itu kembar di
 * zona lain gudang yang sama — tanpa awalan, dua petak berbeda akan terbaca
 * sama. Bin area lantai: "AB1 · Area". Bin sistem (Penerimaan, Transit, …)
 * tidak berak, jadi kode lengkapnya dipakai apa adanya.
 */
final class BinCode
{
    public const PEMISAH = ' · ';

    /** Nomor petak dari segmen bin: B01 → 01, AREA → Area, 12 → 12. */
    public static function petak(string $segmen): string
    {
        if (strtoupper($segmen) === 'AREA') {
            return 'Area';
        }

        return preg_match('/^[A-Z]+(\d+)$/i', $segmen, $m) === 1 ? $m[1] : $segmen;
    }

    /** Kode pendek dari bagian-bagiannya; `$zona` null = tanpa awalan zona. */
    public static function dari(?string $zona, string $rak, string $level, string $bin, bool $area = false): string
    {
        $bagian = $area
            ? [$zona, $rak, self::petak($bin)]
            : [$zona, $rak, $level, self::petak($bin)];

        return implode(self::PEMISAH, array_values(array_filter($bagian, fn ($s) => $s !== null && $s !== '')));
    }

    /**
     * Kode pendek dari kode lengkap `{GUDANG}-{ZONA}-{RAK}-{LEVEL}-{BIN}`.
     * Kode lain (bin sistem, on-site) dikembalikan apa adanya.
     */
    public static function pendek(string $kode, bool $denganZona = false): string
    {
        $segmen = explode('-', $kode);

        if (count($segmen) !== 5) {
            return $kode;
        }

        [, $zona, $rak, $level, $bin] = $segmen;

        return self::dari($denganZona ? $zona : null, $rak, $level, $bin, strtoupper($bin) === 'AREA');
    }
}

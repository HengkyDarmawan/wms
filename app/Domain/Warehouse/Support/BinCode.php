<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Support;

use App\Domain\Warehouse\Models\Bin;
use Illuminate\Support\Facades\DB;

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

    /**
     * Kode pendek sekumpulan bin sekaligus (K-I): awalan zona hanya bila kode
     * raknya kembar di zona lain gudang yang sama. Satu query untuk semua bin.
     *
     * @param  iterable<Bin>  $bins
     * @return array<int, string> bin_id => kode pendek
     */
    public static function pendekBanyak(iterable $bins): array
    {
        $bins = collect($bins);
        $gudang = $bins->pluck('warehouse_id')->filter()->unique()->all();
        $kembar = [];

        if ($gudang !== []) {
            DB::table('racks')->join('zones', 'zones.id', '=', 'racks.zone_id')
                ->whereIn('zones.warehouse_id', $gudang)
                ->groupBy('zones.warehouse_id', 'racks.code')
                ->havingRaw('COUNT(DISTINCT racks.zone_id) > 1')
                ->get(['zones.warehouse_id', 'racks.code'])
                ->each(function ($r) use (&$kembar): void {
                    $kembar[(int) $r->warehouse_id][mb_strtoupper((string) $r->code)] = true;
                });
        }

        $hasil = [];

        foreach ($bins as $b) {
            $segmen = explode('-', (string) $b->code);
            $rak = mb_strtoupper($segmen[2] ?? '');
            $hasil[(int) $b->id] = self::pendek((string) $b->code, isset($kembar[(int) $b->warehouse_id][$rak]));
        }

        return $hasil;
    }

    public static function pendekUntuk(Bin $bin): string
    {
        return self::pendekBanyak([$bin])[(int) $bin->id] ?? (string) $bin->code;
    }

    /**
     * Isi QR label bin (keputusan #7, A-373): **tautan** ke halaman Isi Bin
     * dengan kode lengkap, supaya kamera HP biasa langsung membukanya.
     */
    public static function tautan(string $kode): string
    {
        $jalur = '/bins/'.rawurlencode($kode);
        $company = function_exists('tenant') ? tenant() : null;

        return $company !== null && method_exists($company, 'url') ? $company->url($jalur) : url($jalur);
    }

    /**
     * Hasil pindai → kode bin (A-373): tautan Isi Bin ("…/bins/KODE") dibaca
     * kodenya; selain itu dikembalikan apa adanya, huruf besar.
     */
    public static function dariPindai(string $raw): string
    {
        $teks = trim($raw);

        if (preg_match('~/bins/([^/?#\s]+)~i', $teks, $m) === 1) {
            $teks = rawurldecode($m[1]);
        }

        return mb_strtoupper(trim($teks));
    }

    /**
     * Cari bin dari hasil pindai atau ketikan: kode lengkap, tautan QR, atau
     * kode pendek (tanpa memedulikan spasi, titik tengah, dan tanda hubung —
     * "R01 L1 01", "r01-l1-01") bila hanya cocok dengan satu bin.
     *
     * @param  iterable<Bin>  $bins
     */
    public static function cocokkan(string $raw, iterable $bins): ?Bin
    {
        $kode = self::dariPindai($raw);

        if ($kode === '') {
            return null;
        }

        $bins = collect($bins);
        $persis = $bins->first(fn (Bin $b) => mb_strtoupper((string) $b->code) === $kode);

        if ($persis !== null) {
            return $persis;
        }

        $rata = fn (string $s) => preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($s));
        $cari = $rata($kode);

        if ($cari === '') {
            return null;
        }

        $cocok = $bins->filter(fn (Bin $b) => $rata(self::pendek((string) $b->code)) === $cari
            || $rata(self::pendek((string) $b->code, true)) === $cari);

        return $cocok->count() === 1 ? $cocok->first() : null;
    }
}

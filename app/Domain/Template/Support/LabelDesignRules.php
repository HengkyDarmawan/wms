<?php

declare(strict_types=1);

namespace App\Domain\Template\Support;

use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Enums\LabelCodeMode;
use App\Domain\Template\Models\LabelFormat;

/**
 * Aturan tata letak label (A-262, 18 §5.3): enam elemen tetap, posisi dan
 * ukuran dalam mm di dalam batas label, tata letak otomatis per ukuran.
 *
 * Satu tempat untuk desainer (layar), penyimpanan, dan cetak PDF, supaya
 * yang tampil di kanvas sama dengan yang tercetak.
 */
class LabelDesignRules
{
    /** Elemen yang bisa ditata; teks isinya dari LabelPayload (A-121). */
    public const ELEMEN = ['title', 'subtitle', 'detail', 'barcode', 'qr', 'logo'];

    public const TEKS = ['title', 'subtitle', 'detail'];

    public const MIN_SISI = 1.5;

    public const FONT_MIN = 4.0;

    public const FONT_MAKS = 72.0;

    /** 1 pt = 0,3528 mm; tinggi kotak 1,35 × ukuran huruf supaya huruf berekor (g, p, y) tidak terpotong. */
    public static function lineMm(float $pt): float
    {
        return round($pt * 0.3528 * 1.35, 1);
    }

    /** @return array<string, string> elemen => nama di layar */
    public static function names(): array
    {
        return [
            'title' => __('Teks utama'),
            'subtitle' => __('Teks kedua'),
            'detail' => __('Keterangan'),
            'barcode' => __('Barcode (Code128)'),
            'qr' => __('QR'),
            'logo' => __('Logo company'),
        ];
    }

    /**
     * Arti teks per jenis label, sesuai LabelPayload.
     *
     * @return array<string, string>
     */
    public static function sources(DocumentTemplateType $type): array
    {
        return match ($type) {
            DocumentTemplateType::LabelBin => ['title' => __('Kode bin'), 'subtitle' => __('Nama gudang'), 'detail' => __('Jenis bin')],
            DocumentTemplateType::LabelItem => ['title' => __('Kode item'), 'subtitle' => __('Nama item'), 'detail' => __('Satuan dasar')],
            DocumentTemplateType::LabelLot => ['title' => __('Nomor lot'), 'subtitle' => __('Kode & nama item'), 'detail' => __('Tanggal masuk & kedaluwarsa')],
            DocumentTemplateType::LabelPackage => ['title' => __('Kode label kemasan'), 'subtitle' => __('Kode & nama item'), 'detail' => __('Isi, tanggal masuk, vendor, GRN, lot')],
            DocumentTemplateType::LabelSerial => ['title' => __('Nomor seri'), 'subtitle' => __('Kode & nama item'), 'detail' => __('Tanggal masuk, vendor, GRN')],
            default => ['title' => __('Nomor potongan'), 'subtitle' => __('Kode & nama item'), 'detail' => __('Panjang & tanggal masuk')],
        };
    }

    /**
     * Tata letak otomatis mengikuti ukuran label dan kode yang dicetak.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function defaults(LabelFormat $format, LabelCodeMode $mode): array
    {
        $w = $format->width_mm;
        $h = $format->height_mm;
        $p = round(max(1.0, min(3.0, min($w, $h) * 0.06)), 1);
        $judulPt = round(max(7.0, min(24.0, $h * 0.28)), 1);
        $kecilPt = round(max(5.5, min(14.0, $h * 0.2)), 1);
        $judulH = self::lineMm($judulPt);
        $kecilH = self::lineMm($kecilPt);

        $qr = 0.0;
        $teksX = $p;
        $teksW = $w - 2 * $p;
        $batangH = 0.0;

        if ($mode === LabelCodeMode::Both) {
            $qr = round(min($h * 0.5, $w * 0.3, $h - 2 * $p), 1);
            $teksW = $w - 2 * $p - $qr - 1;
            $batangH = round(min($h * 0.3, 25.0), 1);
        } elseif ($mode === LabelCodeMode::Barcode) {
            $batangH = round(min($h * 0.38, 30.0), 1);
        } else {
            $qr = round(min($h - 2 * $p, $w * 0.4), 1);
            $teksX = $p + $qr + 1.5;
            $teksW = $w - $teksX - $p;
        }

        $elemen = [
            'title' => ['visible' => true, 'x' => $teksX, 'y' => $p, 'w' => $teksW, 'h' => $judulH, 'font' => $judulPt, 'bold' => true, 'align' => 'left'],
            'subtitle' => ['visible' => true, 'x' => $teksX, 'y' => $p + $judulH, 'w' => $teksW, 'h' => 2 * $kecilH, 'font' => $kecilPt, 'bold' => false, 'align' => 'left'],
            'detail' => ['visible' => true, 'x' => $teksX, 'y' => $p + $judulH + 2 * $kecilH, 'w' => $teksW, 'h' => $kecilH, 'font' => $kecilPt, 'bold' => false, 'align' => 'left'],
            'barcode' => ['visible' => $mode->hasBarcode(), 'x' => $p, 'y' => $h - $p - $batangH, 'w' => $w - 2 * $p, 'h' => max($batangH, 6.0), 'font' => round(max(5.0, $kecilPt * 0.9), 1), 'bold' => false, 'align' => 'center', 'show_text' => true],
            'qr' => ['visible' => $mode->hasQr(), 'x' => $mode === LabelCodeMode::Qr ? $p : $w - $p - $qr, 'y' => $p, 'w' => max($qr, 8.0), 'h' => max($qr, 8.0), 'font' => $kecilPt, 'bold' => false, 'align' => 'center'],
            'logo' => ['visible' => false, 'x' => $p, 'y' => $p, 'w' => round(min(15.0, $w / 4), 1), 'h' => round(min(8.0, $h / 4), 1), 'font' => $kecilPt, 'bold' => false, 'align' => 'left'],
        ];

        return self::normalize($elemen, $format);
    }

    /**
     * Rapikan isian dari desainer atau desain lama: kunci yang dikenal saja,
     * angka dibulatkan 0,1 mm, dan setiap elemen dijaga tetap di dalam label.
     *
     * @param  array<string, mixed>  $elements
     * @return array<string, array<string, mixed>>
     */
    public static function normalize(array $elements, LabelFormat $format): array
    {
        $w = $format->width_mm;
        $h = $format->height_mm;
        $hasil = [];

        foreach (self::ELEMEN as $kunci) {
            $e = is_array($elements[$kunci] ?? null) ? $elements[$kunci] : [];

            $lebar = self::angka($e['w'] ?? null, $w / 3, self::MIN_SISI, $w);
            $tinggi = self::angka($e['h'] ?? null, $h / 4, self::MIN_SISI, $h);
            $align = in_array($e['align'] ?? null, ['left', 'center', 'right'], true) ? $e['align'] : ($kunci === 'barcode' || $kunci === 'qr' ? 'center' : 'left');

            $hasil[$kunci] = [
                'visible' => filter_var($e['visible'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'x' => self::angka($e['x'] ?? null, 0, 0, $w - $lebar),
                'y' => self::angka($e['y'] ?? null, 0, 0, $h - $tinggi),
                'w' => $lebar,
                'h' => $tinggi,
                'font' => self::angka($e['font'] ?? null, 7, self::FONT_MIN, self::FONT_MAKS),
                'bold' => filter_var($e['bold'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'align' => $align,
            ];

            if ($kunci === 'barcode') {
                $hasil[$kunci]['show_text'] = filter_var($e['show_text'] ?? true, FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $hasil;
    }

    private static function angka(mixed $nilai, float $bawaan, float $min, float $maks): float
    {
        $n = is_numeric($nilai) ? (float) $nilai : $bawaan;

        return round(max($min, min(max($min, $maks), $n)), 1);
    }
}

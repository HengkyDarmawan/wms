<?php

declare(strict_types=1);

namespace App\Domain\Template\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Picqer\Barcode\BarcodeGeneratorPNG;

/**
 * Gambar barcode Code128 dan QR sebagai data URI PNG untuk dompdf (AD-08).
 *
 * PNG dipilih, bukan SVG: dompdf merender PNG dengan tepat, sedangkan dukungan
 * SVG-nya terbatas. QR digambar sendiri dari matriks bacon lewat GD, karena
 * penulis PNG bawaan bacon butuh imagick yang tidak ada di semua mesin.
 */
class BarcodeImage
{
    /** Code128 untuk teks apa pun yang bisa dipindai scanner 1D. */
    public function code128(string $data, int $widthFactor = 2, int $height = 40): string
    {
        $generator = new BarcodeGeneratorPNG;
        $generator->useGd();

        return 'data:image/png;base64,'.base64_encode(
            $generator->getBarcode($data, $generator::TYPE_CODE_128, $widthFactor, $height),
        );
    }

    /** QR level M, satu modul = `$scale` piksel, zona tenang 2 modul. */
    public function qr(string $data, int $scale = 4): string
    {
        $matrix = Encoder::encode($data, ErrorCorrectionLevel::M(), 'UTF-8')->getMatrix();
        $tepi = 2;
        $sisi = ($matrix->getWidth() + 2 * $tepi) * $scale;

        $gambar = imagecreatetruecolor($sisi, $sisi);
        $putih = imagecolorallocate($gambar, 255, 255, 255);
        $hitam = imagecolorallocate($gambar, 0, 0, 0);
        imagefill($gambar, 0, 0, $putih);

        for ($y = 0; $y < $matrix->getHeight(); $y++) {
            for ($x = 0; $x < $matrix->getWidth(); $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $x0 = ($x + $tepi) * $scale;
                    $y0 = ($y + $tepi) * $scale;
                    imagefilledrectangle($gambar, $x0, $y0, $x0 + $scale - 1, $y0 + $scale - 1, $hitam);
                }
            }
        }

        ob_start();
        imagepng($gambar);
        $png = (string) ob_get_clean();
        imagedestroy($gambar);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}

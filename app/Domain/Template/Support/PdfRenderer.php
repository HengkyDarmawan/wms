<?php

declare(strict_types=1);

namespace App\Domain\Template\Support;

use App\Domain\Template\Enums\PaperSize;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/**
 * Satu pintu HTML → PDF untuk semua cetakan (AD-08). Subset font dinyalakan
 * supaya PDF tidak membawa seluruh DejaVu Sans (±850 KB per berkas).
 */
class PdfRenderer
{
    public static function make(string $html, PaperSize $paper): DomPdf
    {
        [$ukuran, $arah] = $paper->dompdf();

        return Pdf::setOption(['isFontSubsettingEnabled' => true])
            ->loadHTML($html)
            ->setPaper($ukuran, $arah);
    }

    /** Halaman berukuran bebas dalam mm, untuk label (A-261). 1 mm = 2,8346 pt. */
    public static function makeCustom(string $html, float $widthMm, float $heightMm): DomPdf
    {
        return Pdf::setOption(['isFontSubsettingEnabled' => true])
            ->loadHTML($html)
            ->setPaper([0, 0, round($widthMm * 2.8346, 2), round($heightMm * 2.8346, 2)], 'portrait');
    }
}

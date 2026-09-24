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
}

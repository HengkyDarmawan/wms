<?php

declare(strict_types=1);

namespace App\Http\Controllers\Template;

use App\Domain\Template\Actions\PrintLabels;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Enums\PaperSize;
use App\Domain\Template\Support\DocumentPrinter;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cetak dokumen dan label (18 §6). Semua GET dan hanya membaca: mencetak
 * bukan transisi status (P-04), jadi aman diulang dan dibuka di tab baru.
 */
class PrintController extends Controller
{
    public function document(Request $request, string $type, int $id, DocumentPrinter $printer): Response
    {
        $jenis = DocumentTemplateType::fromRoute($type);
        abort_if($jenis === null, 404);

        return $printer->stream($jenis, $id, $request->user());
    }

    public function labelForm(): View
    {
        $this->authorize('label.print');

        return view('template.labels');
    }

    public function labels(Request $request, PrintLabels $action): Response
    {
        $jenis = DocumentTemplateType::tryFrom((string) $request->query('type'));
        abort_if($jenis === null || ! $jenis->isLabel(), 404);

        $ids = $request->query('ids', []);
        $ids = is_array($ids) ? $ids : explode(',', (string) $ids);

        return $action->handle(
            $jenis,
            $ids,
            PaperSize::tryFrom((string) $request->query('paper')),
            (int) $request->query('copies', 1),
            $request->user(),
        );
    }
}

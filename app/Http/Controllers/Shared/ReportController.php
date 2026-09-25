<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared;

use App\Domain\Shared\Reports\ReportExport;
use App\Domain\Shared\Reports\ReportRegistry;
use App\Domain\Template\Enums\PaperSize;
use App\Domain\Template\Support\PdfRenderer;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Daftar laporan, satu layar laporan, dan ekspor Excel (AD-09).
 *
 * Izin diperiksa per laporan, bukan per modul: laporan gudang hanya terbuka
 * untuk pemegang `warehouse.view`, laporan item untuk `item.view`, dan
 * seterusnya sesuai definisinya.
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportRegistry $registry) {}

    public function index(Request $request): View
    {
        return view('shared.reports.index', [
            'laporan' => $this->registry->availableTo($request->user()),
        ]);
    }

    public function show(Request $request, string $report): View
    {
        $laporan = $this->registry->find($report);

        abort_unless($request->user()->hasPermission($laporan->permission()), 403);

        return view('shared.reports.show', ['laporan' => $laporan]);
    }

    public function export(Request $request, string $report): BinaryFileResponse
    {
        $laporan = $this->registry->find($report);

        abort_unless($request->user()->hasPermission($laporan->permission()), 403);

        $filters = $this->penyaring($request);

        activity('report')
            ->causedBy($request->user())
            ->withProperties(['report' => $laporan->key(), 'filters' => $filters])
            ->log('Laporan diekspor: '.$laporan->title());

        return Excel::download(
            new ReportExport($laporan, $filters),
            $laporan->fileName().'.xlsx',
        );
    }

    /** Ekspor PDF satu laporan (Blueprint §6.9a UX-12, A-190): tabel A4 mendatar, tanpa nilai uang (D-07). */
    public function pdf(Request $request, string $report): Response
    {
        $laporan = $this->registry->find($report);

        abort_unless($request->user()->hasPermission($laporan->permission()), 403);

        $filters = $this->penyaring($request);

        activity('report')
            ->causedBy($request->user())
            ->withProperties(['report' => $laporan->key(), 'filters' => $filters, 'format' => 'pdf'])
            ->log('Laporan diekspor PDF: '.$laporan->title());

        $html = view('shared.reports.pdf', [
            'laporan' => $laporan,
            'baris' => $laporan->rows($filters),
            'filters' => array_filter($filters, fn ($v) => $v !== ''),
            'company' => tenant(),
        ])->render();

        return PdfRenderer::make($html, PaperSize::A4Landscape)->stream($laporan->fileName().'.pdf');
    }

    /** @return array<string, string> */
    private function penyaring(Request $request): array
    {
        return array_map(
            fn ($nilai) => is_scalar($nilai) ? (string) $nilai : '',
            (array) $request->query('filters', []),
        );
    }
}

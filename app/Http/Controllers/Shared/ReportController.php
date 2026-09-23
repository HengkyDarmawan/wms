<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared;

use App\Domain\Shared\Reports\ReportExport;
use App\Domain\Shared\Reports\ReportRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        /** @var array<string, string> $filters */
        $filters = array_map(
            fn ($nilai) => is_scalar($nilai) ? (string) $nilai : '',
            $request->query('filters', []),
        );

        activity('report')
            ->causedBy($request->user())
            ->withProperties(['report' => $laporan->key(), 'filters' => $filters])
            ->log('Laporan diekspor: '.$laporan->title());

        return Excel::download(
            new ReportExport($laporan, $filters),
            $laporan->fileName().'.xlsx',
        );
    }
}

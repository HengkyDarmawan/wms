<?php

declare(strict_types=1);

namespace App\Http\Controllers\Count;

use App\Domain\Count\Models\CountAssignment;
use App\Domain\Count\Models\CountLine;
use App\Domain\Count\Models\StockCount;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman modul Count (21-opname-penyesuaian §6). Semua GET dan hanya
 * membaca; transisi status lewat aksi Livewire (POST).
 */
class CountController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', StockCount::class);

        return view('count.index');
    }

    public function create(): View
    {
        $this->authorize('create', StockCount::class);

        return view('count.create');
    }

    public function show(StockCount $stockCount): View
    {
        $this->authorize('view', $stockCount);

        return view('count.show', ['count' => $stockCount]);
    }

    /** Laporan PDF sesi (Katalog §2.13 "Laporan PDF terbit"), dibuat saat diminta (A-101). */
    public function report(StockCount $stockCount): Response
    {
        $this->authorize('report', $stockCount);

        $stockCount->load('warehouses:id,code,name', 'creator:id,name', 'submitter:id,name', 'approver:id,name');

        $pdf = Pdf::loadView('count.report', [
            'count' => $stockCount,
            'lines' => CountLine::query()->with('bin:id,code', 'item:id,code,name', 'lot', 'serial', 'piece')
                ->where('stock_count_id', $stockCount->id)->orderBy('bin_id')->orderBy('id')->get(),
            'adjustments' => $stockCount->adjustments()->orderBy('id')->get(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download(str_replace('/', '-', $stockCount->number).'.pdf');
    }

    public function tasks(): View
    {
        $this->authorize('viewAny', CountAssignment::class);

        return view('count.tasks');
    }

    public function task(CountAssignment $countAssignment): View
    {
        $this->authorize('view', $countAssignment);

        return view('count.entry', ['assignment' => $countAssignment]);
    }
}

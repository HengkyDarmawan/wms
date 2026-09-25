<?php

declare(strict_types=1);

namespace App\Http\Controllers\Count;

use App\Domain\Count\Models\CountAssignment;
use App\Domain\Count\Models\StockCount;
use App\Domain\Count\Support\CountReportArchive;
use App\Domain\Shared\Files\StoreUpload;
use App\Http\Controllers\Controller;
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

    /**
     * Laporan PDF sesi (Katalog §2.13 "Laporan PDF terbit"): arsip lampiran bila
     * sesi sudah ditutup (A-238), selain itu dibuat saat diminta (A-101).
     */
    public function report(StockCount $stockCount, CountReportArchive $laporan, StoreUpload $files): Response
    {
        $this->authorize('report', $stockCount);

        $arsip = $laporan->archived($stockCount);

        if ($arsip !== null && $files->exists($arsip->path)) {
            return $files->download($arsip->path, $laporan->fileName($stockCount));
        }

        return response($laporan->render($stockCount), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$laporan->fileName($stockCount).'"',
        ]);
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

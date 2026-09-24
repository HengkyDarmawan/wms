<?php

declare(strict_types=1);

namespace App\Http\Controllers\Transfer;

use App\Domain\Transfer\Models\Transfer;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Halaman transfer (22-retur-transfer §6). Semua GET; transisi lewat Livewire (POST). */
class TransferController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Transfer::class);

        return view('transfer.index');
    }

    public function create(): View
    {
        $this->authorize('create', Transfer::class);

        return view('transfer.create');
    }

    public function show(Transfer $transfer): View
    {
        $this->authorize('view', $transfer);

        return view('transfer.show', ['trf' => $transfer]);
    }
}

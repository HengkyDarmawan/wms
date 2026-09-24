<?php

declare(strict_types=1);

namespace App\Http\Controllers\Adjustment;

use App\Domain\Adjustment\Models\StockAdjustment;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Halaman penyesuaian stok (21-opname-penyesuaian §6). Semua GET; transisi lewat Livewire (POST). */
class AdjustmentController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', StockAdjustment::class);

        return view('adjustment.index');
    }

    public function create(): View
    {
        $this->authorize('create', StockAdjustment::class);

        return view('adjustment.create');
    }

    public function show(StockAdjustment $stockAdjustment): View
    {
        $this->authorize('view', $stockAdjustment);

        return view('adjustment.show', ['adj' => $stockAdjustment]);
    }
}

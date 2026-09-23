<?php

declare(strict_types=1);

namespace App\Http\Controllers\Stock;

use App\Domain\Master\Models\Item;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Stock\Models\StockReservation;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Halaman modul Stock (13-stock §6). Semuanya baca saja; aksi lewat Livewire. */
class StockController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', StockBalance::class);

        return view('stock.index');
    }

    public function card(Item $item): View
    {
        $this->authorize('viewAny', StockBalance::class);

        return view('stock.card', ['item' => $item]);
    }

    public function reservations(): View
    {
        $this->authorize('viewAny', StockReservation::class);

        return view('stock.reservations');
    }

    public function events(): View
    {
        $this->authorize('viewAny', StockEvent::class);

        return view('stock.events');
    }

    /** BR-STK-15: layar kunci periode ada di pengaturan, bukan di daftar stok. */
    public function period(): View
    {
        $this->authorize('lockPeriod', StockBalance::class);

        return view('stock.period');
    }
}

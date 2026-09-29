<?php

declare(strict_types=1);

namespace App\Http\Controllers\Warehouse;

use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Support\BinCode;
use App\Domain\Warehouse\Support\BinContents;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Halaman daftar bin lintas gudang (12-warehouse §6) dan Isi Bin (A-374). */
class BinController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Bin::class);

        return view('warehouse.bins.index');
    }

    /**
     * Isi Bin (K-G, A-374) — tujuan QR label bin. Bin dicari lewat query
     * bercakupan (BR-ACC-05): bin di luar gudang pengguna = tidak ditemukan.
     */
    public function show(string $kode, BinContents $isi): View
    {
        $this->authorize('viewAny', Bin::class);

        $bin = Bin::query()->where('code', BinCode::dariPindai($kode))->firstOrFail();
        $this->authorize('view', $bin);

        return view('warehouse.bins.show', $isi->untuk($bin));
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Return;

use App\Domain\Return\Models\GoodsReturn;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Halaman retur dari proyek (22-retur-transfer §6) — back-office dan portal
 * klien memakai komponen yang sama (BR-RET-05). Semua GET; transisi lewat
 * Livewire (POST).
 */
class ReturnController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', GoodsReturn::class);

        return view('return.index');
    }

    public function create(): View
    {
        $this->authorize('create', GoodsReturn::class);

        return view('return.create');
    }

    public function show(GoodsReturn $goodsReturn): View
    {
        $this->authorize('view', $goodsReturn);

        return view('return.show', ['ret' => $goodsReturn]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Master;

use App\Domain\Master\Models\Item;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Halaman item: daftar, form, dan detail (11-master §6). */
class ItemController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Item::class);

        return view('master.items.index');
    }

    public function create(): View
    {
        $this->authorize('create', Item::class);

        return view('master.items.form', ['item' => null]);
    }

    public function edit(Item $item): View
    {
        $this->authorize('update', $item);

        return view('master.items.form', ['item' => $item]);
    }

    public function show(Item $item): View
    {
        $this->authorize('view', $item);

        return view('master.items.show', ['item' => $item]);
    }
}

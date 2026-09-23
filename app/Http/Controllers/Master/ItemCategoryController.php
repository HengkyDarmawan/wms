<?php

declare(strict_types=1);

namespace App\Http\Controllers\Master;

use App\Domain\Master\Models\ItemCategory;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Halaman kategori item (11-master §6). */
class ItemCategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', ItemCategory::class);

        return view('master.item-categories.index');
    }
}

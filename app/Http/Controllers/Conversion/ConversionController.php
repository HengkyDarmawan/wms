<?php

declare(strict_types=1);

namespace App\Http\Controllers\Conversion;

use App\Domain\Conversion\Models\Conversion;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Halaman konversi material (24-konversi-waste §6). Semua GET; transisi lewat Livewire (POST). */
class ConversionController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Conversion::class);

        return view('conversion.index');
    }

    public function create(): View
    {
        $this->authorize('create', Conversion::class);

        return view('conversion.create');
    }

    public function edit(Conversion $conversion): View
    {
        $this->authorize('update', $conversion);

        return view('conversion.edit', ['cnv' => $conversion]);
    }

    public function show(Conversion $conversion): View
    {
        $this->authorize('view', $conversion);

        return view('conversion.show', ['cnv' => $conversion]);
    }
}

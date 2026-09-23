<?php

declare(strict_types=1);

namespace App\Http\Controllers\Request;

use App\Domain\Request\Models\MaterialRequest;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Halaman modul Request (14-request §6). Transisi status lewat Livewire, bukan GET. */
class RequestController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', MaterialRequest::class);

        return view('request.index');
    }

    public function create(): View
    {
        $this->authorize('create', MaterialRequest::class);

        return view('request.create');
    }

    public function show(MaterialRequest $materialRequest): View
    {
        $this->authorize('view', $materialRequest);

        return view('request.show', ['req' => $materialRequest]);
    }

    public function edit(MaterialRequest $materialRequest): View
    {
        $this->authorize('update', $materialRequest);

        return view('request.edit', ['req' => $materialRequest]);
    }
}

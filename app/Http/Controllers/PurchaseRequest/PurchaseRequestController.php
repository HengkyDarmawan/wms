<?php

declare(strict_types=1);

namespace App\Http\Controllers\PurchaseRequest;

use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Halaman Purchase Request (26-purchase-request §6). Semua GET; transisi lewat Livewire (POST). */
class PurchaseRequestController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', PurchaseRequest::class);

        return view('purchase-request.index');
    }

    public function create(): View
    {
        $this->authorize('create', PurchaseRequest::class);

        return view('purchase-request.create');
    }

    public function edit(PurchaseRequest $purchaseRequest): View
    {
        $this->authorize('update', $purchaseRequest);

        return view('purchase-request.edit', ['prq' => $purchaseRequest]);
    }

    public function show(PurchaseRequest $purchaseRequest): View
    {
        $this->authorize('view', $purchaseRequest);

        return view('purchase-request.show', ['prq' => $purchaseRequest]);
    }
}

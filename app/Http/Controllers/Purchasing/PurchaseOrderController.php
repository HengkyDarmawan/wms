<?php

declare(strict_types=1);

namespace App\Http\Controllers\Purchasing;

use App\Domain\Master\Models\Item;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\VendorPrice;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Halaman Purchasing inti (purchasing/02 §6). Semua GET; transisi lewat Livewire (POST). */
class PurchaseOrderController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        return view('purchasing.index');
    }

    public function create(): View
    {
        $this->authorize('create', PurchaseOrder::class);

        return view('purchasing.create');
    }

    public function edit(PurchaseOrder $purchaseOrder): View
    {
        $this->authorize('update', $purchaseOrder);

        return view('purchasing.edit', ['po' => $purchaseOrder]);
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $this->authorize('view', $purchaseOrder);

        return view('purchasing.show', ['po' => $purchaseOrder]);
    }

    public function vendorPrices(): View
    {
        $this->authorize('viewAny', VendorPrice::class);

        return view('purchasing.vendor-prices');
    }

    /** Riwayat harga beli per item (A-309); harga hanya bagi `po.view` (D-07). */
    public function priceHistory(Item $item): View
    {
        $this->authorize('po.view');

        return view('purchasing.price-history', ['item' => $item]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Receipt;

use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\PutawayTask;
use App\Domain\Receipt\Models\VendorReturn;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Halaman modul Receipt/Putaway (19-receipt-putaway §6). Semua GET; transisi lewat Livewire (POST). */
class ReceiptController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', GoodsReceipt::class);

        return view('receipt.index');
    }

    public function create(): View
    {
        $this->authorize('create', GoodsReceipt::class);

        return view('receipt.form', ['grn' => null]);
    }

    public function show(GoodsReceipt $goodsReceipt): View
    {
        $this->authorize('view', $goodsReceipt);

        return view('receipt.show', ['grn' => $goodsReceipt]);
    }

    public function edit(GoodsReceipt $goodsReceipt): View
    {
        $this->authorize('update', $goodsReceipt);

        return view('receipt.form', ['grn' => $goodsReceipt]);
    }

    public function putaways(): View
    {
        $this->authorize('viewAny', PutawayTask::class);

        return view('receipt.putaways');
    }

    public function putaway(PutawayTask $putawayTask): View
    {
        $this->authorize('view', $putawayTask);

        return view('receipt.putaway', ['task' => $putawayTask]);
    }

    public function vendorReturns(): View
    {
        $this->authorize('viewAny', VendorReturn::class);

        return view('receipt.vendor-returns');
    }

    public function createVendorReturn(): View
    {
        $this->authorize('create', VendorReturn::class);

        return view('receipt.vendor-return-create');
    }

    public function vendorReturn(VendorReturn $vendorReturn): View
    {
        $this->authorize('view', $vendorReturn);

        return view('receipt.vendor-return', ['rtv' => $vendorReturn]);
    }
}

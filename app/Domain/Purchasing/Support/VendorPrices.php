<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Support;

use App\Domain\Purchasing\Models\VendorPrice;
use Carbon\CarbonInterface;

/**
 * Harga beli yang berlaku (A-211): baris aktif vendor × item dengan
 * `valid_from` terbaru yang tidak melewati tanggal PO.
 */
class VendorPrices
{
    public function current(int $vendorId, int $itemId, ?CarbonInterface $on = null): ?VendorPrice
    {
        return VendorPrice::query()
            ->where('vendor_id', $vendorId)
            ->where('item_id', $itemId)
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', ($on ?? now())->toDateString())
            ->orderByDesc('valid_from')->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<int, int>  $itemIds
     * @return array<int, float> item_id => harga satuan
     */
    public function currentMany(int $vendorId, array $itemIds, ?CarbonInterface $on = null): array
    {
        $hasil = [];

        foreach (array_unique($itemIds) as $itemId) {
            $harga = $this->current($vendorId, (int) $itemId, $on);

            if ($harga !== null) {
                $hasil[(int) $itemId] = (float) $harga->unit_price;
            }
        }

        return $hasil;
    }
}

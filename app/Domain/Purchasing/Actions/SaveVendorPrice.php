<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Vendor;
use App\Domain\Purchasing\Exceptions\PurchasingRuleException;
use App\Domain\Purchasing\Models\VendorPrice;
use App\Domain\Purchasing\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `vendor_price.manage` — harga beli vendor × item (A-211). Harga
 * baru selalu baris baru; baris aktif dengan tanggal berlaku yang sama
 * dinonaktifkan (digantikan), riwayat tidak dihapus (P-03).
 */
class SaveVendorPrice
{
    /** @param  array{vendor_id?: mixed, item_id?: mixed, unit_price?: mixed, valid_from?: mixed, notes?: mixed}  $data */
    public function handle(array $data, ?User $actor = null): VendorPrice
    {
        $vendor = Vendor::query()->find(is_numeric($data['vendor_id'] ?? null) ? (int) $data['vendor_id'] : 0);

        if ($vendor === null || $vendor->status === VendorStatus::Inactive) {
            throw PurchasingRuleException::field('BR-GEN-11', 'vendor_id', 'Vendor aktif wajib dipilih.');
        }

        $item = Item::query()->find(is_numeric($data['item_id'] ?? null) ? (int) $data['item_id'] : 0)
            ?? throw PurchasingRuleException::field('BR-GEN-11', 'item_id', 'Item wajib dipilih.');

        $harga = is_numeric($data['unit_price'] ?? null) ? Money::round((float) $data['unit_price']) : 0.0;

        if ($harga <= 0) {
            throw PurchasingRuleException::field('A-211', 'unit_price', 'Harga satuan wajib lebih dari nol.');
        }

        try {
            $berlaku = trim((string) ($data['valid_from'] ?? '')) === '' ? now()->toDateString() : Carbon::parse((string) $data['valid_from'])->toDateString();
        } catch (\Throwable) {
            throw PurchasingRuleException::field('BR-GEN-11', 'valid_from', 'Tanggal berlaku tidak valid.');
        }

        $catatan = trim((string) ($data['notes'] ?? ''));

        return DB::transaction(function () use ($vendor, $item, $harga, $berlaku, $catatan, $actor) {
            VendorPrice::query()->where('vendor_id', $vendor->id)->where('item_id', $item->id)
                ->where('is_active', true)->whereDate('valid_from', $berlaku)
                ->update(['is_active' => false]);

            $baru = VendorPrice::create([
                'vendor_id' => $vendor->id,
                'item_id' => $item->id,
                'unit_price' => $harga,
                'currency' => Money::CURRENCY,
                'valid_from' => $berlaku,
                'is_active' => true,
                'notes' => $catatan === '' ? null : mb_substr($catatan, 0, 255),
                'created_by' => $actor?->id,
            ]);

            activity('purchasing')->performedOn($baru)->causedBy($actor)
                ->withProperties(['vendor' => $vendor->code, 'item' => $item->code, 'harga' => $harga, 'berlaku' => $berlaku])
                ->log('Harga beli vendor dicatat');

            return $baru;
        });
    }

    public function deactivate(VendorPrice $price, ?User $actor = null): VendorPrice
    {
        $price->forceFill(['is_active' => false])->save();

        activity('purchasing')->performedOn($price)->causedBy($actor)->log('Harga beli vendor dinonaktifkan');

        return $price->refresh();
    }
}

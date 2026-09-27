<?php

declare(strict_types=1);

namespace App\Domain\Master\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\ItemUomConversion;
use App\Domain\Master\Models\Uom;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Permission: izin dokumen induk (`receipt.create`, `request.create`,
 * `return.create`) — A-292. Menyimpan kemasan yang diketik staf di form
 * dokumen ("1 DUS = 12 BOX", centang *Ingat untuk item ini*) ke kemasan item.
 *
 * Hanya **menambah**: kemasan yang sudah ada (aktif atau nonaktif) tidak
 * ditimpa dan tidak dihidupkan lagi dari form dokumen — itu tetap wewenang
 * master item. Tercatat di riwayat item (BR-GEN-05).
 */
class RememberItemPackaging
{
    /** @return bool true bila kemasan baru tersimpan */
    public function handle(Item $item, Uom $uom, float $qtyBase, ?User $actor = null, ?string $sumber = null): bool
    {
        if ($qtyBase <= 0 || (int) $uom->id === (int) $item->base_uom_id || $item->tracksPiece()) {
            return false;
        }

        if (ItemUomConversion::query()->where('item_id', $item->id)->where('uom_id', $uom->id)->exists()) {
            return false;
        }

        try {
            ItemUomConversion::create([
                'item_id' => $item->id,
                'uom_id' => $uom->id,
                'qty_base' => round($qtyBase, 4),
                'is_nominal_piece' => false,
                'is_active' => true,
            ]);
        } catch (UniqueConstraintViolationException) {
            return false; // disimpan dokumen lain pada saat yang sama
        }

        $item->unsetRelation('activeConversions');

        activity('master')
            ->performedOn($item)
            ->causedBy($actor)
            ->withProperties(['uom' => $uom->code, 'qty_base' => round($qtyBase, 4), 'sumber' => $sumber])
            ->log('Kemasan 1 '.$uom->code.' = '.rtrim(rtrim(number_format($qtyBase, 4, ',', '.'), '0'), ',').' '.$item->baseUom?->code.' ditambahkan'.($sumber ? ' dari '.$sumber : ''));

        return true;
    }
}

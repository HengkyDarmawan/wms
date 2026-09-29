<?php

declare(strict_types=1);

namespace App\Domain\Master\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\ItemUomConversion;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Support\QtyFormat;
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
    /**
     * @param  array{qty: float, uom_id: int}|null  $isi  kalimat isi (A-357) bila diketik dengan kemasan lain
     *                                                    yang lebih kecil; diabaikan bila tidak cocok dengan $qtyBase
     * @return bool true bila kemasan baru tersimpan
     */
    public function handle(Item $item, Uom $uom, float $qtyBase, ?User $actor = null, ?string $sumber = null, ?array $isi = null): bool
    {
        if ($qtyBase <= 0 || (int) $uom->id === (int) $item->base_uom_id || $item->tracksPiece()) {
            return false;
        }

        if (ItemUomConversion::query()->where('item_id', $item->id)->where('uom_id', $uom->id)->exists()) {
            return false;
        }

        // A-357: satuan isi harus kemasan aktif item yang lain dan hasilnya cocok; kalau tidak, isi dicatat dalam satuan dasar.
        $satuanIsi = $isi === null || (int) $isi['uom_id'] === (int) $uom->id ? null
            : ItemUomConversion::query()->with('uom:id,code')->where('item_id', $item->id)
                ->where('uom_id', (int) $isi['uom_id'])->where('is_active', true)->first();

        if ($satuanIsi !== null && abs((float) $isi['qty'] * (float) $satuanIsi->qty_base - $qtyBase) > 0.001) {
            $satuanIsi = null;
        }

        try {
            ItemUomConversion::create([
                'item_id' => $item->id,
                'uom_id' => $uom->id,
                'qty_base' => round($qtyBase, 4),
                'content_qty' => $satuanIsi === null ? round($qtyBase, 4) : round((float) $isi['qty'], 4),
                'content_uom_id' => $satuanIsi?->uom_id,
                'is_nominal_piece' => false,
                'is_active' => true,
            ]);
        } catch (UniqueConstraintViolationException) {
            return false; // disimpan dokumen lain pada saat yang sama
        }

        $item->unsetRelation('activeConversions');

        $dasar = QtyFormat::withUnit($qtyBase, $item->baseUom?->code);
        $kalimat = $satuanIsi === null ? $dasar
            : QtyFormat::withUnit($isi['qty'], $satuanIsi->uom?->code).' (= '.$dasar.')';

        activity('master')
            ->performedOn($item)
            ->causedBy($actor)
            ->withProperties(['uom' => $uom->code, 'qty_base' => round($qtyBase, 4), 'sumber' => $sumber])
            ->log('Kemasan 1 '.$uom->code.' berisi '.$kalimat.' ditambahkan'.($sumber ? ' dari '.$sumber : ''));

        return true;
    }
}

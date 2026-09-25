<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Support;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Models\Item;
use App\Domain\PurchaseRequest\Exceptions\PurchaseRequestRuleException;
use Carbon\Carbon;

/**
 * Isian baris PRQ manual / draf titik pesan ulang (Katalog §2.15 guard "item
 * terdefinisi"): item katalog aktif atau sementara (A-51), jumlah satuan
 * dasar > 0, tanggal dibutuhkan opsional.
 */
class PurchaseLines
{
    /**
     * @param  array<int, array<string, mixed>>  $lines  item_id, qty_base, required_date, notes
     * @return array<int, array<string, mixed>>
     */
    public function normalize(array $lines): array
    {
        $hasil = [];
        $items = Item::query()->whereIn('id', array_map(fn ($l) => (int) ($l['item_id'] ?? 0), $lines))->get()->keyBy('id');

        foreach (array_values($lines) as $i => $l) {
            $itemId = (int) ($l['item_id'] ?? 0);
            $qty = is_numeric($l['qty_base'] ?? null) ? round((float) $l['qty_base'], 4) : 0.0;

            if ($itemId === 0 && $qty == 0.0) {
                continue;
            }

            $label = 'Baris '.($i + 1);
            $item = $items->get($itemId) ?? throw PurchaseRequestRuleException::field('BR-REQ-03', 'lines', $label.': item wajib dipilih dari katalog (non-katalog dipetakan dulu).');

            if (! in_array($item->status, [ItemStatus::Active, ItemStatus::Provisional], true)) {
                throw PurchaseRequestRuleException::field('BR-MST-05', 'lines', $label.': item '.$item->code.' nonaktif.');
            }

            if ($qty <= 0) {
                throw PurchaseRequestRuleException::field('BR-LED-02', 'lines', $label.' ('.$item->code.'): jumlah harus lebih dari nol.');
            }

            $tanggal = null;
            $isiTanggal = trim((string) ($l['required_date'] ?? ''));

            if ($isiTanggal !== '') {
                try {
                    $tanggal = Carbon::parse($isiTanggal)->startOfDay();
                } catch (\Throwable) {
                    throw PurchaseRequestRuleException::field('BR-GEN-11', 'lines', $label.': tanggal dibutuhkan tidak valid.');
                }
            }

            $hasil[] = [
                'item_id' => (int) $item->id,
                'qty_base' => $qty,
                'required_date' => $tanggal?->toDateString(),
                'notes' => ($n = trim((string) ($l['notes'] ?? ''))) === '' ? null : mb_substr($n, 0, 255),
            ];
        }

        if ($hasil === []) {
            throw PurchaseRequestRuleException::field('BR-GEN-11', 'lines', 'Isi minimal satu baris barang.');
        }

        return $hasil;
    }
}

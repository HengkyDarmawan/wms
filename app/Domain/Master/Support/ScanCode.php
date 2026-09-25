<?php

declare(strict_types=1);

namespace App\Domain\Master\Support;

use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\Serial;

/**
 * Membaca hasil pindai label (A-121, A-206) menjadi item — dan lot/serial/
 * potongan bila yang dipindai label berlacak. Kebalikan dari
 * `Template\Support\LabelPayload`: kode/barcode/QR item, QR lot
 * `<kode item>|<lot>`, nomor lot, serial, dan potongan.
 *
 * Satu kode boleh punya beberapa arti (mis. nomor lot yang sama di dua item);
 * pemanggil yang memutuskan apakah itu ambigu.
 */
class ScanCode
{
    public static function normalize(string $raw): string
    {
        return mb_strtoupper(trim($raw));
    }

    /**
     * @return list<array{item_id: int, lot_id: ?int, serial_id: ?int, piece_id: ?int}>
     */
    public static function resolve(string $raw): array
    {
        $kode = self::normalize($raw);

        if ($kode === '') {
            return [];
        }

        $hasil = [];
        $tambah = function (int $itemId, ?int $lot = null, ?int $serial = null, ?int $piece = null) use (&$hasil): void {
            $hasil[$itemId.':'.$lot.':'.$serial.':'.$piece] = ['item_id' => $itemId, 'lot_id' => $lot, 'serial_id' => $serial, 'piece_id' => $piece];
        };

        Item::query()
            ->where(fn ($q) => $q->where('code', $kode)->orWhere('barcode', $kode)->orWhere('qr_payload', $kode))
            ->pluck('id')
            ->each(fn ($id) => $tambah((int) $id));

        if (str_contains($kode, '|')) {
            [$kodeItem, $noLot] = array_map('trim', explode('|', $kode, 2));

            Lot::query()->where('lot_no', $noLot)
                ->whereHas('item', fn ($q) => $q->where('code', $kodeItem))
                ->get(['id', 'item_id'])
                ->each(fn (Lot $l) => $tambah((int) $l->item_id, (int) $l->id));
        }

        Lot::query()->where('lot_no', $kode)->get(['id', 'item_id'])
            ->each(fn (Lot $l) => $tambah((int) $l->item_id, (int) $l->id));

        Serial::query()->where('serial_no', $kode)->get(['id', 'item_id'])
            ->each(fn (Serial $s) => $tambah((int) $s->item_id, null, (int) $s->id));

        Piece::query()->where('piece_no', $kode)->where('is_consumed', false)->get(['id', 'item_id'])
            ->each(fn (Piece $p) => $tambah((int) $p->item_id, null, null, (int) $p->id));

        return array_values($hasil);
    }

    /** @return list<int> item yang ditunjuk kode, tanpa duplikat */
    public static function itemIds(string $raw): array
    {
        return array_values(array_unique(array_map(fn (array $r) => $r['item_id'], self::resolve($raw))));
    }
}

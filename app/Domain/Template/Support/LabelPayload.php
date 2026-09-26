<?php

declare(strict_types=1);

namespace App\Domain\Template\Support;

use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Warehouse\Models\Bin;

/**
 * Isi barcode dan QR per label (A-121). Satu tempat supaya pemindai PWA nanti
 * membaca format yang sama dengan yang dicetak.
 *
 * @phpstan-type Isi array{title: string, subtitle: string, detail: string, code128: string, qr: string}
 */
class LabelPayload
{
    /** @return array{title: string, subtitle: string, detail: string, code128: string, qr: string} */
    public static function bin(Bin $bin): array
    {
        return [
            'title' => (string) $bin->code,
            'subtitle' => (string) ($bin->warehouse?->name ?? ''),
            'detail' => $bin->bin_type?->label() ?? '',
            'code128' => (string) $bin->code,
            'qr' => (string) $bin->code,
        ];
    }

    /** @return array{title: string, subtitle: string, detail: string, code128: string, qr: string} */
    public static function item(Item $item): array
    {
        return [
            'title' => (string) $item->code,
            'subtitle' => (string) $item->name,
            'detail' => (string) ($item->baseUom?->code ?? ''),
            'code128' => self::isi($item->barcode, $item->code),
            'qr' => self::isi($item->qr_payload, $item->code),
        ];
    }

    /** Nomor lot hanya unik per item, jadi QR menyertakan kode item. */
    public static function lot(Lot $lot): array
    {
        $kodeItem = (string) ($lot->item?->code ?? '');

        return [
            'title' => (string) $lot->lot_no,
            'subtitle' => trim($kodeItem.' '.($lot->item?->name ?? '')),
            // A-256: tanggal masuk tercetak supaya yang lama diambil dulu (FIFO).
            'detail' => trim(implode(' · ', array_filter([
                $lot->received_at ? __('Masuk').' '.$lot->received_at->format('d/m/Y') : null,
                $lot->expiry_date ? __('Kedaluwarsa').' '.$lot->expiry_date->format('d/m/Y') : null,
            ]))),
            'code128' => (string) $lot->lot_no,
            'qr' => $kodeItem.'|'.$lot->lot_no,
        ];
    }

    /** @return array{title: string, subtitle: string, detail: string, code128: string, qr: string} */
    public static function piece(Piece $piece): array
    {
        $panjang = rtrim(rtrim(number_format((float) $piece->length, 4, ',', '.'), '0'), ',');

        return [
            'title' => (string) $piece->piece_no,
            'subtitle' => trim(($piece->item?->code ?? '').' '.($piece->item?->name ?? '')),
            'detail' => trim(__('Panjang').' '.$panjang.' '.($piece->item?->baseUom?->code ?? '')
                .($piece->created_at ? ' · '.__('Masuk').' '.$piece->created_at->format('d/m/Y') : '')), // A-256 FIFO
            'code128' => (string) $piece->piece_no,
            'qr' => (string) $piece->piece_no,
        ];
    }

    private static function isi(?string $utama, ?string $cadangan): string
    {
        return $utama !== null && trim($utama) !== '' ? trim($utama) : (string) $cadangan;
    }
}

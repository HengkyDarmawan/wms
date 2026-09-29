<?php

declare(strict_types=1);

namespace App\Domain\Template\Support;

use App\Domain\Label\Models\PackageLabel;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\Serial;
use App\Domain\Master\Support\QtyFormat;
use App\Domain\Receipt\Models\GoodsReceiptLine;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Support\BinCode;

/**
 * Isi barcode dan QR per label (A-121). Satu tempat supaya pemindai PWA nanti
 * membaca format yang sama dengan yang dicetak.
 *
 * @phpstan-type Isi array{title: string, subtitle: string, detail: string, code128: string, qr: string}
 */
class LabelPayload
{
    /**
     * Label bin (A-379): **kode pendek besar** (K-I) sebagai judul, kode
     * lengkap + gudang di bawahnya; Code128 = kode lengkap; QR = **tautan**
     * halaman Isi Bin berisi kode lengkap (keputusan #7, A-373) — kamera HP
     * biasa membuka halamannya, pemindai di aplikasi membaca kodenya.
     *
     * @return array{title: string, subtitle: string, detail: string, code128: string, qr: string}
     */
    public static function bin(Bin $bin, ?string $pendek = null): array
    {
        $kode = (string) $bin->code;

        return [
            'title' => $pendek ?? BinCode::pendekUntuk($bin),
            'subtitle' => trim($kode.' · '.($bin->warehouse?->name ?? ''), ' ·'),
            'detail' => $bin->bin_type?->label() ?? '',
            'code128' => $kode,
            'qr' => BinCode::tautan($kode),
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

    /**
     * A-296: label kemasan induk/isi — kode label (Code128 & QR), item, isi,
     * tanggal terima, vendor, nomor GRN, serta lot/kedaluwarsa/batch vendor.
     * Tanpa harga (D-07).
     *
     * @return array{title: string, subtitle: string, detail: string, code128: string, qr: string}
     */
    public static function package(PackageLabel $label): array
    {
        $item = $label->item;
        $grn = $label->receipt;
        $kemasan = $label->packageUom?->code;
        $lot = $label->lot;

        return [
            'title' => (string) $label->code,
            'subtitle' => trim(($item?->code ?? '').' '.($item?->name ?? '')),
            'detail' => implode(' · ', array_filter([
                __('Isi').' '.QtyFormat::withUnit($label->qty, $item?->baseUom?->code).($label->isParent() && $kemasan ? ' (1 '.$kemasan.')' : ''),
                $grn?->received_at ? __('Masuk').' '.$grn->received_at->lokal()->format('d/m/Y') : null,
                $grn?->vendor?->name,
                $grn?->number,
                $lot ? __('Lot').' '.$lot->lot_no : null,
                $lot?->expiry_date ? __('Kedaluwarsa').' '.$lot->expiry_date->format('d/m/Y') : null,
                ($lot?->attributes['vendor_batch'] ?? null) ? __('Batch').' '.$lot->attributes['vendor_batch'] : null,
            ])),
            'code128' => (string) $label->code,
            'qr' => (string) $label->code,
        ];
    }

    /**
     * A-298: label serial alat — nomor seri, item, tanggal terima, vendor, dan
     * nomor GRN dari penerimaan vendor terakhir serial itu.
     *
     * @return array{title: string, subtitle: string, detail: string, code128: string, qr: string}
     */
    public static function serial(Serial $serial): array
    {
        $asal = GoodsReceiptLine::query()->with('receipt.vendor:id,name')
            ->where('serial_id', $serial->id)->latest('id')->first()?->receipt;

        return [
            'title' => (string) $serial->serial_no,
            'subtitle' => trim(($serial->item?->code ?? '').' '.($serial->item?->name ?? '')),
            'detail' => implode(' · ', array_filter([
                $asal?->received_at ? __('Masuk').' '.$asal->received_at->lokal()->format('d/m/Y')
                    : ($serial->acquired_at ? __('Masuk').' '.$serial->acquired_at->format('d/m/Y') : null),
                $asal?->vendor?->name,
                $asal?->number,
            ])),
            'code128' => (string) $serial->serial_no,
            'qr' => (string) $serial->serial_no,
        ];
    }

    private static function isi(?string $utama, ?string $cadangan): string
    {
        return $utama !== null && trim($utama) !== '' ? trim($utama) : (string) $cadangan;
    }
}

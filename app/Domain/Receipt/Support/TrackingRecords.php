<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Support;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\Serial;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\GoodsReceiptLine;
use Carbon\Carbon;

/**
 * Isian pelacakan baris GRN vendor (BR-LED-03, BR-STK-09, BR-STK-12).
 *
 * Dua tugas: memeriksa isian draf sesuai mode pelacakan item, dan — saat GRN
 * `received` — membuat turunan lot/serial/potongan yang ditunjuk kartu stok.
 * Turunan tidak dibuat saat draf supaya draf yang dibatalkan tidak meninggalkan
 * serial yatim.
 */
class TrackingRecords
{
    /**
     * Menormalkan dan memeriksa satu baris isian draf.
     *
     * @param  array<string, mixed>  $line
     * @return array<string, mixed> kolom siap simpan
     */
    public function normalize(Item $item, array $line, int $index): array
    {
        $label = 'Baris '.($index + 1).' ('.$item->code.')';

        // BR-REQ-03: item sementara wajib dilengkapi Admin sebelum GRN pertama.
        if ($item->status !== ItemStatus::Active) {
            throw ReceiptRuleException::rule(
                'BR-REQ-03',
                $label.': item berstatus '.$item->status->label().' tidak bisa diterima. Lengkapi dan aktifkan item lebih dulu.',
            );
        }

        $qty = round((float) ($line['qty_received'] ?? 0), 4);
        $lot = $this->teks($line['lot_no'] ?? null);
        $serial = $this->teks($line['serial_no'] ?? null);
        $panjang = ($line['piece_length'] ?? null) === null || $line['piece_length'] === ''
            ? null
            : round((float) $line['piece_length'], 4);
        $kedaluwarsa = $this->tanggal($line['expiry_date'] ?? null, $label);

        $hasil = [
            'item_id' => $item->id,
            'qty_received' => $qty,
            'lot_no' => null,
            'expiry_date' => null,
            'serial_no' => null,
            'piece_length' => null,
            'notes' => $this->teks($line['notes'] ?? null),
        ];

        switch ($item->tracking_mode) {
            case TrackingMode::Lot:
                if ($lot === null) {
                    throw ReceiptRuleException::field('BR-LED-03', 'lot_no', $label.': nomor lot wajib diisi.');
                }

                if ($item->has_expiry && $kedaluwarsa === null) {
                    throw ReceiptRuleException::field('BR-STK-12', 'expiry_date', $label.': tanggal kedaluwarsa wajib diisi.');
                }

                $hasil['lot_no'] = mb_strtoupper($lot);
                $hasil['expiry_date'] = $kedaluwarsa;
                break;

            case TrackingMode::Serial:
                if ($serial === null) {
                    throw ReceiptRuleException::field('BR-LED-03', 'serial_no', $label.': nomor serial wajib diisi.');
                }

                // Satu baris = satu unit (BR-LED-04).
                $qty = 1.0;
                $hasil['qty_received'] = 1.0;
                $hasil['serial_no'] = mb_strtoupper($serial);
                $hasil['expiry_date'] = $item->has_expiry ? $kedaluwarsa : null;
                break;

            case TrackingMode::Piece:
                if ($panjang === null || $panjang <= 0) {
                    throw ReceiptRuleException::field('BR-STK-09', 'piece_length', $label.': panjang potongan wajib diisi.');
                }

                // Saldo potongan dalam satuan dasar panjang: jumlah = panjangnya.
                $qty = $panjang;
                $hasil['qty_received'] = $panjang;
                $hasil['piece_length'] = $panjang;
                break;

            case TrackingMode::None:
                break;
        }

        if ($qty <= 0) {
            throw ReceiptRuleException::field('BR-LED-02', 'qty_received', $label.': jumlah diterima harus lebih dari nol.');
        }

        return $hasil;
    }

    /**
     * Membuat lot, serial, atau potongan untuk baris yang akan diposting.
     *
     * @return array{lot_id: ?int, serial_id: ?int, piece_id: ?int}
     */
    public function materialize(GoodsReceipt $receipt, GoodsReceiptLine $line): array
    {
        $item = $line->item;

        return match ($item->tracking_mode) {
            TrackingMode::Lot => ['lot_id' => $this->lot($receipt, $line), 'serial_id' => null, 'piece_id' => null],
            TrackingMode::Serial => ['lot_id' => null, 'serial_id' => $this->serial($line), 'piece_id' => null],
            TrackingMode::Piece => ['lot_id' => null, 'serial_id' => null, 'piece_id' => $this->piece($receipt, $line)],
            TrackingMode::None => ['lot_id' => null, 'serial_id' => null, 'piece_id' => null],
        };
    }

    private function lot(GoodsReceipt $receipt, GoodsReceiptLine $line): int
    {
        $lot = Lot::query()->where('item_id', $line->item_id)->where('lot_no', $line->lot_no)->first();

        if ($lot === null) {
            return (int) Lot::create([
                'item_id' => $line->item_id,
                'lot_no' => $line->lot_no,
                'expiry_date' => $line->expiry_date?->toDateString(),
                'received_at' => now()->toDateString(),
                'vendor_id' => $receipt->vendor_id,
            ])->id;
        }

        // Lot yang sama dengan kedaluwarsa berbeda adalah dua lot yang salah dicatat.
        if ($line->expiry_date !== null && $lot->expiry_date !== null
            && $lot->expiry_date->toDateString() !== $line->expiry_date->toDateString()) {
            throw ReceiptRuleException::rule(
                'BR-STK-12',
                'Lot '.$lot->lot_no.' sudah tercatat dengan kedaluwarsa '.$lot->expiry_date->format('d/m/Y')
                .'; periksa kembali nomor lot atau tanggalnya.',
            );
        }

        if ($lot->expiry_date === null && $line->expiry_date !== null) {
            $lot->forceFill(['expiry_date' => $line->expiry_date->toDateString()])->save();
        }

        return (int) $lot->id;
    }

    private function serial(GoodsReceiptLine $line): int
    {
        $serial = Serial::query()->where('item_id', $line->item_id)->where('serial_no', $line->serial_no)->first();

        // Serial lama boleh masuk lagi (mis. pengganti yang sama dari vendor);
        // buku besar menolaknya bila ternyata masih ada di bin lain (BR-LED-04).
        if ($serial !== null) {
            return (int) $serial->id;
        }

        return (int) Serial::create([
            'item_id' => $line->item_id,
            'serial_no' => $line->serial_no,
            'expiry_date' => $line->expiry_date?->toDateString(),
            'acquired_at' => now()->toDateString(),
        ])->id;
    }

    private function piece(GoodsReceipt $receipt, GoodsReceiptLine $line): int
    {
        return (int) Piece::create([
            'item_id' => $line->item_id,
            'piece_no' => Piece::nextPieceNo(),
            'length' => (float) $line->piece_length,
            'is_offcut' => false,
            'origin_type' => 'grn',
            'origin_id' => $receipt->id,
        ])->id;
    }

    private function teks(mixed $nilai): ?string
    {
        $isi = is_scalar($nilai) ? trim((string) $nilai) : '';

        return $isi === '' ? null : $isi;
    }

    private function tanggal(mixed $nilai, string $label): ?string
    {
        $isi = $this->teks($nilai);

        if ($isi === null) {
            return null;
        }

        try {
            return Carbon::parse($isi)->toDateString();
        } catch (\Throwable) {
            throw ReceiptRuleException::field('BR-STK-12', 'expiry_date', $label.': tanggal kedaluwarsa tidak dikenali.');
        }
    }
}

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
        // A-287/A-288: bagian Rusak dicatat terpisah dari Baik (`qty_received`).
        $rusak = round((float) ($line['qty_damaged'] ?? 0), 4);
        $unitRusak = filter_var($line['damaged_unit'] ?? false, FILTER_VALIDATE_BOOLEAN);
        // A-297: nomor lot dibuat sistem dari GRN; yang diketik staf = nomor batch vendor.
        $batch = $this->teks($line['vendor_batch_no'] ?? $line['lot_no'] ?? null);
        $serial = $this->teks($line['serial_no'] ?? null);
        $panjang = ($line['piece_length'] ?? null) === null || $line['piece_length'] === ''
            ? null
            : round((float) $line['piece_length'], 4);
        $kedaluwarsa = $this->tanggal($line['expiry_date'] ?? null, $label);

        $hasil = [
            'item_id' => $item->id,
            'qty_received' => $qty,
            'qty_damaged' => $rusak,
            'lot_no' => null,
            'expiry_date' => null,
            'serial_no' => null,
            'piece_length' => null,
            'notes' => $this->teks($line['notes'] ?? null),
        ];

        switch ($item->tracking_mode) {
            case TrackingMode::Lot:
                if ($item->has_expiry && $kedaluwarsa === null) {
                    throw ReceiptRuleException::field('BR-STK-12', 'expiry_date', $label.': tanggal kedaluwarsa wajib diisi.');
                }

                // Nomor lot = nomor GRN + baris, diisi saat diterima (A-297).
                $hasil['vendor_batch_no'] = $batch === null ? null : mb_substr(mb_strtoupper($batch), 0, 60);
                $hasil['expiry_date'] = $kedaluwarsa;
                break;

            case TrackingMode::Serial:
                if ($serial === null) {
                    throw ReceiptRuleException::field('BR-LED-03', 'serial_no', $label.': nomor serial wajib diisi.');
                }

                // Satu baris = satu unit (BR-LED-04); unit rusak dicatat utuh sebagai Rusak.
                $qty = $unitRusak ? 0.0 : 1.0;
                $rusak = $unitRusak ? 1.0 : 0.0;
                $hasil['qty_received'] = $qty;
                $hasil['qty_damaged'] = $rusak;
                $hasil['serial_no'] = mb_strtoupper($serial);
                $hasil['expiry_date'] = $item->has_expiry ? $kedaluwarsa : null;
                break;

            case TrackingMode::Piece:
                if ($panjang === null || $panjang <= 0) {
                    throw ReceiptRuleException::field('BR-STK-09', 'piece_length', $label.': panjang potongan wajib diisi.');
                }

                // Saldo potongan dalam satuan dasar panjang: jumlah = panjangnya.
                $qty = $unitRusak ? 0.0 : $panjang;
                $rusak = $unitRusak ? $panjang : 0.0;
                $hasil['qty_received'] = $qty;
                $hasil['qty_damaged'] = $rusak;
                $hasil['piece_length'] = $panjang;
                break;

            case TrackingMode::None:
                break;
        }

        if ($qty < 0 || $rusak < 0) {
            throw ReceiptRuleException::field('BR-LED-02', 'qty_received', $label.': jumlah tidak boleh negatif.');
        }

        if ($qty + $rusak <= 0) {
            throw ReceiptRuleException::field('BR-LED-02', 'qty_received', $label.': jumlah diterima (baik + rusak) harus lebih dari nol.');
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

    /**
     * A-297: GRN vendor membuat lot sendiri per baris — `nomor GRN-NN` (NN = urutan
     * baris) — supaya vendor, tanggal, dan pesanan setiap lot pasti tepat. Nomor
     * batch vendor disimpan di `lots.attributes.vendor_batch`. Baris lama yang
     * sudah membawa nomor lot (draf sebelum A-297) tetap memakainya.
     */
    private function lot(GoodsReceipt $receipt, GoodsReceiptLine $line): int
    {
        if ($line->lot_no === null || $line->lot_no === '') {
            $urutan = GoodsReceiptLine::query()->where('goods_receipt_id', $receipt->id)->where('id', '<=', $line->id)->count();
            $line->forceFill(['lot_no' => mb_substr($receipt->number.'-'.str_pad((string) $urutan, 2, '0', STR_PAD_LEFT), 0, 60)])->save();
        }

        $lot = Lot::query()->where('item_id', $line->item_id)->where('lot_no', $line->lot_no)->first();

        if ($lot === null) {
            return (int) Lot::create([
                'item_id' => $line->item_id,
                'lot_no' => $line->lot_no,
                'expiry_date' => $line->expiry_date?->toDateString(),
                'received_at' => now()->toDateString(),
                'vendor_id' => $receipt->vendor_id,
                'attributes' => $line->vendor_batch_no === null ? null : ['vendor_batch' => $line->vendor_batch_no],
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

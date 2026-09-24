<?php

declare(strict_types=1);

namespace App\Domain\Adjustment\Support;

use App\Domain\Adjustment\Exceptions\AdjustmentRuleException;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Serial;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Models\Bin;
use Carbon\Carbon;

/**
 * Memeriksa dan menormalkan isian baris ADJ manual (21-opname-penyesuaian §5).
 *
 * Satu baris = satu bin × item × lot/serial/potongan × kondisi, dengan arah
 * `in` (tambah) atau `out` (kurangi). Barang keluar harus menunjuk saldo yang
 * ada dan cukup (BR-STK-06); barang masuk boleh menyebut lot/serial yang
 * belum ada — turunannya baru dibuat saat ADJ diposting, supaya ADJ yang
 * ditolak tidak meninggalkan serial yatim (A-102).
 */
class AdjustmentLines
{
    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>> kolom siap simpan (tanpa stock_adjustment_id)
     */
    public function normalize(int $warehouseId, array $lines): array
    {
        $hasil = [];
        $keluar = [];

        foreach (array_values($lines) as $i => $isi) {
            if ($this->kosong($isi)) {
                continue;
            }

            $label = 'Baris '.($i + 1);
            $bin = Bin::withoutGlobalScopes()->find((int) ($isi['bin_id'] ?? 0));

            if ($bin === null || (int) $bin->warehouse_id !== $warehouseId) {
                throw AdjustmentRuleException::field('BR-STK-02', 'bin_id', $label.': bin wajib dipilih dari gudang ADJ.');
            }

            if ($bin->bin_type->isVirtual()) {
                throw AdjustmentRuleException::rule(
                    'BR-SJ-10',
                    $label.': bin '.$bin->code.' adalah lokasi virtual; selisih barang dalam perjalanan diselesaikan lewat DSC, bukan ADJ.',
                );
            }

            if ($bin->bin_status === BinStatus::Frozen) {
                throw AdjustmentRuleException::rule('BR-OPN-02', $label.': bin '.$bin->code.' sedang dibeku untuk opname.');
            }

            if ($bin->bin_status === BinStatus::Inactive) {
                throw AdjustmentRuleException::rule('BR-WH-07', $label.': bin '.$bin->code.' nonaktif.');
            }

            $item = Item::query()->find((int) ($isi['item_id'] ?? 0));

            if ($item === null) {
                throw AdjustmentRuleException::field('BR-GEN-11', 'item_id', $label.': item wajib dipilih.');
            }

            $label .= ' ('.$item->code.')';
            $arah = ($isi['direction'] ?? '') === 'out' ? 'out' : (($isi['direction'] ?? '') === 'in' ? 'in' : null);

            if ($arah === null) {
                throw AdjustmentRuleException::field('BR-GEN-11', 'direction', $label.': arah (tambah/kurangi) wajib dipilih.');
            }

            if ($arah === 'in' && $item->status !== ItemStatus::Active) {
                throw AdjustmentRuleException::rule('BR-REQ-03', $label.': item berstatus '.$item->status->label().' tidak bisa ditambahkan ke stok.');
            }

            $status = StockStatus::tryFrom((string) ($isi['stock_status'] ?? '')) ?? StockStatus::Available;
            $qty = round((float) ($isi['qty'] ?? 0), 4);

            $baris = [
                'item_id' => $item->id,
                'bin_id' => $bin->id,
                'lot_id' => null,
                'serial_id' => null,
                'piece_id' => null,
                'lot_no' => null,
                'expiry_date' => null,
                'serial_no' => null,
                'piece_length' => null,
                'stock_status' => $status->value,
                'reason_code_id' => $this->alasanBaris($isi['reason_code_id'] ?? null, $label),
                'notes' => $this->teks($isi['notes'] ?? null),
            ];

            [$baris, $qty] = $this->pelacakan($item, $arah, $baris, $qty, $isi, $bin, $label);

            if ($qty <= 0) {
                throw AdjustmentRuleException::field('BR-LED-02', 'qty', $label.': jumlah harus lebih dari nol.');
            }

            if ($arah === 'out') {
                $kunci = implode('|', [$bin->id, $item->id, $baris['lot_id'], $baris['serial_id'], $baris['piece_id'], $status->value]);
                $keluar[$kunci] = ($keluar[$kunci] ?? 0) + $qty;
                $this->periksaSaldo($baris, $status, $keluar[$kunci], $bin, $label);
            }

            $baris['qty_delta'] = $arah === 'out' ? -$qty : $qty;
            $hasil[] = $baris;
        }

        if ($hasil === []) {
            throw AdjustmentRuleException::rule('BR-GEN-11', 'Isi minimal satu baris penyesuaian.');
        }

        return $hasil;
    }

    /** Alasan dokumen: master Alasan konteks penyesuaian (BR-GEN-02). */
    public function headerReason(mixed $id): int
    {
        $valid = $id !== null && $id !== '' && ReasonCode::query()->whereKey((int) $id)
            ->where('context', ReasonContext::Adjustment->value)->exists();

        if (! $valid) {
            throw AdjustmentRuleException::field('BR-GEN-02', 'reason_code_id', 'Alasan penyesuaian wajib dipilih.');
        }

        return (int) $id;
    }

    /**
     * @param  array<string, mixed>  $baris
     * @param  array<string, mixed>  $isi
     * @return array{0: array<string, mixed>, 1: float}
     */
    private function pelacakan(Item $item, string $arah, array $baris, float $qty, array $isi, Bin $bin, string $label): array
    {
        $lotNo = $this->kode($isi['lot_no'] ?? null);
        $serialNo = $this->kode($isi['serial_no'] ?? null);

        switch ($item->tracking_mode) {
            case TrackingMode::Lot:
                if ($lotNo === null) {
                    throw AdjustmentRuleException::field('BR-LED-03', 'lot_no', $label.': nomor lot wajib diisi.');
                }

                $lot = Lot::query()->where('item_id', $item->id)->where('lot_no', $lotNo)->first();

                if ($lot !== null) {
                    $baris['lot_id'] = $lot->id;
                } elseif ($arah === 'out') {
                    throw AdjustmentRuleException::field('BR-LED-03', 'lot_no', $label.': lot '.$lotNo.' tidak ditemukan.');
                } else {
                    $baris['lot_no'] = $lotNo;
                    $baris['expiry_date'] = $this->tanggal($isi['expiry_date'] ?? null, $label);

                    if ($item->has_expiry && $baris['expiry_date'] === null) {
                        throw AdjustmentRuleException::field('BR-STK-12', 'expiry_date', $label.': lot baru wajib bertanggal kedaluwarsa.');
                    }
                }
                break;

            case TrackingMode::Serial:
                if ($serialNo === null) {
                    throw AdjustmentRuleException::field('BR-LED-03', 'serial_no', $label.': nomor serial wajib diisi.');
                }

                $qty = 1.0; // BR-LED-04: satu serial = satu unit.
                $serial = Serial::query()->where('item_id', $item->id)->where('serial_no', $serialNo)->first();

                if ($serial !== null) {
                    $baris['serial_id'] = $serial->id;

                    if ($arah === 'in' && StockBalance::query()->where('serial_id', $serial->id)->where('qty_base', '>', 0)->exists()) {
                        throw AdjustmentRuleException::rule('BR-LED-04', $label.': serial '.$serialNo.' masih tercatat di sebuah bin.');
                    }
                } elseif ($arah === 'out') {
                    throw AdjustmentRuleException::field('BR-LED-03', 'serial_no', $label.': serial '.$serialNo.' tidak ditemukan.');
                } else {
                    $baris['serial_no'] = $serialNo;
                }
                break;

            case TrackingMode::Piece:
                if ($arah === 'out') {
                    $nomor = $this->kode($isi['piece_no'] ?? null);
                    $potongan = $nomor === null ? null : Piece::query()->where('item_id', $item->id)->where('piece_no', $nomor)->first();

                    if ($potongan === null) {
                        throw AdjustmentRuleException::field('BR-LED-03', 'piece_no', $label.': nomor potongan wajib menunjuk potongan yang ada.');
                    }

                    $baris['piece_id'] = $potongan->id;

                    // Satu potongan keluar utuh: jumlahnya = sisa saldonya di bin itu.
                    $qty = round((float) StockBalance::query()->where('piece_id', $potongan->id)
                        ->where('bin_id', $bin->id)->sum('qty_base'), 4);

                    if ($qty <= 0) {
                        throw AdjustmentRuleException::rule('BR-STK-06', $label.': potongan '.$nomor.' tidak ada di bin '.$bin->code.'.');
                    }
                } else {
                    $panjang = round((float) ($isi['piece_length'] ?? 0), 4);

                    if ($panjang <= 0) {
                        throw AdjustmentRuleException::field('BR-STK-09', 'piece_length', $label.': panjang potongan wajib diisi.');
                    }

                    $baris['piece_length'] = $panjang;
                    $qty = $panjang;
                }
                break;

            case TrackingMode::None:
                break;
        }

        return [$baris, $qty];
    }

    /** @param  array<string, mixed>  $baris */
    private function periksaSaldo(array $baris, StockStatus $status, float $qty, Bin $bin, string $label): void
    {
        $saldo = (float) StockBalance::query()
            ->where('item_id', $baris['item_id'])
            ->where('bin_id', $bin->id)
            ->where('stock_status', $status->value)
            ->when($baris['lot_id'] !== null, fn ($q) => $q->where('lot_id', $baris['lot_id']), fn ($q) => $q->whereNull('lot_id'))
            ->when($baris['serial_id'] !== null, fn ($q) => $q->where('serial_id', $baris['serial_id']), fn ($q) => $q->whereNull('serial_id'))
            ->when($baris['piece_id'] !== null, fn ($q) => $q->where('piece_id', $baris['piece_id']), fn ($q) => $q->whereNull('piece_id'))
            ->sum('qty_base');

        if ($qty - $saldo > 0.00005) {
            throw AdjustmentRuleException::field(
                'BR-STK-06',
                'qty',
                $label.': saldo '.$status->label().' di bin '.$bin->code.' hanya '.$this->angka($saldo).', tidak cukup untuk dikurangi '.$this->angka($qty).'.',
            );
        }
    }

    private function alasanBaris(mixed $id, string $label): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }

        if (! ReasonCode::query()->whereKey((int) $id)->where('context', ReasonContext::Adjustment->value)->exists()) {
            throw AdjustmentRuleException::field('BR-GEN-02', 'reason_code_id', $label.': alasan baris tidak dikenal.');
        }

        return (int) $id;
    }

    /** @param  array<string, mixed>  $isi */
    private function kosong(array $isi): bool
    {
        return ($isi['item_id'] ?? '') === '' && ($isi['bin_id'] ?? '') === '' && ($isi['qty'] ?? '') === '';
    }

    private function teks(mixed $nilai): ?string
    {
        $isi = is_scalar($nilai) ? trim((string) $nilai) : '';

        return $isi === '' ? null : mb_substr($isi, 0, 255);
    }

    private function kode(mixed $nilai): ?string
    {
        $isi = $this->teks($nilai);

        return $isi === null ? null : mb_strtoupper($isi);
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
            throw AdjustmentRuleException::field('BR-STK-12', 'expiry_date', $label.': tanggal kedaluwarsa tidak dikenali.');
        }
    }

    private function angka(float $n): string
    {
        return rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Issue\Support;

use App\Domain\Issue\Exceptions\IssueRuleException;
use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Stock\Enums\ReservationLevel;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Collection;

/**
 * Stok Gudang Site yang boleh dipakai proyek (BR-PRJ-08, A-117).
 *
 * Calon = saldo Tersedia di bin **penyimpanan** Gudang Site (sama dengan
 * sumber Stok Tersedia, A-85), satu per bin × item × lot/serial/potongan,
 * dikurangi alokasi keras pada kombinasi itu. Item aset (`asset`/`both`)
 * ikut terbaca supaya penolakannya jelas, tetapi tidak pernah boleh dipakai
 * (BR-STK-08: aset tidak dipakai habis).
 *
 * Form mengirim kunci + jumlah; aksi menghitung ulang daftar ini, jadi batas
 * jumlah tidak pernah dipercaya dari peramban.
 */
class IssuableStock
{
    public function __construct(private readonly StockLedger $ledger) {}

    public static function key(int $binId, int $itemId, ?int $lotId, ?int $serialId, ?int $pieceId): string
    {
        return implode(':', [$binId, $itemId, $lotId ?? 0, $serialId ?? 0, $pieceId ?? 0]);
    }

    /**
     * @return Collection<string, array<string, mixed>> kunci => calon
     */
    public function forWarehouse(Warehouse $site): Collection
    {
        $bins = Bin::query()->withoutGlobalScopes()
            ->where('warehouse_id', $site->id)
            ->where('bin_type', BinType::Storage->value)
            ->where('bin_status', '!=', BinStatus::Inactive->value)
            ->get()
            ->keyBy('id');

        if ($bins->isEmpty()) {
            return collect();
        }

        return StockBalance::query()
            ->with('item:id,code,name,tracking_mode,ownership_model,base_uom_id', 'item.baseUom:id,code', 'lot:id,lot_no', 'serial:id,serial_no', 'piece:id,piece_no,length')
            ->where('stock_status', StockStatus::Available->value)
            ->nonZero()
            ->whereIn('bin_id', $bins->keys())
            ->get()
            ->map(function (StockBalance $s) use ($bins, $site): array {
                $bin = $bins->get($s->bin_id);
                $keras = (float) StockReservation::query()->active()
                    ->where('level', ReservationLevel::Hard->value)
                    ->where('bin_id', $s->bin_id)->where('item_id', $s->item_id)
                    ->where(fn ($q) => $s->lot_id === null ? $q->whereNull('lot_id') : $q->where('lot_id', $s->lot_id))
                    ->where(fn ($q) => $s->serial_id === null ? $q->whereNull('serial_id') : $q->where('serial_id', $s->serial_id))
                    ->where(fn ($q) => $s->piece_id === null ? $q->whereNull('piece_id') : $q->where('piece_id', $s->piece_id))
                    ->sum('qty_base');

                $item = $s->item;

                return [
                    'key' => self::key((int) $s->bin_id, (int) $s->item_id, $s->lot_id, $s->serial_id, $s->piece_id),
                    'warehouse_id' => (int) $site->id,
                    'bin_id' => (int) $s->bin_id,
                    'bin_code' => $bin?->code,
                    'item_id' => (int) $s->item_id,
                    'item_code' => $item?->code,
                    'item_name' => $item?->name,
                    'uom' => $item?->baseUom?->code,
                    'lot_id' => $s->lot_id === null ? null : (int) $s->lot_id,
                    'serial_id' => $s->serial_id === null ? null : (int) $s->serial_id,
                    'piece_id' => $s->piece_id === null ? null : (int) $s->piece_id,
                    'tracking' => (string) ($s->lot?->lot_no ?? $s->serial?->serial_no ?? $s->piece?->piece_no ?? ''),
                    'tracking_mode' => $item?->tracking_mode,
                    'balance' => round((float) $s->qty_base, 4),
                    'max' => round(max(0, (float) $s->qty_base - $keras), 4),
                    'frozen' => $bin !== null && ! $bin->acceptsMovement(),
                    'asset' => $item?->isAsset() ?? false,
                ];
            })
            ->sortBy(fn (array $c) => $c['item_code'].'|'.$c['bin_code'].'|'.$c['tracking'])
            ->keyBy('key');
    }

    /** Calon yang boleh dipilih di layar: bukan aset. */
    public function selectable(Warehouse $site): Collection
    {
        return $this->forWarehouse($site)->reject(fn (array $c) => $c['asset']);
    }

    /**
     * Mengubah isian form (kunci + jumlah + catatan pemakaian) menjadi baris
     * ISU yang sah, sekaligus memeriksa stok tersedia (Katalog §2.9 guard).
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    public function normalize(Warehouse $site, array $lines): array
    {
        $calon = $this->forWarehouse($site);
        $baris = [];

        foreach ($lines as $i => $isian) {
            $kunci = trim((string) ($isian['key'] ?? ''));
            $jumlah = is_numeric($isian['qty_base'] ?? null) ? round((float) $isian['qty_base'], 4) : 0.0;

            if ($kunci === '' && $jumlah == 0.0) {
                continue;
            }

            $c = $calon->get($kunci) ?? $this->tolakKunci($site, $kunci);

            if ($c['asset']) {
                throw IssueRuleException::field('BR-PRJ-08', 'key', 'Item '.$c['item_code'].' adalah aset; aset tidak dipakai habis dan kembali lewat retur (BR-STK-08).');
            }

            if ($c['frozen']) {
                throw IssueRuleException::field('BR-OPN-02', 'key', 'Bin '.$c['bin_code'].' sedang dibeku opname dan menolak ISU baru.');
            }

            if ($jumlah <= 0) {
                throw IssueRuleException::field('BR-LED-02', 'qty_base', 'Jumlah pemakaian '.$c['item_code'].' harus lebih besar dari nol.');
            }

            $catatan = trim((string) ($isian['work_note'] ?? ''));

            if (isset($baris[$kunci])) {
                $baris[$kunci]['qty_base'] = round($baris[$kunci]['qty_base'] + $jumlah, 4);
                $baris[$kunci]['work_note'] ??= $catatan === '' ? null : mb_substr($catatan, 0, 255);
            } else {
                $baris[$kunci] = [
                    'item_id' => $c['item_id'],
                    'bin_id' => $c['bin_id'],
                    'lot_id' => $c['lot_id'],
                    'serial_id' => $c['serial_id'],
                    'piece_id' => $c['piece_id'],
                    'qty_base' => $jumlah,
                    'work_note' => $catatan === '' ? null : mb_substr($catatan, 0, 255),
                ];
            }
        }

        if ($baris === []) {
            throw IssueRuleException::field('BR-PRJ-08', 'key', 'Isi jumlah pemakaian minimal satu barang dari stok Gudang Site ini.');
        }

        $this->assertAvailable($site, array_values($baris), $calon);

        return array_values($baris);
    }

    /**
     * Guard `draft → confirmed` (Katalog §2.9 "stok tersedia cukup"): per
     * kombinasi bin tidak melebihi saldo dikurangi alokasi keras, per item tidak
     * melebihi Stok Tersedia gudang (reservasi lunak ikut dihitung, BR-STK-03);
     * serial per unit, potongan utuh (A-117).
     *
     * @param  array<int, array<string, mixed>>  $rows  item_id, bin_id, lot_id, serial_id, piece_id, qty_base
     * @param  Collection<string, array<string, mixed>>|null  $calon
     */
    public function assertAvailable(Warehouse $site, array $rows, ?Collection $calon = null): void
    {
        $calon ??= $this->forWarehouse($site);
        $perKunci = [];
        $perItem = [];

        foreach ($rows as $r) {
            $kunci = self::key((int) $r['bin_id'], (int) $r['item_id'], $r['lot_id'] ?? null, $r['serial_id'] ?? null, $r['piece_id'] ?? null);
            $perKunci[$kunci] = round(($perKunci[$kunci] ?? 0) + (float) $r['qty_base'], 4);
            $perItem[(int) $r['item_id']] = round(($perItem[(int) $r['item_id']] ?? 0) + (float) $r['qty_base'], 4);
        }

        foreach ($perKunci as $kunci => $jumlah) {
            $c = $calon->get($kunci) ?? $this->tolakKunci($site, $kunci);

            if ($c['asset']) {
                throw IssueRuleException::field('BR-PRJ-08', 'key', 'Item '.$c['item_code'].' adalah aset; aset tidak dipakai habis (BR-STK-08).');
            }

            if ($c['frozen']) {
                throw IssueRuleException::field('BR-OPN-02', 'key', 'Bin '.$c['bin_code'].' sedang dibeku opname dan menolak ISU baru.');
            }

            if ($c['tracking_mode'] === TrackingMode::Serial && abs($jumlah - 1.0) > 0.00005) {
                throw IssueRuleException::field('BR-LED-04', 'qty_base', 'Serial '.$c['tracking'].' dipakai per unit: jumlahnya 1.');
            }

            if ($c['tracking_mode'] === TrackingMode::Piece && abs($jumlah - $c['balance']) > 0.00005) {
                throw IssueRuleException::field('BR-STK-09', 'qty_base', 'Potongan '.$c['tracking'].' dipakai utuh ('.$this->angka($c['balance']).' '.$c['uom'].'); memotong lewat konversi.');
            }

            if ($jumlah - $c['max'] > 0.00005) {
                throw IssueRuleException::field('BR-STK-06', 'qty_base', 'Stok '.$c['item_code'].' di bin '.$c['bin_code'].' tidak cukup: tersedia '.$this->angka($c['max']).', diminta '.$this->angka($jumlah).'.');
            }
        }

        foreach ($perItem as $itemId => $jumlah) {
            $tersedia = $this->ledger->availableQty($itemId, (int) $site->id);

            if ($jumlah - $tersedia > 0.00005) {
                $kode = Item::query()->whereKey($itemId)->value('code');

                throw IssueRuleException::field('BR-STK-03', 'qty_base', 'Stok tersedia '.$kode.' di '.$site->code.' hanya '.$this->angka(max(0, $tersedia)).' (sebagian dicadangkan dokumen lain); diminta '.$this->angka($jumlah).'.');
            }
        }
    }

    private function tolakKunci(Warehouse $site, string $kunci): never
    {
        $bagian = explode(':', $kunci);
        $item = count($bagian) === 5 ? Item::query()->find((int) $bagian[1]) : null;

        if ($item !== null && $item->ownership_model !== OwnershipModel::Consumable) {
            throw IssueRuleException::field('BR-PRJ-08', 'key', 'Item '.$item->code.' adalah aset; aset tidak dipakai habis (BR-STK-08).');
        }

        throw IssueRuleException::field('BR-PRJ-08', 'key', 'Barang yang dipilih bukan stok Tersedia di bin penyimpanan '.$site->code.'.');
    }

    private function angka(float $n): string
    {
        return rtrim(rtrim(number_format($n, 4, ',', '.'), '0'), ',');
    }
}

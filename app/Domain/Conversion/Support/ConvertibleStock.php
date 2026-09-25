<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Support;

use App\Domain\Conversion\Exceptions\ConversionRuleException;
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
 * Stok gudang yang boleh menjadi **input** konversi (A-154).
 *
 * Calon = saldo Tersedia di bin **penyimpanan** gudang (sumber Stok Tersedia,
 * A-85), satu per bin × item × lot/potongan, dikurangi alokasi keras pada
 * kombinasi itu. Item aset dan item berserial ikut terbaca supaya
 * penolakannya jelas, tetapi tidak pernah boleh dikonversi (BR-STK-08; ERD
 * `conversion_inputs` tidak punya serial).
 *
 * Form mengirim kunci + jumlah; aksi menghitung ulang daftar ini, jadi batas
 * jumlah tidak pernah dipercaya dari peramban.
 */
class ConvertibleStock
{
    public function __construct(private readonly StockLedger $ledger) {}

    public static function key(int $binId, int $itemId, ?int $lotId, ?int $pieceId): string
    {
        return implode(':', [$binId, $itemId, $lotId ?? 0, $pieceId ?? 0]);
    }

    /**
     * @return Collection<string, array<string, mixed>> kunci => calon
     */
    public function forWarehouse(Warehouse $gudang): Collection
    {
        $bins = Bin::query()->withoutGlobalScopes()
            ->where('warehouse_id', $gudang->id)
            ->where('bin_type', BinType::Storage->value)
            ->where('bin_status', '!=', BinStatus::Inactive->value)
            ->get()
            ->keyBy('id');

        if ($bins->isEmpty()) {
            return collect();
        }

        return StockBalance::query()
            ->with('item:id,code,name,tracking_mode,ownership_model,base_uom_id,min_offcut_length,kerf,is_cuttable', 'item.baseUom:id,code,rounding', 'lot:id,lot_no', 'piece:id,piece_no,length,is_offcut')
            ->where('stock_status', StockStatus::Available->value)
            ->nonZero()
            ->whereIn('bin_id', $bins->keys())
            ->whereNull('serial_id')
            ->get()
            ->map(function (StockBalance $s) use ($bins, $gudang): array {
                $bin = $bins->get($s->bin_id);
                $keras = (float) StockReservation::query()->active()
                    ->where('level', ReservationLevel::Hard->value)
                    ->where('bin_id', $s->bin_id)->where('item_id', $s->item_id)
                    ->where(fn ($q) => $s->lot_id === null ? $q->whereNull('lot_id') : $q->where('lot_id', $s->lot_id))
                    ->where(fn ($q) => $s->piece_id === null ? $q->whereNull('piece_id') : $q->where('piece_id', $s->piece_id))
                    ->sum('qty_base');

                $item = $s->item;

                return [
                    'key' => self::key((int) $s->bin_id, (int) $s->item_id, $s->lot_id, $s->piece_id),
                    'warehouse_id' => (int) $gudang->id,
                    'bin_id' => (int) $s->bin_id,
                    'bin_code' => $bin?->code,
                    'item_id' => (int) $s->item_id,
                    'item_code' => $item?->code,
                    'item_name' => $item?->name,
                    'uom' => $item?->baseUom?->code,
                    'base_uom_id' => (int) $item?->base_uom_id,
                    'lot_id' => $s->lot_id === null ? null : (int) $s->lot_id,
                    'lot_no' => $s->lot?->lot_no,
                    'piece_id' => $s->piece_id === null ? null : (int) $s->piece_id,
                    'is_offcut' => (bool) $s->piece?->is_offcut,
                    'tracking' => (string) ($s->lot?->lot_no ?? $s->piece?->piece_no ?? ''),
                    'tracking_mode' => $item?->tracking_mode,
                    'balance' => round((float) $s->qty_base, 4),
                    'max' => round(max(0, (float) $s->qty_base - $keras), 4),
                    'frozen' => $bin !== null && ! $bin->acceptsMovement(),
                    'asset' => $item?->isAsset() ?? false,
                    'cuttable' => (bool) $item?->is_cuttable,
                    'min_offcut' => $item?->min_offcut_length === null ? null : (float) $item->min_offcut_length,
                    'kerf' => $item?->kerf === null ? null : (float) $item->kerf,
                ];
            })
            ->sortBy(fn (array $c) => $c['item_code'].'|'.$c['bin_code'].'|'.($c['is_offcut'] ? '0' : '1').'|'.$c['tracking'])
            ->keyBy('key');
    }

    /** Calon yang boleh dipilih di layar: bukan aset, dan item ditandai Bisa dipotong/dikonversi. */
    public function selectable(Warehouse $gudang): Collection
    {
        return $this->forWarehouse($gudang)->reject(fn (array $c) => $c['asset'] || ! $c['cuttable']);
    }

    /**
     * Isian form (kunci + jumlah) menjadi baris input yang sah.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<string, array<string, mixed>> kunci => baris
     */
    public function normalize(Warehouse $gudang, array $lines): array
    {
        $calon = $this->forWarehouse($gudang);
        $baris = [];

        foreach ($lines as $isian) {
            $kunci = trim((string) ($isian['key'] ?? ''));
            $jumlah = is_numeric($isian['qty_base'] ?? null) ? round((float) $isian['qty_base'], 4) : 0.0;

            if ($kunci === '' && $jumlah == 0.0) {
                continue;
            }

            $c = $calon->get($kunci) ?? $this->tolakKunci($gudang, $kunci);
            $this->periksaCalon($c);

            if ($jumlah <= 0) {
                throw ConversionRuleException::field('BR-LED-02', 'inputs', 'Jumlah input '.$c['item_code'].' harus lebih besar dari nol.');
            }

            $baris[$kunci] = [
                'key' => $kunci,
                'item_id' => $c['item_id'],
                'bin_id' => $c['bin_id'],
                'lot_id' => $c['lot_id'],
                'piece_id' => $c['piece_id'],
                'qty_base' => round(($baris[$kunci]['qty_base'] ?? 0) + $jumlah, 4),
            ];
        }

        if ($baris === []) {
            throw ConversionRuleException::field('BR-CNV-02', 'inputs', 'Pilih minimal satu input dari stok Tersedia gudang ini.');
        }

        $this->assertAvailable($gudang, array_values($baris), $calon);

        return $baris;
    }

    /**
     * Guard Katalog §2.10 "input tersedia": per kombinasi bin tidak melebihi
     * saldo dikurangi alokasi keras, per item tidak melebihi Stok Tersedia
     * gudang (reservasi lunak ikut, BR-STK-03); potongan dipakai utuh.
     *
     * @param  array<int, array<string, mixed>>  $rows  item_id, bin_id, lot_id, piece_id, qty_base
     * @param  Collection<string, array<string, mixed>>|null  $calon
     */
    public function assertAvailable(Warehouse $gudang, array $rows, ?Collection $calon = null): void
    {
        $calon ??= $this->forWarehouse($gudang);
        $perKunci = [];
        $perItem = [];

        foreach ($rows as $r) {
            $kunci = self::key((int) $r['bin_id'], (int) $r['item_id'], $r['lot_id'] ?? null, $r['piece_id'] ?? null);
            $perKunci[$kunci] = round(($perKunci[$kunci] ?? 0) + (float) $r['qty_base'], 4);
            $perItem[(int) $r['item_id']] = round(($perItem[(int) $r['item_id']] ?? 0) + (float) $r['qty_base'], 4);
        }

        foreach ($perKunci as $kunci => $jumlah) {
            $c = $calon->get($kunci) ?? $this->tolakKunci($gudang, $kunci);
            $this->periksaCalon($c);

            if ($c['tracking_mode'] === TrackingMode::Piece && abs($jumlah - $c['balance']) > 0.00005) {
                throw ConversionRuleException::field('BR-STK-09', 'inputs', 'Potongan '.$c['tracking'].' dipakai utuh ('.self::angka($c['balance']).' '.$c['uom'].'); sisanya dicatat sebagai offcut, waste, atau kerf.');
            }

            if ($jumlah - $c['max'] > 0.00005) {
                throw ConversionRuleException::field('BR-STK-06', 'inputs', 'Stok '.$c['item_code'].' di bin '.$c['bin_code'].' tidak cukup: tersedia '.self::angka($c['max']).', diminta '.self::angka($jumlah).'.');
            }
        }

        foreach ($perItem as $itemId => $jumlah) {
            $tersedia = $this->ledger->availableQty($itemId, (int) $gudang->id);

            if ($jumlah - $tersedia > 0.00005) {
                $kode = Item::query()->whereKey($itemId)->value('code');

                throw ConversionRuleException::field('BR-STK-03', 'inputs', 'Stok tersedia '.$kode.' di '.$gudang->code.' hanya '.self::angka(max(0, $tersedia)).' (sebagian dicadangkan dokumen lain); diminta '.self::angka($jumlah).'.');
            }
        }
    }

    /** @param  array<string, mixed>  $c */
    private function periksaCalon(array $c): void
    {
        if ($c['asset']) {
            throw ConversionRuleException::field('BR-STK-08', 'inputs', 'Item '.$c['item_code'].' adalah aset; aset tidak dikonversi.');
        }

        // Master item "Bisa dipotong/dikonversi" (Blueprint §6.4, A-154).
        if (! $c['cuttable']) {
            throw ConversionRuleException::field('BR-CNV-03', 'inputs', 'Item '.$c['item_code'].' tidak ditandai Bisa dipotong/dikonversi di master item.');
        }

        if ($c['frozen']) {
            throw ConversionRuleException::field('BR-OPN-02', 'inputs', 'Bin '.$c['bin_code'].' sedang dibeku opname dan menolak konversi.');
        }
    }

    private function tolakKunci(Warehouse $gudang, string $kunci): never
    {
        $bagian = explode(':', $kunci);
        $item = count($bagian) === 4 ? Item::query()->find((int) $bagian[1]) : null;

        if ($item !== null && $item->ownership_model !== OwnershipModel::Consumable) {
            throw ConversionRuleException::field('BR-STK-08', 'inputs', 'Item '.$item->code.' adalah aset; aset tidak dikonversi.');
        }

        if ($item !== null && $item->tracking_mode === TrackingMode::Serial) {
            throw ConversionRuleException::field('BR-LED-03', 'inputs', 'Item berserial '.$item->code.' tidak dikonversi di Fase 1 (A-154).');
        }

        throw ConversionRuleException::field('BR-CNV-01', 'inputs', 'Input yang dipilih bukan stok Tersedia di bin penyimpanan '.$gudang->code.'.');
    }

    public static function angka(float $n): string
    {
        return rtrim(rtrim(number_format($n, 4, ',', '.'), '0'), ',');
    }
}

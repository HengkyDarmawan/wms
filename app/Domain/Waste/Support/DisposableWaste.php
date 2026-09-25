<?php

declare(strict_types=1);

namespace App\Domain\Waste\Support;

use App\Domain\Master\Models\Item;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Waste\Enums\WasteDisposalStatus;
use App\Domain\Waste\Exceptions\WasteRuleException;
use App\Domain\Waste\Models\WasteDisposalLine;
use Illuminate\Support\Collection;

/**
 * Isi bin Waste gudang yang boleh masuk WST (Katalog §2.14 "baris dari bin
 * Waste", A-159).
 *
 * Calon = saldo di bin Waste (semua kondisi), satu per bin × item × lot ×
 * serial × potongan × kondisi, dikurangi jumlah yang sudah dipegang WST lain
 * yang masih terbuka (diajukan, menunggu, disetujui). Aset tidak ditutup lewat
 * WST — aset rusak/hilang diproses lewat ADJ (BR-AST-04).
 */
class DisposableWaste
{
    public static function key(int $binId, int $itemId, ?int $lotId, ?int $serialId, ?int $pieceId, string $status): string
    {
        return implode(':', [$binId, $itemId, $lotId ?? 0, $serialId ?? 0, $pieceId ?? 0, $status]);
    }

    /** @return Collection<string, array<string, mixed>> kunci => calon */
    public function forWarehouse(Warehouse $gudang, ?int $exceptId = null): Collection
    {
        $bins = Bin::query()->withoutGlobalScopes()
            ->where('warehouse_id', $gudang->id)
            ->where('bin_type', BinType::Waste->value)
            ->get()->keyBy('id');

        if ($bins->isEmpty()) {
            return collect();
        }

        $dipegang = WasteDisposalLine::query()
            ->whereHas('wasteDisposal', fn ($q) => $q->withoutGlobalScopes()
                ->whereIn('status', WasteDisposalStatus::holdingValues())
                ->when($exceptId !== null, fn ($q) => $q->whereKeyNot($exceptId)))
            ->whereIn('bin_id', $bins->keys())
            ->get()
            ->groupBy(fn (WasteDisposalLine $l) => self::key((int) $l->bin_id, (int) $l->item_id, $l->lot_id, $l->serial_id, $l->piece_id, $l->stock_status->value))
            ->map(fn (Collection $g) => round((float) $g->sum('qty_base'), 4));

        return StockBalance::query()
            ->with('item:id,code,name,tracking_mode,ownership_model,base_uom_id', 'item.baseUom:id,code', 'lot:id,lot_no', 'serial:id,serial_no', 'piece:id,piece_no,length')
            ->nonZero()
            ->whereIn('bin_id', $bins->keys())
            ->get()
            ->map(function (StockBalance $s) use ($bins, $dipegang): array {
                $kunci = self::key((int) $s->bin_id, (int) $s->item_id, $s->lot_id, $s->serial_id, $s->piece_id, $s->stock_status->value);
                $bin = $bins->get($s->bin_id);
                $pegang = (float) ($dipegang->get($kunci) ?? 0);

                return [
                    'key' => $kunci,
                    'bin_id' => (int) $s->bin_id,
                    'bin_code' => $bin?->code,
                    'item_id' => (int) $s->item_id,
                    'item_code' => $s->item?->code,
                    'item_name' => $s->item?->name,
                    'uom' => $s->item?->baseUom?->code,
                    'lot_id' => $s->lot_id === null ? null : (int) $s->lot_id,
                    'serial_id' => $s->serial_id === null ? null : (int) $s->serial_id,
                    'piece_id' => $s->piece_id === null ? null : (int) $s->piece_id,
                    'stock_status' => $s->stock_status,
                    'tracking' => (string) ($s->lot?->lot_no ?? $s->serial?->serial_no ?? $s->piece?->piece_no ?? ''),
                    'whole' => $s->serial_id !== null || $s->piece_id !== null,
                    'balance' => round((float) $s->qty_base, 4),
                    'held' => $pegang,
                    'max' => round(max(0, (float) $s->qty_base - $pegang), 4),
                    'frozen' => $bin !== null && ! $bin->acceptsMovement(),
                    'asset' => $s->item?->isAsset() ?? false,
                ];
            })
            ->sortBy(fn (array $c) => $c['item_code'].'|'.$c['bin_code'].'|'.$c['tracking'])
            ->keyBy('key');
    }

    /** @return Collection<string, array<string, mixed>> calon yang tampil di layar */
    public function selectable(Warehouse $gudang, ?int $exceptId = null): Collection
    {
        return $this->forWarehouse($gudang, $exceptId)->reject(fn (array $c) => $c['asset'] || $c['max'] <= 0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines  key, qty_base, reason_code_id
     * @return array<int, array<string, mixed>> baris `waste_disposal_lines`
     */
    public function normalize(Warehouse $gudang, array $lines, ?int $exceptId = null): array
    {
        $calon = $this->forWarehouse($gudang, $exceptId);
        $baris = [];

        foreach ($lines as $isian) {
            $kunci = trim((string) ($isian['key'] ?? ''));
            $jumlah = is_numeric($isian['qty_base'] ?? null) ? round((float) $isian['qty_base'], 4) : 0.0;

            if ($kunci === '' && $jumlah == 0.0) {
                continue;
            }

            $c = $calon->get($kunci) ?? throw WasteRuleException::field('BR-STK-02', 'lines', 'Baris yang dipilih bukan isi bin Waste gudang '.$gudang->code.'.');

            if ($jumlah <= 0) {
                throw WasteRuleException::field('BR-LED-02', 'lines', 'Jumlah '.$c['item_code'].' harus lebih besar dari nol.');
            }

            $baris[$kunci] = [
                'item_id' => $c['item_id'],
                'bin_id' => $c['bin_id'],
                'lot_id' => $c['lot_id'],
                'serial_id' => $c['serial_id'],
                'piece_id' => $c['piece_id'],
                'stock_status' => $c['stock_status'],
                'qty_base' => round(($baris[$kunci]['qty_base'] ?? 0) + $jumlah, 4),
                'reason_code_id' => is_numeric($isian['reason_code_id'] ?? null) && (int) $isian['reason_code_id'] > 0 ? (int) $isian['reason_code_id'] : null,
            ];
        }

        if ($baris === []) {
            throw WasteRuleException::field('BR-STK-02', 'lines', 'Pilih minimal satu baris dari bin Waste gudang ini.');
        }

        $this->assertAvailable($gudang, array_values($baris), $exceptId, $calon);

        return array_values($baris);
    }

    /**
     * Guard "baris dari bin Waste" (A-159): tidak melebihi saldo dikurangi WST
     * terbuka lain; potongan dan serial utuh; bin tidak dibeku; bukan aset.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<string, array<string, mixed>>|null  $calon
     */
    public function assertAvailable(Warehouse $gudang, array $rows, ?int $exceptId = null, ?Collection $calon = null): void
    {
        $calon ??= $this->forWarehouse($gudang, $exceptId);
        $perKunci = [];

        foreach ($rows as $r) {
            $status = $r['stock_status'] instanceof StockStatus ? $r['stock_status']->value : (string) $r['stock_status'];
            $kunci = self::key((int) $r['bin_id'], (int) $r['item_id'], $r['lot_id'] ?? null, $r['serial_id'] ?? null, $r['piece_id'] ?? null, $status);
            $perKunci[$kunci] = round(($perKunci[$kunci] ?? 0) + (float) $r['qty_base'], 4);
        }

        foreach ($perKunci as $kunci => $jumlah) {
            $c = $calon->get($kunci);

            if ($c === null) {
                $kode = Item::query()->whereKey((int) explode(':', $kunci)[1])->value('code');

                throw WasteRuleException::field('BR-STK-06', 'lines', 'Waste '.$kode.' sudah tidak ada di bin Waste '.$gudang->code.'.');
            }

            if ($c['asset']) {
                throw WasteRuleException::field('BR-AST-04', 'lines', 'Item '.$c['item_code'].' adalah aset; aset rusak/hilang diproses lewat penyesuaian stok.');
            }

            if ($c['frozen']) {
                throw WasteRuleException::field('BR-OPN-02', 'lines', 'Bin '.$c['bin_code'].' sedang dibeku opname.');
            }

            if ($c['whole'] && abs($jumlah - $c['balance']) > 0.00005) {
                throw WasteRuleException::field('BR-STK-09', 'lines', ($c['serial_id'] !== null ? 'Serial ' : 'Potongan ').$c['tracking'].' ditutup utuh ('.self::angka($c['balance']).' '.$c['uom'].').');
            }

            if ($jumlah - $c['max'] > 0.00005) {
                throw WasteRuleException::field('BR-STK-06', 'lines', 'Waste '.$c['item_code'].' di '.$c['bin_code'].' tinggal '.self::angka($c['max']).' (sebagian dipegang BA waste lain); diminta '.self::angka($jumlah).'.');
            }
        }
    }

    public static function angka(float $n): string
    {
        return rtrim(rtrim(number_format($n, 4, ',', '.'), '0'), ',');
    }
}

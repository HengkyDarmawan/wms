<?php

declare(strict_types=1);

namespace App\Domain\Return\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Return\Enums\GoodsReturnStatus;
use App\Domain\Return\Enums\ReturnSorting;
use App\Domain\Return\Enums\ReturnSource;
use App\Domain\Return\Exceptions\ReturnRuleException;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Return\Models\GoodsReturnLine;
use App\Domain\Return\Support\ReturnProgress;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `return.sort` — `received` → `sorted` (Katalog §2.8, BR-RET-04).
 *
 * Setiap baris yang diterima dipilah seluruhnya, boleh dibagi ke beberapa hasil
 * (A-113): bagian pertama ditulis di baris itu, bagian berikutnya menjadi baris
 * hasil pilah. Ledger dari bin Retur gudang tujuan:
 *
 * - `good` → bin penyimpanan, kondisi Tersedia;
 * - `damaged` → bin Retur/Karantina/penyimpanan, kondisi Rusak (+ Alasan kerusakan);
 * - `waste` → bin Waste, kondisi Rusak (+ Alasan waste);
 * - `offcut` (item per potong) → potongan asal keluar, potongan baru bersilsilah
 *   ke bin penyimpanan; sisa panjang menjadi potongan waste di bin Waste.
 *
 * Satu kejadian per pergerakan masuk hasil pilah (matriks §14): `goods_returned`
 * dengan penanda kepemilikan dan hasil pilah, atau `asset_returned` untuk aset.
 * Setelah semua baris dipilah, GRN retur ikut selesai (A-112).
 */
class SortGoodsReturn
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly ReturnProgress $progress,
    ) {}

    /**
     * @param  array<int|string, array<int, array<string, mixed>>>  $portions  line_id => [sorting, qty, target_bin_id, reason_code_id, offcut_length]
     */
    public function handle(GoodsReturn $ret, array $portions, ?string $notes = null, ?User $actor = null): GoodsReturn
    {
        if ($ret->status !== GoodsReturnStatus::Received) {
            throw ReturnRuleException::rule('BR-RET-04', 'Hanya RET berstatus Diterima yang bisa dipilah.');
        }

        $gudang = Warehouse::withoutGlobalScopes()->findOrFail($ret->to_warehouse_id);
        $binRetur = $this->binSistem($gudang, BinType::Return);
        $binWaste = $this->binSistem($gudang, BinType::Waste);

        $baris = $ret->requestedLines()->with('item', 'piece', 'fromBin')->where('qty_received', '>', 0)->orderBy('id')->get();

        if ($baris->isEmpty()) {
            throw ReturnRuleException::rule('BR-RET-04', 'Tidak ada barang diterima yang bisa dipilah.');
        }

        $rencana = [];

        foreach ($baris as $l) {
            $rencana[$l->id] = $this->periksaBaris($l, $portions[$l->id] ?? [], $gudang, $binRetur, $binWaste);
        }

        return DB::transaction(function () use ($ret, $baris, $rencana, $binRetur, $binWaste, $notes, $actor) {
            foreach ($baris as $l) {
                foreach ($rencana[$l->id] as $i => $p) {
                    $target = $i === 0 ? $l : $this->barisHasilPilah($l);
                    $this->pilah($ret, $l, $target, $p, $binRetur, $binWaste, $actor);
                }
            }

            $ret->forceFill([
                'status' => GoodsReturnStatus::Sorted,
                'sorted_at' => now(),
                'sorted_by' => $actor?->id,
                'notes' => $notes !== null && trim($notes) !== '' ? mb_substr(trim($notes), 0, 255) : $ret->notes,
            ])->save();

            $this->progress->sorted($ret, $actor);

            activity('return')->performedOn($ret)->causedBy($actor)
                ->withProperties(['baris' => $baris->count(), 'peringatan' => $this->ledger->warnings()])
                ->log('Retur dipilah');

            return $ret->refresh();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $bagian
     * @return array<int, array<string, mixed>>
     */
    private function periksaBaris(GoodsReturnLine $l, array $bagian, Warehouse $gudang, Bin $binRetur, Bin $binWaste): array
    {
        $label = $l->item->code.($l->trackingLabel() !== '' ? ' '.$l->trackingLabel() : '');
        $diterima = (float) $l->qty_received;
        $hasil = [];

        foreach (array_values($bagian) as $p) {
            $pilah = ReturnSorting::tryFrom((string) ($p['sorting'] ?? ''));
            $qty = round((float) ($p['qty'] ?? 0), 4);

            if ($pilah === null && $qty <= 0) {
                continue;
            }

            if ($pilah === null) {
                throw ReturnRuleException::field('BR-RET-04', 'sorting', $label.': hasil pilah wajib dipilih.');
            }

            if ($qty <= 0) {
                throw ReturnRuleException::field('BR-LED-02', 'qty', $label.': jumlah hasil pilah harus lebih dari nol.');
            }

            $alasan = $this->alasan($pilah, $p['reason_code_id'] ?? null, $label);
            $bin = $this->binTujuan($pilah, $p['target_bin_id'] ?? null, $gudang, $binRetur, $binWaste, $label);
            $offcut = null;

            if ($pilah === ReturnSorting::Offcut) {
                $offcut = $this->panjangOffcut($l, $p['offcut_length'] ?? null, $label);
            }

            $hasil[] = ['sorting' => $pilah, 'qty' => $qty, 'bin' => $bin, 'reason_code_id' => $alasan, 'offcut_length' => $offcut];
        }

        if ($hasil === []) {
            throw ReturnRuleException::field('BR-RET-04', 'sorting', $label.': setiap baris harus dipilah (layak / rusak / offcut / waste).');
        }

        $total = array_sum(array_column($hasil, 'qty'));

        if (abs($total - $diterima) > 0.00005) {
            throw ReturnRuleException::field('BR-RET-04', 'qty', $label.': jumlah hasil pilah '.round($total, 4).' harus sama dengan yang diterima ('.round($diterima, 4).').');
        }

        // Serial dan potongan dipilah per unit utuh.
        if (($l->serial_id !== null || $l->piece_id !== null) && count($hasil) > 1) {
            throw ReturnRuleException::field('BR-LED-03', 'sorting', $label.': serial dan potongan dipilah utuh ke satu hasil.');
        }

        return $hasil;
    }

    /** BR-GEN-11: rusak dan waste menuntut Alasan dari konteksnya. */
    private function alasan(ReturnSorting $pilah, mixed $id, string $label): ?int
    {
        $konteks = $pilah->reasonContext();

        if ($konteks === null) {
            return null;
        }

        $id = (int) $id;

        if ($id === 0 || ! ReasonCode::query()->whereKey($id)->where('context', $konteks->value)->exists()) {
            throw ReturnRuleException::field('BR-GEN-11', 'reason_code_id', $label.': alasan '.mb_strtolower($pilah->label()).' wajib dipilih.');
        }

        return $id;
    }

    private function binTujuan(ReturnSorting $pilah, mixed $binId, Warehouse $gudang, Bin $binRetur, Bin $binWaste, string $label): Bin
    {
        if ($pilah === ReturnSorting::Waste) {
            return $binWaste;
        }

        $id = (int) $binId;

        if ($id === 0 && $pilah === ReturnSorting::Damaged) {
            return $binRetur;
        }

        $bin = $id > 0 ? Bin::query()->withoutGlobalScopes()->find($id) : null;

        $boleh = match ($pilah) {
            ReturnSorting::Good, ReturnSorting::Offcut => [BinType::Storage],
            ReturnSorting::Damaged => [BinType::Return, BinType::Quarantine, BinType::Storage],
            ReturnSorting::Waste => [BinType::Waste],
        };

        if ($bin === null || (int) $bin->warehouse_id !== (int) $gudang->id || ! in_array($bin->bin_type, $boleh, true)
            || $bin->bin_status !== BinStatus::Active) {
            throw ReturnRuleException::field(
                'BR-RET-04',
                'target_bin_id',
                $label.': hasil '.$pilah->label().' ditaruh di bin '.implode('/', array_map(fn (BinType $t) => $t->label(), $boleh)).' aktif gudang '.$gudang->code.'.',
            );
        }

        return $bin;
    }

    /** BR-CNV-03: offcut hanya untuk item per potong, tidak lebih panjang dari asalnya. */
    private function panjangOffcut(GoodsReturnLine $l, mixed $panjang, string $label): float
    {
        if ($l->item->tracking_mode !== TrackingMode::Piece || $l->piece === null) {
            throw ReturnRuleException::field('BR-RET-04', 'sorting', $label.': offcut hanya untuk item per potong.');
        }

        $p = round((float) $panjang, 4);
        $asal = (float) $l->piece->length;

        if ($p <= 0 || $p - $asal > 0.00005) {
            throw ReturnRuleException::field('BR-STK-09', 'offcut_length', $label.': panjang offcut harus lebih dari 0 dan paling panjang '.round($asal, 4).'.');
        }

        $minimum = $l->item->min_offcut_length;

        if ($minimum !== null && $p + 0.00005 < (float) $minimum) {
            throw ReturnRuleException::field('BR-CNV-03', 'offcut_length', $label.': sisa di bawah panjang minimum offcut ('.(float) $minimum.') dipilah sebagai waste.');
        }

        return $p;
    }

    /** Baris hasil pilah tambahan (A-113): jumlah diajukan 0, merujuk baris asal. */
    private function barisHasilPilah(GoodsReturnLine $asal): GoodsReturnLine
    {
        return GoodsReturnLine::create([
            'goods_return_id' => $asal->goods_return_id,
            'item_id' => $asal->item_id,
            'lot_id' => $asal->lot_id,
            'serial_id' => $asal->serial_id,
            'piece_id' => $asal->piece_id,
            'origin_shipment_line_id' => $asal->origin_shipment_line_id,
            'origin_discrepancy_line_id' => $asal->origin_discrepancy_line_id,
            'ownership' => $asal->ownership,
            'stock_status' => $asal->stock_status,
            'qty_base' => 0,
            'qty_received' => 0,
            'split_from_line_id' => $asal->id,
        ]);
    }

    /** @param  array<string, mixed>  $p */
    private function pilah(GoodsReturn $ret, GoodsReturnLine $asal, GoodsReturnLine $target, array $p, Bin $binRetur, Bin $binWaste, ?User $actor): void
    {
        /** @var ReturnSorting $pilah */
        $pilah = $p['sorting'];
        /** @var Bin $bin */
        $bin = $p['bin'];
        $kondisiAsal = $asal->stock_status;
        $sumber = $asal->source();
        $event = $sumber === ReturnSource::OnSiteAsset ? StockEventType::AssetReturned : StockEventType::GoodsReturned;
        $pieceId = $asal->piece_id;
        $newPieceId = null;

        if ($pilah === ReturnSorting::Offcut) {
            [$pieceId, $newPieceId] = $this->potong($ret, $asal, (float) $p['offcut_length'], $binRetur, $binWaste, $p, $actor);
        } elseif ((int) $bin->id === (int) $binRetur->id && $pilah->stockStatus() === $kondisiAsal) {
            // Tetap di bin Retur dengan kondisi sama: tidak ada pergerakan,
            // kejadian tetap terbit supaya retur tercatat di Akuntansi.
            $this->ledger->emitEvent($event, $this->payload($ret, $asal, $pilah, (float) $p['qty']),
                'goods_return', (int) $ret->id, $ret->number, (int) $ret->project_id);
        } else {
            $this->posting($ret, $asal, $target, new MovementRequest(
                item: $asal->item,
                qtyBase: (float) $p['qty'],
                fromBinId: (int) $binRetur->id,
                toBinId: (int) $bin->id,
                stockStatus: $pilah->stockStatus(),
                fromStockStatus: $kondisiAsal,
                lotId: $asal->lot_id,
                serialId: $asal->serial_id,
                pieceId: $asal->piece_id,
                projectId: (int) $ret->project_id,
                documentType: 'goods_return',
                documentId: (int) $ret->id,
                documentLineId: (int) $target->id,
                documentNumber: $ret->number,
                reasonCodeId: $p['reason_code_id'],
                performedBy: $actor,
                eventType: $event,
                eventPayload: $this->payload($ret, $asal, $pilah, (float) $p['qty']),
            ));
        }

        $target->forceFill([
            'sorting' => $pilah,
            'sorted_qty' => $pilah === ReturnSorting::Offcut ? $p['offcut_length'] : $p['qty'],
            'target_bin_id' => $bin->id,
            'reason_code_id' => $p['reason_code_id'],
            'new_piece_id' => $newPieceId,
        ])->save();
    }

    /**
     * Offcut (BR-RET-04, A-36): potongan asal keluar dari bin Retur, potongan
     * baru bersilsilah masuk bin penyimpanan, sisanya menjadi potongan waste di
     * bin Waste — panjang tidak pernah hilang dari kartu stok.
     *
     * @param  array<string, mixed>  $p
     * @return array{0: int|null, 1: int}
     */
    private function potong(GoodsReturn $ret, GoodsReturnLine $asal, float $panjang, Bin $binRetur, Bin $binWaste, array $p, ?User $actor): array
    {
        $induk = $asal->piece;
        $total = (float) $induk->length;

        $this->posting($ret, $asal, $asal, new MovementRequest(
            item: $asal->item,
            qtyBase: $total,
            fromBinId: (int) $binRetur->id,
            stockStatus: $asal->stock_status,
            pieceId: (int) $induk->id,
            projectId: (int) $ret->project_id,
            documentType: 'goods_return',
            documentId: (int) $ret->id,
            documentLineId: (int) $asal->id,
            documentNumber: $ret->number,
            performedBy: $actor,
            notes: 'Dipotong menjadi offcut',
        ));

        $induk->forceFill(['is_consumed' => true])->save();

        $offcut = $this->potonganBaru($ret, $asal, $panjang, true);

        $this->posting($ret, $asal, $asal, new MovementRequest(
            item: $asal->item,
            qtyBase: $panjang,
            toBinId: (int) $p['bin']->id,
            pieceId: (int) $offcut->id,
            projectId: (int) $ret->project_id,
            documentType: 'goods_return',
            documentId: (int) $ret->id,
            documentLineId: (int) $asal->id,
            documentNumber: $ret->number,
            performedBy: $actor,
            eventType: StockEventType::GoodsReturned,
            eventPayload: $this->payload($ret, $asal, ReturnSorting::Offcut, $panjang) + ['parent_piece_id' => $induk->id],
        ));

        $sisa = round($total - $panjang, 4);

        if ($sisa > 0) {
            $waste = $this->potonganBaru($ret, $asal, $sisa, false);

            $this->posting($ret, $asal, $asal, new MovementRequest(
                item: $asal->item,
                qtyBase: $sisa,
                toBinId: (int) $binWaste->id,
                stockStatus: StockStatus::Damaged,
                pieceId: (int) $waste->id,
                projectId: (int) $ret->project_id,
                documentType: 'goods_return',
                documentId: (int) $ret->id,
                documentLineId: (int) $asal->id,
                documentNumber: $ret->number,
                performedBy: $actor,
                notes: 'Sisa potong offcut retur',
                eventType: StockEventType::GoodsReturned,
                eventPayload: $this->payload($ret, $asal, ReturnSorting::Waste, $sisa) + ['parent_piece_id' => $induk->id],
            ));
        }

        return [$induk->id, (int) $offcut->id];
    }

    /** Potongan hasil pilah dengan silsilah ke potongan asal (BR-CNV-04). */
    private function potonganBaru(GoodsReturn $ret, GoodsReturnLine $asal, float $panjang, bool $offcut): Piece
    {
        $urut = Piece::query()->count() + 1;

        do {
            $nomor = sprintf('P-%06d', $urut++);
        } while (Piece::query()->where('piece_no', $nomor)->exists());

        return Piece::create([
            'item_id' => $asal->item_id,
            'piece_no' => $nomor,
            'length' => $panjang,
            'is_offcut' => $offcut,
            'parent_piece_id' => $asal->piece_id,
            'origin_type' => 'return',
            'origin_id' => $ret->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(GoodsReturn $ret, GoodsReturnLine $asal, ReturnSorting $pilah, float $qty): array
    {
        return [
            'return_number' => $ret->number,
            'return_line_id' => $asal->id,
            // BR-RET-03: retur penjualan (sold) dibedakan dari stok company.
            'ownership' => $asal->ownership->value,
            'sorting' => $pilah->value,
            'source' => $asal->source()->value,
            'origin_shipment_line_id' => $asal->origin_shipment_line_id,
            'to_warehouse_id' => $ret->to_warehouse_id,
            'item_code' => $asal->item?->code,
            'qty_base' => $qty,
        ] + ($asal->source() === ReturnSource::OnSiteAsset
            // Pemeriksaan aset (grade, skor, meter, hari pakai) menunggu modul Aset (BR-GEN-10).
            ? ['inspection' => null, 'serial_id' => $asal->serial_id]
            : []);
    }

    private function posting(GoodsReturn $ret, GoodsReturnLine $asal, GoodsReturnLine $target, MovementRequest $request): void
    {
        try {
            $this->ledger->post($request);
        } catch (LedgerException $e) {
            throw ReturnRuleException::rule($e->rule, 'Baris '.$asal->item?->code.' gagal dipilah: '.$e->getMessage());
        }
    }

    private function binSistem(Warehouse $gudang, BinType $type): Bin
    {
        $bin = Bin::query()->withoutGlobalScopes()->where('warehouse_id', $gudang->id)->where('bin_type', $type->value)->orderBy('id')->first();

        if ($bin === null) {
            throw ReturnRuleException::rule('BR-WH-02', 'Gudang '.$gudang->code.' belum punya bin '.$type->label().'. Simpan ulang gudangnya untuk membuatnya.');
        }

        return $bin;
    }
}

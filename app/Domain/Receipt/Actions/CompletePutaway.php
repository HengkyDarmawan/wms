<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Actions;

use App\Domain\Access\Models\User;
use App\Domain\PurchaseRequest\Support\BackorderPurchases;
use App\Domain\Receipt\Enums\PutawayTaskStatus;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Receipt\Models\PutawayTask;
use App\Domain\Receipt\Models\PutawayTaskLine;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Transfer\Support\BackorderTransfers;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `putaway.complete` — `pending` → `completed` (Katalog §2.6).
 *
 * Staf menaruh barang di bin saran atau bin lain; mengganti saran menuntut
 * alasan (BR-GRN-03: "staf boleh mengganti"). Bin tujuan harus bin
 * penyimpanan aktif di gudang yang sama. Kapasitas ditegakkan buku besar
 * (BR-WH-06): mode blokir menolak, mode peringatan dikembalikan sebagai pesan.
 *
 * Tidak ada kejadian stok: perpindahan di dalam gudang (matriks §14).
 */
class CompletePutaway
{
    /** @var array<int, string> */
    private array $peringatan = [];

    public function __construct(
        private readonly StockLedger $ledger,
        private readonly BackorderTransfers $backorder,
    ) {}

    /**
     * @param  array<int|string, array{bin_id?: int|string|null, override_reason?: ?string}>  $isian  line_id => isian
     */
    public function handle(PutawayTask $task, array $isian = [], ?User $actor = null): PutawayTask
    {
        if ($task->status !== PutawayTaskStatus::Pending) {
            throw ReceiptRuleException::rule('BR-GEN-01', 'Hanya tugas put-away berstatus Menunggu yang bisa diselesaikan.');
        }

        $lines = $task->lines()->with('item', 'receiptLine')->orderBy('id')->get();
        $rencana = [];

        foreach ($lines as $l) {
            /** @var PutawayTaskLine $l */
            $isi = $isian[$l->id] ?? [];
            $binId = (int) (($isi['bin_id'] ?? '') !== '' && ($isi['bin_id'] ?? null) !== null ? $isi['bin_id'] : ($l->suggested_bin_id ?? 0));

            if ($binId === 0) {
                throw ReceiptRuleException::field('BR-GRN-03', 'bin_id', 'Baris '.$l->item->code.': bin tujuan wajib dipilih.');
            }

            $bin = Bin::query()->withoutGlobalScopes()->find($binId);

            if ($bin === null || (int) $bin->warehouse_id !== (int) $task->warehouse_id || $bin->bin_type !== BinType::Storage) {
                throw ReceiptRuleException::field('BR-GRN-03', 'bin_id', 'Baris '.$l->item->code.': bin tujuan harus bin penyimpanan di gudang ini.');
            }

            $alasan = trim((string) ($isi['override_reason'] ?? ''));

            if ($l->suggested_bin_id !== null && $binId !== (int) $l->suggested_bin_id && $alasan === '') {
                throw ReceiptRuleException::field('BR-GRN-03', 'override_reason', 'Baris '.$l->item->code.': menaruh di bin selain saran menuntut alasan.');
            }

            $rencana[$l->id] = [$l, $bin, $alasan === '' ? null : $alasan];
        }

        $this->peringatan = [];

        return DB::transaction(function () use ($task, $rencana, $actor) {
            foreach ($rencana as [$l, $bin, $alasan]) {
                try {
                    $this->ledger->post(new MovementRequest(
                        item: $l->item,
                        qtyBase: (float) $l->qty_base,
                        fromBinId: (int) $l->from_bin_id,
                        toBinId: (int) $bin->id,
                        lotId: $l->lot_id,
                        serialId: $l->serial_id,
                        pieceId: $l->piece_id,
                        documentType: 'putaway_task',
                        documentId: (int) $task->id,
                        documentLineId: (int) $l->id,
                        documentNumber: $task->number,
                        performedBy: $actor,
                        notes: $alasan,
                    ));
                } catch (LedgerException $e) {
                    throw ReceiptRuleException::rule($e->rule, 'Baris '.$l->item->code.' gagal ditaruh: '.$e->getMessage());
                }

                $this->peringatan = array_merge($this->peringatan, $this->ledger->warnings());

                $l->forceFill([
                    'bin_id' => $bin->id,
                    'override_reason' => $alasan,
                    'scanned_at' => now(),
                ])->save();
            }

            $task->forceFill([
                'status' => PutawayTaskStatus::Completed,
                'completed_at' => now(),
                'assigned_to' => $task->assigned_to ?? $actor?->id,
            ])->save();

            // BR-REQ-08 (A-108): barang TRF backorder yang sudah di bin penyimpanan
            // direservasi ke baris REQ penunggunya.
            $this->backorder->reserveArrivals($task->refresh(), $actor);
            // BR-REQ-08: barang PRQ backorder juga direservasi ke REQ penunggunya (A-171).
            app(BackorderPurchases::class)->reserveArrivals($task->refresh(), $actor);

            activity('receipt')
                ->performedOn($task)
                ->causedBy($actor)
                ->withProperties(['baris' => count($rencana), 'peringatan' => $this->peringatan])
                ->log('Put-away selesai');

            return $task->refresh();
        });
    }

    /** @return array<int, string> peringatan kapasitas dari penyelesaian terakhir */
    public function warnings(): array
    {
        return array_values(array_unique($this->peringatan));
    }
}

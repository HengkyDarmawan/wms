<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Label\Support\PackageLabelLedger;
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
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Support\StoragePolicy;
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
 *
 * BR-WH-10 (A-366): bin yang **Khusus** untuk barang lain ditolak, kecuali
 * Kepala Gudang membukanya dengan alasan (`buka_khusus`, dicatat).
 *
 * **Per baris** (keputusan #6, A-375): {@see placeLine()} menaruh satu baris
 * (satu pergerakan stok lewat StockLedger, label kemasan ikut pindah, reservasi
 * backorder baris itu); tugas tetap *Menunggu* sampai baris terakhir tertaruh,
 * lalu otomatis *Selesai* — tanpa status baru. Baris tertaruh = `scanned_at`
 * terisi. {@see handle()} menaruh semua baris yang belum.
 */
class CompletePutaway
{
    /** @var array<int, string> */
    private array $peringatan = [];

    public function __construct(
        private readonly StockLedger $ledger,
        private readonly BackorderTransfers $backorder,
        private readonly StoragePolicy $khusus,
    ) {}

    /**
     * Menaruh semua baris yang belum tertaruh lalu menutup tugas.
     *
     * @param  array<int|string, array{bin_id?: int|string|null, override_reason?: ?string, buka_khusus?: ?string}>  $isian  line_id => isian
     */
    public function handle(PutawayTask $task, array $isian = [], ?User $actor = null): PutawayTask
    {
        $this->pastikanMenunggu($task);

        $lines = $task->lines()->with('item', 'receiptLine')->whereNull('scanned_at')->orderBy('id')->get();
        $rencana = [];

        foreach ($lines as $l) {
            $rencana[$l->id] = $this->rencanakan($task, $l, $isian[$l->id] ?? [], $actor);
        }

        $this->peringatan = [];

        return DB::transaction(function () use ($task, $rencana, $actor) {
            foreach ($rencana as [$l, $bin, $alasan, $buka]) {
                $this->taruh($task, $l, $bin, $alasan, $buka, $actor);
            }

            return $this->tutup($task, count($rencana), $actor);
        });
    }

    /**
     * Keputusan #6 (A-375): menaruh **satu** baris. Tugas selesai otomatis bila
     * baris ini yang terakhir.
     *
     * @param  array{bin_id?: int|string|null, override_reason?: ?string, buka_khusus?: ?string}  $isi
     */
    public function placeLine(PutawayTask $task, int $lineId, array $isi, ?User $actor = null): PutawayTask
    {
        $this->pastikanMenunggu($task);

        $l = $task->lines()->with('item', 'receiptLine')->whereKey($lineId)->first();

        if ($l === null) {
            throw ReceiptRuleException::rule('BR-GRN-03', 'Baris put-away tidak ditemukan di tugas ini.');
        }

        if ($l->scanned_at !== null) {
            throw ReceiptRuleException::rule('BR-GEN-01', 'Baris '.$l->item->code.' sudah ditaruh di bin.');
        }

        [$l, $bin, $alasan, $buka] = $this->rencanakan($task, $l, $isi, $actor);
        $this->peringatan = [];

        return DB::transaction(function () use ($task, $l, $bin, $alasan, $buka, $actor) {
            // Kunci tugas supaya dua pemindai tidak menaruh baris yang sama.
            $segar = PutawayTask::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($task->id);
            $this->pastikanMenunggu($segar);

            if (PutawayTaskLine::query()->whereKey($l->id)->whereNotNull('scanned_at')->exists()) {
                throw ReceiptRuleException::rule('BR-GEN-01', 'Baris '.$l->item->code.' sudah ditaruh di bin.');
            }

            $this->taruh($task, $l, $bin, $alasan, $buka, $actor);

            activity('receipt')->performedOn($task)->causedBy($actor)
                ->withProperties(['baris' => $l->id, 'item' => $l->item->code, 'bin' => $bin->code, 'alasan' => $alasan])
                ->log('Baris put-away ditaruh di '.$bin->code);

            if ($task->lines()->whereNull('scanned_at')->exists()) {
                return $task->refresh();
            }

            return $this->tutup($task, $task->lines()->count(), $actor);
        });
    }

    /** @return array<int, string> peringatan kapasitas dari penyelesaian terakhir */
    public function warnings(): array
    {
        return array_values(array_unique($this->peringatan));
    }

    private function pastikanMenunggu(PutawayTask $task): void
    {
        if ($task->status !== PutawayTaskStatus::Pending) {
            throw ReceiptRuleException::rule('BR-GEN-01', 'Hanya tugas put-away berstatus Menunggu yang bisa diselesaikan.');
        }
    }

    /**
     * Periksa isian satu baris (tanpa menulis apa pun).
     *
     * @param  array{bin_id?: int|string|null, override_reason?: ?string, buka_khusus?: ?string}  $isi
     * @return array{0: PutawayTaskLine, 1: Bin, 2: ?string, 3: ?string}
     */
    private function rencanakan(PutawayTask $task, PutawayTaskLine $l, array $isi, ?User $actor): array
    {
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

        try {
            $buka = $this->khusus->periksa($bin, $l->item, $isi['buka_khusus'] ?? null, $actor);
        } catch (WarehouseRuleException $e) {
            throw ReceiptRuleException::field((string) $e->rule, 'bin_id', 'Baris '.$l->item->code.': '.$e->getMessage());
        }

        return [$l, $bin, $alasan === '' ? null : mb_substr($alasan, 0, 255), $buka ? trim((string) $isi['buka_khusus']) : null];
    }

    /** Satu pergerakan stok + label + reservasi backorder untuk satu baris. */
    private function taruh(PutawayTask $task, PutawayTaskLine $l, Bin $bin, ?string $alasan, ?string $buka, ?User $actor): void
    {
        if ($buka !== null) {
            $this->khusus->catatBuka($bin, $l->item, $buka, $actor, ['type' => 'putaway_task', 'id' => (int) $task->id, 'number' => $task->number]);
        }

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

        // A-296: label kemasan baris GRN itu ikut pindah lokasi (stok sudah lewat kartu stok).
        if ($l->goods_receipt_line_id !== null) {
            app(PackageLabelLedger::class)->putAway((int) $l->goods_receipt_line_id, (int) $l->from_bin_id, (int) $bin->id,
                ['type' => 'putaway_task', 'id' => (int) $task->id, 'line_id' => (int) $l->id, 'number' => $task->number], $actor);
        }

        // BR-REQ-08 (A-108, A-171): barang TRF/PRQ backorder yang sudah di bin
        // penyimpanan direservasi ke baris REQ penunggunya — per baris yang
        // tertaruh (A-375), jadi baris yang sudah di rak tidak menunggu baris lain.
        $this->backorder->reserveArrivals($task, $actor, [$l]);
        app(BackorderPurchases::class)->reserveArrivals($task, $actor, [$l]);
    }

    private function tutup(PutawayTask $task, int $baris, ?User $actor): PutawayTask
    {
        $task->forceFill([
            'status' => PutawayTaskStatus::Completed,
            'completed_at' => now(),
            'assigned_to' => $task->assigned_to ?? $actor?->id,
        ])->save();

        activity('receipt')
            ->performedOn($task)
            ->causedBy($actor)
            ->withProperties(['baris' => $baris, 'peringatan' => $this->peringatan])
            ->log('Put-away selesai');

        return $task->refresh();
    }
}

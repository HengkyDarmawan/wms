<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Label\Actions\CreatePackageLabels;
use App\Domain\Label\Exceptions\LabelRuleException;
use App\Domain\Receipt\Enums\GoodsReceiptStatus;
use App\Domain\Receipt\Enums\ReceiptType;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\GoodsReceiptLine;
use App\Domain\Receipt\Models\PutawayTask;
use App\Domain\Receipt\Support\PutawayPlanner;
use App\Domain\Transfer\Support\TransferProgress;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `receipt.complete` — `received` → `completed` (Katalog §2.5).
 *
 * Guard: setiap baris yang masuk Karantina sudah punya hasil QC
 * (`passed`/`quarantined`/`rejected`, BR-GRN-02). Efek: PUT dibuat untuk baris
 * tanpa QC dan baris yang lolos (§2.6); baris `rejected` tetap di Karantina
 * menunggu RTV, baris `quarantined` menunggu keputusan ulang.
 */
class CompleteGoodsReceipt
{
    public function __construct(
        private readonly PutawayPlanner $perencana,
        private readonly TransferProgress $transfer,
    ) {}

    /**
     * @param  array<int|string, array<string, mixed>>|null  $labels  A-296: rencana label induk per baris
     *                                                                (null = tanpa label; label bisa dibuat nanti)
     */
    public function handle(GoodsReceipt $receipt, ?User $actor = null, ?array $labels = null): GoodsReceipt
    {
        if ($receipt->status !== GoodsReceiptStatus::Received) {
            throw ReceiptRuleException::rule('BR-GEN-01', 'Hanya GRN berstatus Diterima yang bisa diselesaikan.');
        }

        // A-112: GRN retur tidak membuat PUT; ia selesai bersama pemilahan RET-nya.
        if ($receipt->receipt_type === ReceiptType::Return) {
            throw ReceiptRuleException::rule('BR-RET-04', 'GRN retur selesai otomatis saat RET-nya dipilah.');
        }

        $lines = $receipt->lines()->with('item.category', 'receivingBin')->get();

        $belumQc = $lines->filter(fn (GoodsReceiptLine $l) => $l->receivingBinIsQuarantine() && $l->qc_result === null);

        if ($belumQc->isNotEmpty()) {
            throw ReceiptRuleException::rule(
                'BR-GRN-02',
                'Hasil QC belum terisi untuk '.$belumQc->count().' baris ('
                .$belumQc->map(fn ($l) => $l->item?->code)->unique()->implode(', ').').',
            );
        }

        return DB::transaction(function () use ($receipt, $lines, $actor, $labels) {
            $receipt->forceFill([
                'status' => GoodsReceiptStatus::Completed,
                'completed_at' => now(),
            ])->save();

            $tugas = $this->perencana->planFor($receipt, $lines, $actor);

            // A-296: label induk dibuat bersama penyelesaian GRN vendor.
            if ($labels !== null && $receipt->receipt_type === ReceiptType::Vendor) {
                try {
                    app(CreatePackageLabels::class)->handle($receipt->refresh(), $labels, $actor);
                } catch (LabelRuleException $e) {
                    throw ReceiptRuleException::rule($e->rule, $e->getMessage(), $e->fieldErrors);
                }
            }

            // Katalog §2.7: TRF `completed` saat GRN tujuan selesai (A-107).
            if ($receipt->receipt_type === ReceiptType::Transfer) {
                $this->transfer->receiptCompleted($receipt, $actor);
            }

            activity('receipt')
                ->performedOn($receipt)
                ->causedBy($actor)
                ->withProperties(['put' => $tugas?->number])
                ->log('GRN selesai');

            return $receipt->refresh();
        });
    }

    /**
     * Membuat ulang PUT untuk baris yang boleh ditaruh tetapi tidak punya PUT
     * aktif — mis. setelah PUT dibatalkan (barang tetap di bin Penerimaan).
     */
    public function replan(GoodsReceipt $receipt, ?User $actor = null): ?PutawayTask
    {
        if ($receipt->status !== GoodsReceiptStatus::Completed) {
            throw ReceiptRuleException::rule('BR-GEN-01', 'Tugas put-away dibuat ulang hanya untuk GRN yang sudah selesai.');
        }

        $tugas = DB::transaction(fn () => $this->perencana->planFor($receipt, null, $actor));

        if ($tugas === null) {
            throw ReceiptRuleException::rule('BR-GRN-03', 'Tidak ada baris yang menunggu put-away.');
        }

        return $tugas;
    }
}

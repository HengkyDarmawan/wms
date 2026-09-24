<?php

declare(strict_types=1);

namespace App\Domain\Transfer\Support;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Contracts\ApprovalHandler;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Support\ApprovalContext;
use App\Domain\Master\Models\Item;
use App\Domain\Shipment\Actions\CreatePickTask;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Stock\Actions\ManageReservation;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Transfer\Enums\TransferStatus;
use App\Domain\Transfer\Exceptions\TransferRuleException;
use App\Domain\Transfer\Models\Transfer;
use App\Domain\Transfer\Models\TransferLine;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Penangan approval TRF (Katalog §2.7, 20-approval §3.9).
 *
 * Persetujuan akhir = TRF mulai mengikat stok gudang asal: reservasi lunak per
 * baris (BR-STK-04), lalu sistem mencoba membuat PCK di gudang asal (alur 5
 * langkah 5) sehingga TRF `in_progress`. Bila alokasi keras gagal — mis. bin
 * sedang dibeku opname — TRF tetap `approved` dengan reservasinya, dan PCK
 * dibuat kemudian dari detail TRF (A-107). Tanpa aturan TRF disetujui otomatis
 * (A-08): itulah jalur ringan transfer dalam proyek (BR-RET-02).
 */
class TransferApprovalHandler implements ApprovalHandler
{
    public function __construct(
        private readonly ManageReservation $reservasi,
        private readonly CreatePickTask $picking,
    ) {}

    public function documentType(): ApprovalDocumentType
    {
        return ApprovalDocumentType::Transfer;
    }

    public function approvePermission(): string
    {
        return 'transfer.approve';
    }

    public function find(int $documentId): ?Model
    {
        return Transfer::withoutGlobalScopes()->find($documentId);
    }

    public function findByNumber(string $number): ?Model
    {
        return Transfer::query()->where('number', $number)->first();
    }

    public function number(Model $document): string
    {
        return (string) $document->number;
    }

    public function url(Model $document): ?string
    {
        return route('transfers.show', $document->getKey());
    }

    public function logName(): string
    {
        return 'transfer';
    }

    /**
     * Gudang dokumen = gudang **asal**: yang melepas stok adalah yang
     * menyetujui ("kepala gudang terkait", A-88, A-107). Proyek = proyek
     * tujuan, atau proyek asal bila tujuannya bukan Gudang Site.
     *
     * @param  Transfer  $document
     */
    public function context(Model $document): ApprovalContext
    {
        $baris = TransferLine::query()->where('transfer_id', $document->getKey())->get();
        $item = Item::query()->whereIn('id', $baris->pluck('item_id')->unique())->get(['id', 'item_category_id', 'ownership_model']);
        $proyek = $document->to_project_id ?? $document->from_project_id;

        return new ApprovalContext(
            documentType: ApprovalDocumentType::Transfer,
            documentId: (int) $document->getKey(),
            documentNumber: (string) $document->number,
            warehouseIds: [(int) $document->from_warehouse_id],
            projectId: $proyek !== null ? (int) $proyek : null,
            categoryIds: ApprovalContext::withAncestors($item->pluck('item_category_id')->filter()->all()),
            ownershipModels: $item->map(fn (Item $i) => $i->ownership_model?->value)->filter()->unique()->values()->all(),
            lineCount: $baris->count(),
            maxLineQty: (float) ($baris->max('qty_base') ?? 0),
            requesterId: $document->submitted_by !== null ? (int) $document->submitted_by : null,
            requesterIds: array_filter([(int) $document->submitted_by]),
        );
    }

    public function attachSnapshot(Model $document, ?ApprovalSnapshot $snapshot): void
    {
        $document->forceFill(['approval_snapshot_id' => $snapshot?->id])->save();
    }

    public function fallbackSteps(Model $document): array
    {
        return [];
    }

    /** @param  Transfer  $document */
    public function onApproved(Model $document, ?User $actor): void
    {
        $document->refresh();

        if ($document->status !== TransferStatus::PendingApproval) {
            throw TransferRuleException::rule('BR-GEN-01', 'Hanya TRF berstatus Menunggu Approval yang bisa disetujui.');
        }

        $document->forceFill([
            'status' => TransferStatus::Approved,
            'approved_by' => $actor?->id,
            'approved_at' => now(),
        ])->save();

        $this->reservasiLunak($document, $actor);

        activity('transfer')->performedOn($document)->causedBy($actor)->log('TRF disetujui');

        $this->cobaBuatPicking($document, $actor);
    }

    /** @param  Transfer  $document */
    public function onRejected(Model $document, int $reasonCodeId, ?string $notes, User $actor): void
    {
        $document->refresh()->forceFill([
            'status' => TransferStatus::Rejected,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'reject_reason_id' => $reasonCodeId,
        ])->save();

        activity('transfer')->performedOn($document)->causedBy($actor)
            ->withProperties(['reason_code_id' => $reasonCodeId, 'keterangan' => $notes])
            ->log('TRF ditolak');
    }

    /** Katalog §2.7: `submitted → approved` membuat reservasi lunak di gudang asal. */
    private function reservasiLunak(Transfer $trf, ?User $actor): void
    {
        // Gudang dibaca tanpa cakupan: approver akhir belum tentu bercakupan gudang asal.
        $gudang = Warehouse::withoutGlobalScopes()->findOrFail($trf->from_warehouse_id);

        foreach ($trf->lines()->with('item')->orderBy('id')->get() as $l) {
            try {
                $this->reservasi->reserveSoft($l->item, $gudang, (float) $l->qty_base, 'transfer', (int) $trf->id, (int) $l->id, $actor);
            } catch (LedgerException $e) {
                throw TransferRuleException::rule(
                    'BR-STK-03',
                    'Baris '.$l->item->code.' tidak bisa direservasi di gudang asal '.$gudang->code.': '.$e->getMessage(),
                );
            }
        }
    }

    /**
     * Alur 5 langkah 5: "reservasi lunak di gudang asal; buat PCK". Kegagalan
     * alokasi tidak membatalkan persetujuan (A-107) — savepoint memastikan
     * PCK yang setengah jadi tidak tertinggal.
     */
    private function cobaBuatPicking(Transfer $trf, ?User $actor): void
    {
        try {
            DB::transaction(fn () => $this->picking->forTransfer($trf->refresh(), $actor));
        } catch (ShipmentRuleException|LedgerException $e) {
            activity('transfer')->performedOn($trf)->causedBy($actor)
                ->withProperties(['aturan' => $e->rule, 'pesan' => $e->getMessage()])
                ->log('Tugas picking belum bisa dibuat otomatis');
        }
    }
}

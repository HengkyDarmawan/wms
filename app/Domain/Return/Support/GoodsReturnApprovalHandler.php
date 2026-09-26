<?php

declare(strict_types=1);

namespace App\Domain\Return\Support;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Contracts\ApprovalHandler;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Support\ApprovalContext;
use App\Domain\Master\Models\Item;
use App\Domain\Return\Enums\GoodsReturnStatus;
use App\Domain\Return\Enums\ReturnSource;
use App\Domain\Return\Exceptions\ReturnRuleException;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Return\Models\GoodsReturnLine;
use App\Domain\Shipment\Actions\CreatePickTask;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Stock\Actions\ManageReservation;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Penangan approval RET (Katalog §2.8, 20-approval §3.9).
 *
 * Setelah disetujui, stok Gudang Site yang dikembalikan dicadangkan keras supaya
 * tidak terpakai ISU/transfer selagi menunggu diangkut (A-111). Lalu:
 * - tanpa SJ (diantar sendiri) → `in_progress` seketika ("atau tanpa SJ bila
 *   diantar sendiri");
 * - dengan SJ balik → PCK dibuat otomatis di Gudang Site; RET `in_progress`
 *   saat SJ balik disusun. Bila PCK gagal dibuat, RET tetap `approved` dan PCK
 *   dibuat dari detail RET.
 */
class GoodsReturnApprovalHandler implements ApprovalHandler
{
    public function __construct(
        private readonly ManageReservation $reservasi,
        private readonly CreatePickTask $picking,
    ) {}

    public function documentType(): ApprovalDocumentType
    {
        return ApprovalDocumentType::GoodsReturn;
    }

    public function approvePermission(): string
    {
        return 'return.approve';
    }

    public function find(int $documentId): ?Model
    {
        return GoodsReturn::withoutGlobalScopes()->find($documentId);
    }

    public function findByNumber(string $number): ?Model
    {
        return GoodsReturn::query()->where('number', $number)->first();
    }

    public function number(Model $document): string
    {
        return (string) $document->number;
    }

    public function url(Model $document): ?string
    {
        return route('returns.show', $document->getKey());
    }

    public function logName(): string
    {
        return 'return';
    }

    /**
     * Gudang dokumen = gudang tujuan (yang menerima dan memilah); proyek = proyek
     * RET; "dari klien" bila pengajunya user Klien.
     *
     * @param  GoodsReturn  $document
     */
    public function context(Model $document): ApprovalContext
    {
        $baris = GoodsReturnLine::query()->where('goods_return_id', $document->getKey())->whereNull('split_from_line_id')->get();
        $item = Item::query()->whereIn('id', $baris->pluck('item_id')->unique())->get(['id', 'item_category_id', 'ownership_model']);

        return new ApprovalContext(
            documentType: ApprovalDocumentType::GoodsReturn,
            documentId: (int) $document->getKey(),
            documentNumber: (string) $document->number,
            warehouseIds: [(int) $document->to_warehouse_id],
            projectId: (int) $document->project_id,
            categoryIds: ApprovalContext::withAncestors($item->pluck('item_category_id')->filter()->all()),
            ownershipModels: $item->map(fn (Item $i) => $i->ownership_model?->value)->filter()->unique()->values()->all(),
            lineCount: $baris->count(),
            maxLineQty: (float) ($baris->max('qty_base') ?? 0),
            fromClient: $document->isFromClient(),
            requesterId: (int) $document->requester_id,
            requesterIds: [(int) $document->requester_id],
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

    /** @param  GoodsReturn  $document */
    public function onApproved(Model $document, ?User $actor): void
    {
        $document->refresh();

        if ($document->status !== GoodsReturnStatus::PendingApproval) {
            throw ReturnRuleException::rule('BR-GEN-01', 'Hanya RET berstatus Menunggu Approval yang bisa disetujui.');
        }

        $document->forceFill([
            'status' => GoodsReturnStatus::Approved,
            'approved_by' => $actor?->id,
            'approved_at' => now(),
        ])->save();

        $this->cadangkanStokSite($document, $actor);

        activity('return')->performedOn($document)->causedBy($actor)->log('RET disetujui');

        if ($document->self_delivered) {
            // Katalog §2.8: tanpa SJ bila diantar sendiri → langsung diproses.
            $document->forceFill(['status' => GoodsReturnStatus::InProgress])->save();

            activity('return')->performedOn($document)->causedBy($actor)->log('RET diproses: diantar sendiri tanpa SJ balik');

            return;
        }

        // A-248: barang di proyek dijemput driver; RET menunggu SJ jemput
        // disusun gudang tujuan (tanpa PCK, tanpa cadangan stok).
        if ($document->isPickup()) {
            activity('return')->performedOn($document)->causedBy($actor)->log('RET menunggu SJ jemput dari gudang tujuan');

            return;
        }

        $this->cobaBuatPicking($document, $actor);
    }

    /** @param  GoodsReturn  $document */
    public function onRejected(Model $document, int $reasonCodeId, ?string $notes, User $actor): void
    {
        $document->refresh()->forceFill([
            'status' => GoodsReturnStatus::Rejected,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'reject_reason_id' => $reasonCodeId,
        ])->save();

        activity('return')->performedOn($document)->causedBy($actor)
            ->withProperties(['reason_code_id' => $reasonCodeId, 'keterangan' => $notes])
            ->log('RET ditolak');
    }

    private function cadangkanStokSite(GoodsReturn $ret, ?User $actor): void
    {
        $baris = $ret->requestedLines()->with('item', 'fromBin')->get()
            ->filter(fn (GoodsReturnLine $l) => $l->source() === ReturnSource::SiteStock);

        if ($baris->isEmpty()) {
            return;
        }

        $gudang = Warehouse::withoutGlobalScopes()->findOrFail($ret->from_warehouse_id);

        foreach ($baris as $l) {
            try {
                $this->reservasi->reserveHard($l->item, $gudang, (float) $l->qty_base, array_filter([
                    'bin_id' => $l->from_bin_id,
                    'lot_id' => $l->lot_id,
                    'serial_id' => $l->serial_id,
                    'piece_id' => $l->piece_id,
                ]), 'goods_return', (int) $ret->id, (int) $l->id, $actor);
            } catch (LedgerException $e) {
                throw ReturnRuleException::rule(
                    'BR-STK-03',
                    'Baris '.$l->item->code.' di '.$gudang->code.' tidak bisa dicadangkan untuk retur: '.$e->getMessage(),
                );
            }
        }
    }

    private function cobaBuatPicking(GoodsReturn $ret, ?User $actor): void
    {
        try {
            DB::transaction(fn () => $this->picking->forGoodsReturn($ret->refresh(), $actor));
        } catch (ShipmentRuleException|LedgerException $e) {
            activity('return')->performedOn($ret)->causedBy($actor)
                ->withProperties(['aturan' => $e->rule, 'pesan' => $e->getMessage()])
                ->log('Tugas picking SJ balik belum bisa dibuat otomatis');
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Request\Support;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Contracts\ApprovalHandler;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Support\ApprovalContext;
use App\Domain\Master\Models\Item;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Request\Models\MaterialRequestLine;
use App\Domain\Stock\Actions\ManageReservation;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Transfer\Support\BackorderTransfers;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;

/**
 * Penangan approval REQ untuk mesin approval (20-approval §13, D-28).
 *
 * Persetujuan akhir adalah saat REQ berhenti menjadi niat dan mulai mengikat
 * stok: setiap baris bersumber stok mendapat **reservasi lunak** (BR-REQ-05).
 * Logika ini dulu ada di `ApproveRequest`; kini dipanggil mesin, baik setelah
 * lapis terakhir setuju maupun saat tidak ada aturan (A-08).
 */
class RequestApprovalHandler implements ApprovalHandler
{
    public function __construct(private readonly ManageReservation $reservasi) {}

    public function documentType(): ApprovalDocumentType
    {
        return ApprovalDocumentType::MaterialRequest;
    }

    public function approvePermission(): string
    {
        return 'request.approve';
    }

    public function find(int $documentId): ?Model
    {
        return MaterialRequest::withoutGlobalScopes()->find($documentId);
    }

    public function findByNumber(string $number): ?Model
    {
        return MaterialRequest::query()->where('number', $number)->first();
    }

    public function number(Model $document): string
    {
        return (string) $document->number;
    }

    public function url(Model $document): ?string
    {
        return route('requests.show', $document->getKey());
    }

    public function logName(): string
    {
        return 'request';
    }

    /** @param  MaterialRequest  $document */
    public function context(Model $document): ApprovalContext
    {
        $baris = MaterialRequestLine::query()->open()
            ->where('material_request_id', $document->getKey())->get();

        $item = Item::query()->whereIn('id', $baris->pluck('item_id')->filter()->unique())
            ->get(['id', 'item_category_id', 'ownership_model']);

        return new ApprovalContext(
            documentType: ApprovalDocumentType::MaterialRequest,
            documentId: (int) $document->getKey(),
            documentNumber: (string) $document->number,
            warehouseIds: $baris->pluck('source_warehouse_id')->filter()->map(fn ($v) => (int) $v)->unique()->values()->all(),
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

    /** @param  MaterialRequest  $document */
    public function onApproved(Model $document, ?User $actor): void
    {
        $document->refresh();

        if ($document->status !== MaterialRequestStatus::PendingApproval) {
            throw RequestRuleException::rule('BR-REQ-05', 'Hanya REQ yang menunggu persetujuan yang bisa disetujui.');
        }

        // BR-REQ-05: baris tanpa sumber menahan approval seluruh dokumen.
        if ($document->lines()->withoutSource()->exists()) {
            throw RequestRuleException::rule('BR-REQ-05', 'Masih ada baris tanpa gudang sumber atau cara pemenuhan.');
        }

        $document->forceFill([
            'status' => MaterialRequestStatus::Approved,
            'approved_by' => $actor?->id,
            'approved_at' => now(),
        ])->save();

        $this->buatReservasi($document, $actor);

        // BR-REQ-05: baris bersumber transfer melahirkan TRF backorder dari
        // gudang lain ke gudang sumber baris (A-106); gagal = approval tertahan.
        app(BackorderTransfers::class)->createFor($document, $actor);

        activity('request')->performedOn($document)->causedBy($actor)->log('REQ disetujui');
    }

    /** @param  MaterialRequest  $document */
    public function onRejected(Model $document, int $reasonCodeId, ?string $notes, User $actor): void
    {
        $document->refresh()->forceFill([
            'status' => MaterialRequestStatus::Rejected,
            'cancel_reason_id' => $reasonCodeId,
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ])->save();

        activity('request')->performedOn($document)->causedBy($actor)
            ->withProperties(['reason_code_id' => $reasonCodeId, 'notes' => $notes])
            ->log('REQ ditolak approver');
    }

    /**
     * Reservasi lunak untuk baris bersumber stok. Baris bersumber transfer
     * atau pembelian belum punya barang untuk dijanjikan (BR-REQ-08).
     */
    private function buatReservasi(MaterialRequest $request, ?User $actor): void
    {
        $baris = $request->lines()->open()->with('item')->get()
            ->filter(fn (MaterialRequestLine $l) => $l->fulfillment_source?->reservesOnApproval() === true);

        foreach ($baris as $l) {
            // Gudang dibaca tanpa cakupan: approver lapis akhir (mis. Manajemen
            // atau kepala gudang lain) belum tentu bercakupan gudang baris ini.
            $gudang = Warehouse::withoutGlobalScopes()->find($l->source_warehouse_id);

            if ($l->item === null || $gudang === null) {
                continue;
            }

            try {
                $this->reservasi->reserveSoft($l->item, $gudang, (float) $l->qty_base, 'material_request', (int) $request->id, (int) $l->id, $actor);
            } catch (LedgerException $e) {
                // Stok tersedia berkurang antara tinjau dan approval: pesan
                // menyebut baris yang harus dipindah ke transfer/pembelian.
                throw RequestRuleException::rule('BR-REQ-05', 'Baris '.$l->displayName().' tidak bisa direservasi: '.$e->getMessage());
            }

            $l->forceFill(['qty_reserved' => $l->qty_base])->save();
        }
    }
}

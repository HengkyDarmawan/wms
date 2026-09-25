<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Support;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Contracts\ApprovalHandler;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Support\ApprovalContext;
use App\Domain\Conversion\Enums\ConversionStatus;
use App\Domain\Conversion\Exceptions\ConversionRuleException;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Conversion\Models\ConversionInput;
use App\Domain\Conversion\Models\ConversionOutput;
use App\Domain\Master\Models\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Penangan approval CNV (Katalog §2.10, 20-approval §3, A-153).
 *
 * Approval CNV opsional: `conversion.submit` hanya dipakai bila ada aturan yang
 * cocok, jadi tanpa aturan tidak ada lapis minimum. Disetujui = CNV diposting
 * dan `completed`; ditolak = kembali `draft` dengan alasan (tidak ada status
 * "ditolak" untuk CNV) sehingga bisa diperbaiki, diajukan ulang, atau dibatalkan.
 */
class ConversionApprovalHandler implements ApprovalHandler
{
    public function __construct(private readonly ConversionCompletion $selesai) {}

    public function documentType(): ApprovalDocumentType
    {
        return ApprovalDocumentType::Conversion;
    }

    public function approvePermission(): string
    {
        return 'conversion.approve';
    }

    public function find(int $documentId): ?Model
    {
        return Conversion::withoutGlobalScopes()->find($documentId);
    }

    public function findByNumber(string $number): ?Model
    {
        return Conversion::query()->where('number', $number)->first();
    }

    public function number(Model $document): string
    {
        return (string) $document->number;
    }

    public function url(Model $document): ?string
    {
        return route('conversions.show', $document->getKey());
    }

    public function logName(): string
    {
        return 'conversion';
    }

    /** @param  Conversion  $document */
    public function context(Model $document): ApprovalContext
    {
        $input = ConversionInput::query()->where('conversion_id', $document->getKey())->get();
        $hasil = ConversionOutput::query()->where('conversion_id', $document->getKey())->get();
        $item = Item::query()->whereIn('id', $input->pluck('item_id')->merge($hasil->pluck('item_id'))->unique())
            ->get(['id', 'item_category_id', 'ownership_model']);
        $pengaju = array_values(array_unique(array_filter([(int) $document->prepared_by, (int) $document->submitted_by])));

        return new ApprovalContext(
            documentType: ApprovalDocumentType::Conversion,
            documentId: (int) $document->getKey(),
            documentNumber: (string) $document->number,
            warehouseIds: [(int) $document->warehouse_id],
            projectId: (int) $document->project_id,
            categoryIds: ApprovalContext::withAncestors($item->pluck('item_category_id')->filter()->all()),
            ownershipModels: $item->map(fn (Item $i) => $i->ownership_model?->value)->filter()->unique()->values()->all(),
            lineCount: $input->count() + $hasil->count(),
            // BR-APR-07: jumlah satuan dasar input terbesar.
            maxLineQty: (float) ($input->map(fn (ConversionInput $l) => abs((float) $l->qty_base))->max() ?? 0),
            requesterId: $document->submitted_by !== null ? (int) $document->submitted_by : ($document->prepared_by !== null ? (int) $document->prepared_by : null),
            requesterIds: $pengaju,
        );
    }

    public function attachSnapshot(Model $document, ?ApprovalSnapshot $snapshot): void
    {
        $document->forceFill(['approval_snapshot_id' => $snapshot?->id])->save();
    }

    /** A-153: tanpa aturan, CNV diselesaikan lewat `conversion.complete`, bukan approval. */
    public function fallbackSteps(Model $document): array
    {
        return [];
    }

    /** @param  Conversion  $document */
    public function onApproved(Model $document, ?User $actor): void
    {
        DB::transaction(function () use ($document, $actor): void {
            $cnv = Conversion::withoutGlobalScopes()->lockForUpdate()->findOrFail($document->getKey());

            if ($cnv->status !== ConversionStatus::PendingApproval) {
                throw ConversionRuleException::rule('BR-GEN-01', 'Hanya CNV yang menunggu approval yang bisa disetujui.');
            }

            $cnv->forceFill([
                'approved_by' => $actor?->id,
                'approved_at' => now(),
                'reject_reason_id' => null,
            ])->save();

            $this->selesai->handle($cnv, $actor, $cnv->submitter);
        });

        $document->refresh();
    }

    /** @param  Conversion  $document */
    public function onRejected(Model $document, int $reasonCodeId, ?string $notes, User $actor): void
    {
        $document->refresh()->forceFill([
            'status' => ConversionStatus::Draft,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'reject_reason_id' => $reasonCodeId,
        ])->save();

        activity('conversion')->performedOn($document)->causedBy($actor)
            ->withProperties(['reason_code_id' => $reasonCodeId, 'keterangan' => $notes])
            ->log('CNV ditolak; kembali Draf (perbaiki, ajukan ulang, atau batalkan)');
    }
}

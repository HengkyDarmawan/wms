<?php

declare(strict_types=1);

namespace App\Domain\Count\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Count\Enums\StockCountStatus;
use App\Domain\Count\Exceptions\CountRuleException;
use App\Domain\Count\Models\CountLine;
use App\Domain\Count\Models\StockCount;
use App\Domain\Count\Enums\RootCauseCategory;

/**
 * Permission: `count.reconcile` — mengisi kategori akar masalah dan catatan
 * satu baris selisih (alur 8 langkah 11, BR-OPN-07).
 *
 * Boleh setelah baris diklasifikasi dan selama hasil belum menunggu
 * approval; setelah ditolak approver, akar masalah boleh diperbaiki sebelum
 * diajukan ulang (A-97). Data yang sedang diputus approver tidak berubah
 * (BR-APR-01).
 */
class RecordCountRootCause
{
    public function __construct(private readonly ApprovalEngine $approval) {}

    public function handle(CountLine $line, ?string $rootCause, ?string $note = null, ?User $actor = null): CountLine
    {
        $count = StockCount::withoutGlobalScopes()->findOrFail($line->stock_count_id);

        $boleh = $count->status->isCounting()
            || ($count->status === StockCountStatus::Reconciling
                && $this->approval->pendingSnapshot(ApprovalDocumentType::StockCount, (int) $count->id) === null);

        if (! $boleh) {
            throw CountRuleException::rule('BR-APR-01', 'Hasil opname sedang menunggu approval dan tidak bisa diubah.');
        }

        if ($line->final_qty === null) {
            throw CountRuleException::rule('BR-OPN-04', 'Baris ini belum selesai dihitung dan diklasifikasi.');
        }

        $kategori = $rootCause === null || $rootCause === '' ? null : RootCauseCategory::tryFrom($rootCause);

        if ($rootCause !== null && $rootCause !== '' && $kategori === null) {
            throw CountRuleException::field('BR-OPN-07', 'root_cause', 'Kategori akar masalah tidak dikenal.');
        }

        $catatan = $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 255) : null;

        $line->forceFill(['root_cause' => $kategori, 'note' => $catatan])->save();

        activity('count')->performedOn($count)->causedBy($actor)
            ->withProperties(['baris' => $line->id, 'akar_masalah' => $kategori?->value])
            ->log('Akar masalah baris '.$line->item?->code.' di '.$line->bin?->code.': '.($kategori?->label() ?? '—'));

        return $line->refresh();
    }
}

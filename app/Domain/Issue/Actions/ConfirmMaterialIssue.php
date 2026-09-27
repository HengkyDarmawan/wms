<?php

declare(strict_types=1);

namespace App\Domain\Issue\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Issue\Enums\MaterialIssueStatus;
use App\Domain\Issue\Exceptions\IssueRuleException;
use App\Domain\Issue\Models\MaterialIssue;
use App\Domain\Issue\Support\IssuableStock;
use App\Domain\Issue\Support\IssuePoster;
use App\Domain\Label\Exceptions\LabelRuleException;
use App\Domain\Label\Support\PackageLabelLedger;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `issue.confirm` — `draft → confirmed` (Katalog §2.9).
 *
 * ISU biasa: stok tersedia diperiksa ulang (bin tidak dibeku, saldo dikurangi
 * alokasi keras, Stok Tersedia per item), lalu setiap baris keluar dari bin
 * Gudang Site lewat `StockLedger` dengan kejadian `material_consumed` penanda
 * proyek (matriks §14).
 *
 * ISU pembalik: konfirmasi = **mengajukan ke approval** (BR-GEN-04). Dokumen
 * tetap `draft` sampai lapis terakhir setuju; saat itu penangan approval
 * membalik pergerakan asal dan ISU menjadi `confirmed` (A-150).
 */
class ConfirmMaterialIssue
{
    public function __construct(
        private readonly IssuableStock $stock,
        private readonly IssuePoster $poster,
        private readonly ApprovalEngine $approval,
    ) {}

    public function handle(MaterialIssue $issue, ?User $actor = null): MaterialIssue
    {
        return DB::transaction(function () use ($issue, $actor) {
            $isu = MaterialIssue::withoutGlobalScopes()->with('project', 'warehouse')->lockForUpdate()->findOrFail($issue->id);

            if ($isu->status !== MaterialIssueStatus::Draft) {
                throw IssueRuleException::rule('BR-GEN-01', 'Hanya ISU berstatus Draf yang bisa dikonfirmasi.');
            }

            if ($actor !== null && ! $actor->canAccessWarehouse((int) $isu->warehouse_id)) {
                throw IssueRuleException::rule('BR-ACC-05', 'Gudang Site ISU ini di luar cakupan Anda.');
            }

            if (! $isu->project?->acceptsDocuments()) {
                throw IssueRuleException::rule('BR-PRJ-01', 'Proyek '.$isu->project?->code.' tidak aktif; pemakaian tidak bisa dikonfirmasi.');
            }

            if ($isu->lines()->doesntExist()) {
                throw IssueRuleException::rule('BR-PRJ-08', 'ISU tanpa baris tidak bisa dikonfirmasi.');
            }

            return $isu->isReversal() ? $this->ajukanPembalik($isu, $actor) : $this->pakai($isu, $actor);
        });
    }

    private function pakai(MaterialIssue $isu, ?User $actor): MaterialIssue
    {
        $this->stock->assertAvailable($isu->warehouse, $isu->lines()->get()->map(fn ($l) => [
            'item_id' => (int) $l->item_id,
            'bin_id' => (int) $l->bin_id,
            'lot_id' => $l->lot_id === null ? null : (int) $l->lot_id,
            'serial_id' => $l->serial_id === null ? null : (int) $l->serial_id,
            'piece_id' => $l->piece_id === null ? null : (int) $l->piece_id,
            'qty_base' => (float) $l->qty_base,
        ])->all());

        $baris = $isu->lines()->with('item:id,code')->orderBy('id')->get();
        $label = app(PackageLabelLedger::class);

        try {
            // A-299: barang berlabel yang dipakai habis wajib dipindai labelnya.
            $label->assertCoverage((int) $isu->warehouse_id, $baris->groupBy(fn ($l) => $l->item_id.':'.$l->lot_id)
                ->map(fn ($g) => [
                    'item_id' => (int) $g->first()->item_id,
                    'lot_id' => $g->first()->lot_id === null ? null : (int) $g->first()->lot_id,
                    'qty' => round((float) $g->sum('qty_base'), 4),
                    'claimed' => round((float) $g->sum(fn ($l) => $l->labelledQty()), 4),
                    'label' => 'Barang '.$g->first()->item?->code,
                ])->values()->all());

            $this->poster->consume($isu, $actor);

            foreach ($baris->filter(fn ($l) => ($l->labels ?? []) !== []) as $l) {
                $label->issue(
                    $label->resolveClaims($l->labels, (int) $isu->warehouse_id, (int) $l->item_id, $l->lot_id === null ? null : (int) $l->lot_id, 'Baris '.$l->item?->code, true),
                    ['type' => 'material_issue', 'id' => (int) $isu->id, 'line_id' => (int) $l->id, 'number' => $isu->number],
                    $actor,
                );
            }
        } catch (LabelRuleException $e) {
            throw IssueRuleException::rule($e->rule, $e->getMessage());
        }

        $isu->forceFill([
            'status' => MaterialIssueStatus::Confirmed,
            'confirmed_by' => $actor?->id,
            'confirmed_at' => now(),
        ])->save();

        activity('issue')->performedOn($isu)->causedBy($actor)
            ->withProperties(['baris' => $isu->lines()->count()])
            ->log('ISU dikonfirmasi; barang keluar dari Gudang Site (material_consumed)');

        return $isu->refresh();
    }

    /** BR-GEN-04: pembalik lewat mesin approval, minimal satu lapis (A-150). */
    private function ajukanPembalik(MaterialIssue $isu, ?User $actor): MaterialIssue
    {
        if ($this->approval->pendingSnapshot(ApprovalDocumentType::MaterialIssue, (int) $isu->id) !== null) {
            throw IssueRuleException::rule('BR-APR-01', 'ISU pembalik ini sudah diajukan dan sedang menunggu approval.');
        }

        $isu->forceFill([
            'submitted_by' => $actor?->id,
            'submitted_at' => now(),
            'approved_by' => null,
            'approved_at' => null,
            'reject_reason_id' => null,
        ])->save();

        activity('issue')->performedOn($isu)->causedBy($actor)->log('ISU pembalik diajukan ke approval');

        $this->approval->submit(ApprovalDocumentType::MaterialIssue, $isu, $actor);

        return $isu->refresh();
    }
}

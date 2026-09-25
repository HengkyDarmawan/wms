<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Conversion\Enums\ConversionStatus;
use App\Domain\Conversion\Exceptions\ConversionRuleException;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Conversion\Models\ConversionInput;
use App\Domain\Conversion\Models\ConversionOutput;
use App\Domain\Conversion\Support\ConversionApprovalRoute;
use App\Domain\Conversion\Support\ConversionLines;
use App\Domain\Conversion\Support\ConversionReversibility;
use App\Domain\Conversion\Support\ConvertibleStock;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `conversion.submit` — `draft → pending_approval` (Katalog §2.10),
 * hanya bila ada aturan approval yang cocok (A-153).
 *
 * Input, neraca ukuran, dan (untuk pembalik) BR-CNV-05 diperiksa sebelum
 * diajukan supaya approver tidak menyetujui CNV yang pasti gagal diposting;
 * semuanya diulang saat lapis terakhir setuju.
 */
class SubmitConversion
{
    public function __construct(
        private readonly ConversionApprovalRoute $route,
        private readonly ApprovalEngine $approval,
        private readonly ConvertibleStock $stock,
        private readonly ConversionLines $lines,
        private readonly ConversionReversibility $reversibility,
    ) {}

    public function handle(Conversion $conversion, ?User $actor = null): Conversion
    {
        return DB::transaction(function () use ($conversion, $actor) {
            $cnv = Conversion::withoutGlobalScopes()->with('project', 'warehouse', 'reversalOf')->lockForUpdate()->findOrFail($conversion->id);

            if ($cnv->status !== ConversionStatus::Draft) {
                throw ConversionRuleException::rule('BR-GEN-01', 'Hanya CNV berstatus Draf yang bisa diajukan.');
            }

            if ($actor !== null && ! $actor->canAccessWarehouse((int) $cnv->warehouse_id)) {
                throw ConversionRuleException::rule('BR-ACC-05', 'Gudang CNV ini di luar cakupan Anda.');
            }

            if (! $cnv->project?->acceptsDocuments()) {
                throw ConversionRuleException::rule('BR-PRJ-01', 'Proyek '.$cnv->project?->code.' tidak aktif; CNV tidak bisa diajukan.');
            }

            if ($this->approval->pendingSnapshot(ApprovalDocumentType::Conversion, (int) $cnv->id) !== null) {
                throw ConversionRuleException::rule('BR-APR-01', 'CNV ini sudah diajukan dan sedang menunggu approval.');
            }

            if (! $this->route->needsApproval($cnv)) {
                throw ConversionRuleException::rule('BR-APR-02', 'Tidak ada aturan approval yang berlaku untuk CNV ini; selesaikan langsung.');
            }

            $this->periksa($cnv);

            $cnv->forceFill([
                'status' => ConversionStatus::PendingApproval,
                'submitted_by' => $actor?->id,
                'submitted_at' => now(),
                'approved_by' => null,
                'approved_at' => null,
                'reject_reason_id' => null,
            ])->save();

            activity('conversion')->performedOn($cnv)->causedBy($actor)->log('CNV diajukan ke approval');

            $this->approval->submit(ApprovalDocumentType::Conversion, $cnv, $actor);

            return $cnv->refresh();
        });
    }

    private function periksa(Conversion $cnv): void
    {
        $inputs = $cnv->inputs()->orderBy('id')->get();

        if ($inputs->isEmpty()) {
            throw ConversionRuleException::rule('BR-CNV-02', 'CNV tanpa input tidak bisa diajukan.');
        }

        if ($cnv->isReversal()) {
            $this->reversibility->assert($cnv->reversalOf);

            return;
        }

        $this->stock->assertAvailable($cnv->warehouse, $inputs->map(fn (ConversionInput $i) => [
            'item_id' => (int) $i->item_id,
            'bin_id' => (int) $i->bin_id,
            'lot_id' => $i->lot_id === null ? null : (int) $i->lot_id,
            'piece_id' => $i->piece_id === null ? null : (int) $i->piece_id,
            'qty_base' => (float) $i->qty_base,
        ])->all());

        $this->lines->assertBalanced(
            $cnv->conversion_type,
            $inputs->map(fn (ConversionInput $i) => ['item_id' => (int) $i->item_id, 'qty_base' => (float) $i->qty_base])->all(),
            $cnv->outputs()->get()->map(fn (ConversionOutput $o) => ['output_kind' => $o->output_kind, 'item_id' => (int) $o->item_id, 'qty_base' => (float) $o->qty_base])->all(),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Support;

use App\Domain\Access\Models\User;
use App\Domain\Conversion\Enums\ConversionStatus;
use App\Domain\Conversion\Exceptions\ConversionRuleException;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Conversion\Models\ConversionInput;
use App\Domain\Conversion\Models\ConversionOutput;

/**
 * `→ completed` (Katalog §2.10): dipakai `conversion.complete` (tanpa aturan
 * approval) dan penangan approval (lapis terakhir setuju).
 *
 * Guard diulang di sini karena draf tidak memegang stok (A-154): proyek masih
 * menerima dokumen, input masih tersedia, neraca ukuran seimbang (BR-CNV-02).
 * CNV pembalik memeriksa BR-CNV-05 atas CNV asalnya (A-157).
 */
class ConversionCompletion
{
    public function __construct(
        private readonly ConvertibleStock $stock,
        private readonly ConversionLines $lines,
        private readonly ConversionPoster $poster,
        private readonly ConversionReversibility $reversibility,
    ) {}

    public function handle(Conversion $cnv, ?User $actor, ?User $completer = null): Conversion
    {
        $cnv->loadMissing('project', 'warehouse', 'reversalOf');

        if (! $cnv->project?->acceptsDocuments()) {
            throw ConversionRuleException::rule('BR-PRJ-01', 'Proyek '.$cnv->project?->code.' tidak aktif; konversi tidak bisa diselesaikan.');
        }

        $inputs = $cnv->inputs()->orderBy('id')->get();
        $outputs = $cnv->outputs()->orderBy('id')->get();

        if ($cnv->isReversal()) {
            $this->reversibility->assert($cnv->reversalOf);
            $this->poster->reverse($cnv);
        } else {
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
                $outputs->map(fn (ConversionOutput $o) => ['output_kind' => $o->output_kind, 'item_id' => (int) $o->item_id, 'qty_base' => (float) $o->qty_base])->all(),
            );

            $this->poster->post($cnv, $actor);
        }

        $cnv->forceFill($this->lines->totals($inputs, $outputs) + [
            'status' => ConversionStatus::Completed,
            'completed_by' => ($completer ?? $actor)?->id,
            'completed_at' => now(),
        ])->save();

        activity('conversion')->performedOn($cnv)->causedBy($actor)
            ->withProperties(['input' => $inputs->count(), 'hasil' => $outputs->count()])
            ->log($cnv->isReversal()
                ? 'CNV pembalik selesai; konversi '.$cnv->reversalOf?->number.' dibalik (material_converted)'
                : 'CNV selesai; input keluar, hasil masuk stok (material_converted)');

        return $cnv->refresh();
    }
}

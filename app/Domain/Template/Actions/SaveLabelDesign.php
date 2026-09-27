<?php

declare(strict_types=1);

namespace App\Domain\Template\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Enums\LabelCodeMode;
use App\Domain\Template\Models\DocumentTemplate;
use App\Domain\Template\Models\LabelDesign;
use App\Domain\Template\Models\LabelFormat;
use App\Domain\Template\Support\LabelDesignRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Simpan tata letak label satu jenis × ukuran (A-262, 18 §6). Permission:
 * `document_layout.manage`. Posisi elemen dirapikan ke dalam batas label
 * (LabelDesignRules::normalize); kode yang dicetak = barcode, QR, atau
 * keduanya. Bila diminta, ukuran ini menjadi bawaan jenis label tersebut.
 */
class SaveLabelDesign
{
    /** @param  array<string, mixed>  $elements */
    public function handle(DocumentTemplateType $type, LabelFormat $format, string $codeMode, array $elements, bool $makeDefault, User $actor): LabelDesign
    {
        $mode = LabelCodeMode::tryFrom($codeMode);
        $galat = [];

        if (! $type->isLabel()) {
            $galat['type'] = __('Jenis label tidak dikenal.');
        }
        if (! $format->is_active) {
            $galat['format'] = __('Ukuran label ini nonaktif.');
        }
        if ($mode === null) {
            $galat['code_mode'] = __('Pilih kode yang dicetak.');
        }
        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }

        // Barcode/QR ikut pilihan kode, bukan centang elemen, supaya keduanya tidak bertentangan.
        $rapi = LabelDesignRules::normalize($elements, $format);
        $rapi['barcode']['visible'] = $mode->hasBarcode();
        $rapi['qr']['visible'] = $mode->hasQr();

        return DB::transaction(function () use ($type, $format, $mode, $rapi, $makeDefault, $actor) {
            $desain = LabelDesign::query()->updateOrCreate(
                ['document_type' => $type->value, 'label_format_id' => $format->id],
                ['code_mode' => $mode, 'elements' => $rapi, 'updated_by' => $actor->id],
            );

            if ($makeDefault) {
                DocumentTemplate::forType($type)->forceFill(['label_format_id' => $format->id])->save();
            }

            activity('template')->performedOn($desain)->causedBy($actor)
                ->withProperties(['jenis' => $type->value, 'ukuran' => $format->code, 'kode' => $mode->value, 'bawaan' => $makeDefault])
                ->log('Desain '.mb_strtolower($type->label()).' ukuran '.$format->code.' disimpan');

            return $desain->refresh();
        });
    }
}

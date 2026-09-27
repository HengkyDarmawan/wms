<?php

declare(strict_types=1);

namespace App\Domain\Template\Models;

use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Enums\LabelCodeMode;
use App\Domain\Template\Support\LabelDesignRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tata letak label per jenis × format (A-262, 18 §3.4). `elements` berisi
 * posisi dan gaya enam elemen tetap (LabelDesignRules::ELEMEN) dalam mm.
 * Belum pernah disimpan = tata letak otomatis dari ukuran label.
 *
 * @property DocumentTemplateType $document_type
 * @property LabelCodeMode $code_mode
 * @property array<string, array<string, mixed>> $elements
 */
class LabelDesign extends Model
{
    protected $table = 'label_designs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'document_type' => DocumentTemplateType::class,
            'code_mode' => LabelCodeMode::class,
            'elements' => 'array',
        ];
    }

    public function format(): BelongsTo
    {
        return $this->belongsTo(LabelFormat::class, 'label_format_id');
    }

    /** Desain tersimpan, atau desain otomatis (belum disimpan) untuk pasangan ini. */
    public static function resolve(DocumentTemplateType $type, LabelFormat $format): self
    {
        $desain = static::query()->where('document_type', $type->value)
            ->where('label_format_id', $format->id)->first();

        if ($desain !== null) {
            // Ukuran format bisa berubah setelah desain disimpan: rapikan ke batas label.
            $desain->elements = LabelDesignRules::normalize($desain->elements ?? [], $format);

            return $desain;
        }

        return new self([
            'document_type' => $type,
            'label_format_id' => $format->id,
            'code_mode' => LabelCodeMode::Both,
            'elements' => LabelDesignRules::defaults($format, LabelCodeMode::Both),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Template\Models;

use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Enums\PaperSize;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registri template per jenis dokumen/label (18 §3.2). F1 hanya menyimpan
 * kertas dan layout; isinya Blade bawaan, `body_html` untuk editor F2 (A-122).
 *
 * @property DocumentTemplateType $document_type
 * @property PaperSize $paper
 */
class DocumentTemplate extends Model
{
    public const NAMA_BAWAAN = 'Bawaan';

    protected $table = 'document_templates';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'document_type' => DocumentTemplateType::class,
            'paper' => PaperSize::class,
            'is_default' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function layout(): BelongsTo
    {
        return $this->belongsTo(DocumentLayout::class, 'layout_id');
    }

    /** Template bawaan untuk jenis ini; dibuat otomatis bila belum di-seed (TC-TPL-11). */
    public static function forType(DocumentTemplateType $type): self
    {
        return static::query()
            ->where('document_type', $type->value)
            ->where('is_default', true)
            ->orderBy('id')
            ->first()
            ?? static::query()->create([
                'document_type' => $type,
                'name' => self::NAMA_BAWAAN,
                'layout_id' => DocumentLayout::current()->id,
                'paper' => $type->defaultPaper(),
                'is_default' => true,
                'version' => 1,
            ]);
    }

    /** Semua jenis dengan template bawaannya, urut sesuai enum. */
    public static function ensureDefaults(): void
    {
        foreach (DocumentTemplateType::cases() as $type) {
            static::forType($type);
        }
    }
}

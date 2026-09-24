<?php

declare(strict_types=1);

namespace App\Domain\Template\Models;

use App\Domain\Template\Enums\DocumentTemplateType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Layout induk cetak per company (18 §3.1). F1 satu layout (`is_default`);
 * kop dan footer berupa teks biasa yang di-escape saat dicetak (A-122).
 */
class DocumentLayout extends Model
{
    public const WARNA_BAWAAN = '#1f4e79';

    /** A-125: paling banyak empat kotak tanda tangan per dokumen. */
    public const MAKS_BLOK = 4;

    protected $table = 'document_layouts';

    protected $guarded = [];

    protected $attributes = [
        'is_default' => false,
    ];

    protected function casts(): array
    {
        return [
            'colors' => 'array',
            'signature_blocks' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function templates(): HasMany
    {
        return $this->hasMany(DocumentTemplate::class, 'layout_id');
    }

    /** Layout yang berlaku; dibuat dengan nilai bawaan bila company belum punya. */
    public static function current(): self
    {
        return static::query()->where('is_default', true)->orderBy('id')->first()
            ?? static::query()->create(['name' => 'Standar', 'is_default' => true]);
    }

    public function accent(): string
    {
        $warna = $this->colors['accent'] ?? null;

        return is_string($warna) && preg_match('/^#[0-9a-fA-F]{6}$/', $warna) ? $warna : self::WARNA_BAWAAN;
    }

    /** @return array<int, string> */
    public function signatureBlocksFor(DocumentTemplateType $type): array
    {
        $blok = $this->signature_blocks[$type->value] ?? null;

        return is_array($blok) && $blok !== [] ? array_values($blok) : $type->defaultSignatureBlocks();
    }
}

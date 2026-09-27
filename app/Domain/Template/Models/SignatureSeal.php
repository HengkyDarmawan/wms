<?php

declare(strict_types=1);

namespace App\Domain\Template\Models;

use App\Domain\Template\Enums\DocumentTemplateType;
use Illuminate\Database\Eloquent\Model;

/**
 * Segel tanda tangan satu kotak di satu dokumen (A-264). Token acak menjadi
 * isi QR; sidik (HMAC kunci aplikasi) menandai baris tidak diubah diam-diam.
 *
 * @property DocumentTemplateType $document_type
 */
class SignatureSeal extends Model
{
    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    protected $table = 'signature_seals';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'document_type' => DocumentTemplateType::class,
            'block_no' => 'integer',
            'acted_at' => 'datetime',
            'sealed_at' => 'datetime',
        ];
    }

    /** Kode pendek untuk dibaca manusia: 12 karakter sidik, berkelompok 4. */
    public function shortCode(): string
    {
        return implode('-', str_split(strtoupper(substr($this->fingerprint, 0, 12)), 4));
    }
}

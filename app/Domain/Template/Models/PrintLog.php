<?php

declare(strict_types=1);

namespace App\Domain\Template\Models;

use App\Domain\Access\Models\User;
use App\Domain\Template\Enums\DocumentTemplateType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kali cetak dokumen (A-263). Hanya ditambah, tidak diubah atau dihapus.
 *
 * @property DocumentTemplateType $document_type
 */
class PrintLog extends Model
{
    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    protected $table = 'print_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'document_type' => DocumentTemplateType::class,
            'copy_no' => 'integer',
            'printed_at' => 'datetime',
        ];
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Resep konversi `[F2]` (BR-CNV-06) — stub (BR-GEN-10): tabel dan model ada,
 * belum ada layar. Resep hanya mempercepat pengisian, tidak mengunci hasil nyata.
 */
class ConversionRecipe extends Model
{
    protected $table = 'conversion_recipes';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'is_active' => 'boolean',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lapis 1 dari P-08: fitur yang dinyalakan platform per company
 * (whatsapp, offline_sync, rfid, …). Lapis 2 ada di pengaturan company (tenant).
 */
class FeatureFlag extends Model
{
    use HasFactory;

    protected $connection = 'central';

    protected $table = 'feature_flags';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'config' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

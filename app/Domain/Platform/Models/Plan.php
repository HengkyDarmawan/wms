<?php

declare(strict_types=1);

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Paket langganan platform (D-02). Harga di sini adalah harga langganan SaaS,
 * bukan nilai barang — larangan D-07 berlaku untuk data WMS di database tenant.
 */
class Plan extends Model
{
    use HasFactory;

    protected $connection = 'central';

    protected $table = 'plans';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'trial_days' => 'integer',
            'wa_quota' => 'integer',
            'storage_quota_mb' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }
}

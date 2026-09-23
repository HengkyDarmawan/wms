<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bukti terima per baris SJ (A-64).
 *
 * Ketiga jumlahnya harus berjumlah persis sebanyak yang dikirim. Itu bukan
 * kerewelan: selisih yang tidak dijelaskan berarti ada barang yang hilang dari
 * pembukuan tanpa seorang pun bertanggung jawab.
 */
class ProofOfDeliveryLine extends Model
{
    use HasFactory;

    protected $table = 'proof_of_delivery_lines';

    protected $guarded = [];

    protected $attributes = [
        'qty_good' => 0,
        'qty_damaged' => 0,
        'qty_missing' => 0,
    ];

    protected function casts(): array
    {
        return [
            'qty_good' => 'decimal:4',
            'qty_damaged' => 'decimal:4',
            'qty_missing' => 'decimal:4',
        ];
    }

    public function proof(): BelongsTo
    {
        return $this->belongsTo(ProofOfDelivery::class, 'proof_of_delivery_id');
    }

    public function shipmentLine(): BelongsTo
    {
        return $this->belongsTo(ShipmentLine::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProofOfDeliveryUnit::class, 'proof_of_delivery_line_id');
    }

    public function total(): float
    {
        return (float) $this->qty_good + (float) $this->qty_damaged + (float) $this->qty_missing;
    }

    public function hasDamage(): bool
    {
        return (float) $this->qty_damaged > 0;
    }

    public function isComplete(): bool
    {
        return (float) $this->qty_damaged === 0.0 && (float) $this->qty_missing === 0.0;
    }
}

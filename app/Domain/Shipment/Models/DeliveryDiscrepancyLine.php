<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Models;

use App\Domain\Master\Models\ReasonCode;
use App\Domain\Shipment\Enums\ClientDecision;
use App\Domain\Shipment\Enums\DiscrepancyDisposition;
use App\Domain\Shipment\Enums\DiscrepancyType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris DSC — satu jenis selisih pada satu baris SJ (BR-SJ-10).
 *
 * Disposisinya menentukan ke mana barang pergi; keputusan klien menentukan
 * apakah permintaannya masih perlu dipenuhi. Keduanya terpisah karena barang
 * bisa dibawa balik ke gudang sementara klien tetap menunggu penggantinya.
 *
 * @property DiscrepancyType $discrepancy_type
 * @property ?DiscrepancyDisposition $disposition
 * @property ClientDecision $client_decision
 */
class DeliveryDiscrepancyLine extends Model
{
    use HasFactory;

    protected $table = 'delivery_discrepancy_lines';

    protected $guarded = [];

    protected $attributes = [
        'client_decision' => 'still_needed',
    ];

    protected function casts(): array
    {
        return [
            'discrepancy_type' => DiscrepancyType::class,
            'disposition' => DiscrepancyDisposition::class,
            'client_decision' => ClientDecision::class,
            'qty_base' => 'decimal:4',
        ];
    }

    public function discrepancy(): BelongsTo
    {
        return $this->belongsTo(DeliveryDiscrepancy::class, 'delivery_discrepancy_id');
    }

    public function shipmentLine(): BelongsTo
    {
        return $this->belongsTo(ShipmentLine::class);
    }

    public function reasonCode(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'reason_code_id');
    }

    public function scopeUndecided(Builder $query): Builder
    {
        return $query->whereNull('disposition');
    }

    public function isDamaged(): bool
    {
        return $this->discrepancy_type === DiscrepancyType::Damaged;
    }
}

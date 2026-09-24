<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Models;

use App\Domain\Shipment\Enums\OwnershipEffect;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Baris SJ — satu baris PCK yang ikut berangkat.
 *
 * `ownership_effect` diputuskan di sini, bukan saat barang diterima: apa yang
 * terjadi pada kepemilikan sudah ditentukan dokumen asalnya (BR-SJ-04), dan
 * menundanya sampai penerimaan akan membuat penerima memutuskan hal yang bukan
 * haknya.
 *
 * @property OwnershipEffect $ownership_effect
 */
class ShipmentLine extends Model
{
    use HasFactory;

    protected $table = 'shipment_lines';

    protected $guarded = [];

    protected $attributes = [
        'qty_delivered' => 0,
        'ownership_effect' => 'sold',
    ];

    protected function casts(): array
    {
        return [
            'ownership_effect' => OwnershipEffect::class,
            'qty_shipped' => 'decimal:4',
            'qty_delivered' => 'decimal:4',
        ];
    }

    /** Lintas cakupan: SJ transfer/retur dibaca juga oleh gudang penerimanya. */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class)->withoutGlobalScopes();
    }

    public function pickTaskLine(): BelongsTo
    {
        return $this->belongsTo(PickTaskLine::class, 'pick_task_line_id');
    }

    public function proofLines(): HasMany
    {
        return $this->hasMany(ProofOfDeliveryLine::class);
    }

    public function discrepancyLines(): HasMany
    {
        return $this->hasMany(DeliveryDiscrepancyLine::class);
    }

    /** Jumlah yang belum sampai: kurang, rusak, atau belum ada bukti terima. */
    public function outstandingQty(): float
    {
        return max(0, (float) $this->qty_shipped - (float) $this->qty_delivered);
    }
}

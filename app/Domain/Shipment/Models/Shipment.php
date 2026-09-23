<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Models;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopedToUser;
use App\Domain\Master\Models\Carrier;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Vehicle;
use App\Domain\Master\Models\Vendor;
use App\Domain\Shipment\Enums\DestinationType;
use App\Domain\Shipment\Enums\ShipmentMethod;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * SJ — surat jalan (15-picking-shipment).
 *
 * Satu SJ boleh memuat beberapa PCK dari beberapa REQ selama gudang asal dan
 * tujuannya sama (BR-SJ-09): yang menentukan satu perjalanan adalah kendaraan,
 * bukan dokumen permintaannya.
 *
 * @property ShipmentStatus $status
 * @property ShipmentMethod $shipment_method
 * @property DestinationType $destination_type
 */
class Shipment extends Model
{
    use HasFactory;
    use LogsActivity;
    use ScopedToUser;

    protected $table = 'shipments';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'prepared',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'shipment_method' => ShipmentMethod::class,
            'destination_type' => DestinationType::class,
            'loaded_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /** BR-ACC-05: SJ mengikuti cakupan gudang asalnya. */
    public static function scopeWarehouseColumn(): ?string
    {
        return 'warehouse_id';
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function destinationProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'destination_project_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function destinationVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'destination_vendor_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function cancelReason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'cancel_reason_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ShipmentLine::class);
    }

    public function proof(): HasOne
    {
        return $this->hasOne(ProofOfDelivery::class);
    }

    public function discrepancies(): HasMany
    {
        return $this->hasMany(DeliveryDiscrepancy::class);
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(DeliveryToken::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ShipmentStatus::Prepared->value,
            ShipmentStatus::Shipped->value,
        ]);
    }

    public function scopeShipped(Builder $query): Builder
    {
        return $query->where('status', ShipmentStatus::Shipped->value);
    }

    /** Bagaimana tujuan ini dituliskan di layar dan surat jalan. */
    public function destinationLabel(): string
    {
        return match ($this->destination_type) {
            DestinationType::ProjectClient => $this->destinationProject?->name ?? '—',
            DestinationType::SiteWarehouse, DestinationType::Warehouse => $this->destinationWarehouse?->name ?? '—',
            DestinationType::Vendor => $this->destinationVendor?->name ?? '—',
        };
    }

    /** Siapa yang membawa, apa pun caranya. */
    public function carrierLabel(): string
    {
        return match ($this->shipment_method) {
            ShipmentMethod::OwnFleet => trim(($this->vehicle?->plate_no ?? '').' · '.($this->driver?->name ?? ''), ' ·'),
            ShipmentMethod::Carrier => trim(($this->carrier?->name ?? '').' · '.($this->tracking_no ?? ''), ' ·'),
            ShipmentMethod::SelfDelivered => (string) ($this->carried_by_name ?? '—'),
        };
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('shipment')
            ->logOnly(['number', 'status', 'shipment_method', 'destination_type', 'shipped_at', 'delivered_at'])
            ->logOnlyDirty();
    }
}

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
use App\Domain\Request\Models\MaterialRequest;
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

    /**
     * A-312: penerima di gudang/proyek tujuan membuka SJ di luar cakupan gudang
     * asalnya, jadi pengikatan rute tidak memakai cakupan global;
     * `ShipmentPolicy::view` yang memutus.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return static::query()->withoutGlobalScopes()->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    /**
     * Daftar SJ bagi user: cakupan gudang asal (BR-ACC-05) ditambah SJ yang
     * tujuannya gudang/proyek dalam cakupannya — tempat ia menjadi penerima (A-312).
     */
    public static function visibleTo(User $user): Builder
    {
        $query = static::query()->withoutGlobalScopes();
        $gudang = $user->accessibleWarehouseIds();

        if ($gudang === null) {
            return $query;
        }

        $proyek = $user->accessibleProjectIds();
        $proyekSite = Warehouse::query()->withoutGlobalScopes()->whereIn('id', $gudang)
            ->whereNotNull('project_id')->pluck('project_id')->all();

        return $query->where(fn (Builder $w) => $w
            ->whereIn('warehouse_id', $gudang)
            ->orWhereIn('destination_warehouse_id', $gudang)
            ->orWhereIn('destination_project_id', array_merge($proyekSite, $proyek ?? [])));
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function destinationProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'destination_project_id');
    }

    /** Proyek tempat barang dijemput (SJ tanpa PCK, A-247). */
    public function originProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'origin_project_id');
    }

    /**
     * SJ tanpa PCK (A-247): berangkat dari proyek, barisnya baris RET/TRF.
     * Stok tidak bergerak saat berangkat karena barangnya tidak di bin gudang.
     */
    public function isWithoutPicking(): bool
    {
        return $this->source_type !== null;
    }

    /** SJ jemput retur (A-248). */
    public function isReturnPickup(): bool
    {
        return $this->source_type === 'goods_return';
    }

    /** SJ antar site TRF aset antar proyek (A-249). */
    public function isSiteTransfer(): bool
    {
        return $this->source_type === 'transfer';
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

    /**
     * A-311: nama driver tertulis di SJ. Data sebelum A-311 diisi balik dari
     * user driver; `driver_id`/`carried_by_name` hanya cadangan pembacaan.
     */
    public function driverName(): ?string
    {
        $nama = $this->driver_name ?: ($this->driver_id !== null ? $this->driver?->name : null);

        return $nama ?: ($this->isWithoutPicking() ? ($this->carried_by_name ?: null) : null);
    }

    /** Nama + HP driver untuk layar dan cetak, mis. "Gani · 6281200000008". */
    public function driverLabel(): string
    {
        return implode(' · ', array_filter([$this->driverName(), $this->driver_phone]));
    }

    /** Siapa yang membawa, apa pun caranya. */
    public function carrierLabel(): string
    {
        return match ($this->shipment_method) {
            // A-247: plat boleh teks bebas bila bukan master; A-311: driver = teks.
            ShipmentMethod::OwnFleet => implode(' · ', array_filter([$this->vehicle?->plate_no ?? $this->vehicle_plate, $this->driverLabel()])),
            ShipmentMethod::Carrier => trim(implode(' · ', array_filter([$this->carrier?->name, $this->vehicle_plate, $this->driverLabel() ?: $this->carried_by_name, $this->tracking_no]))),
            ShipmentMethod::SelfDelivered => (string) ($this->carried_by_name ?? '—'),
        };
    }

    /**
     * REQ yang dimuat SJ ini (lewat PCK). SJ tanpa PCK (A-247) tidak memuat REQ.
     *
     * @return array<int, int>
     */
    public function materialRequestIds(): array
    {
        $pck = PickTaskLine::query()->withoutGlobalScopes()
            ->whereIn('id', $this->lines()->whereNotNull('pick_task_line_id')->pluck('pick_task_line_id'))
            ->pluck('pick_task_id');

        return PickTask::query()->withoutGlobalScopes()->whereIn('id', $pck)
            ->where('source_type', 'material_request')->pluck('source_id')
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * A-313: No. PO klien dari REQ yang dimuat — referensi teks, tercetak di SJ.
     *
     * @return array<int, string>
     */
    public function clientPoNumbers(): array
    {
        return MaterialRequest::query()->withoutGlobalScopes()->whereIn('id', $this->materialRequestIds())
            ->whereNotNull('client_po_number')->orderBy('id')->pluck('client_po_number')->unique()->values()->all();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('shipment')
            ->logOnly(['number', 'status', 'shipment_method', 'destination_type', 'driver_name', 'driver_phone', 'shipped_at', 'delivered_at'])
            ->logOnlyDirty();
    }
}

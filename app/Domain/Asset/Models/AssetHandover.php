<?php

declare(strict_types=1);

namespace App\Domain\Asset\Models;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopedToUser;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Asset\Enums\AssetHandoverStatus;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Serial;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Return\Models\GoodsReturnLine;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Models\ShipmentLine;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * AST — serah terima aset (Katalog Status §2.11, glosarium `asset_handover`,
 * Blueprint §6.8).
 *
 * Satu AST = satu peminjaman satu serial ke satu proyek: lahir otomatis saat
 * SJ aset diterima di proyek, `returned` saat GRN retur-nya diterima, lalu
 * `inspected` setelah pemeriksaan. Stok aset tetap bergerak lewat SJ/GRN/RET;
 * AST hanya mencatat serah terima, meter, dan hari pakai (A-163).
 *
 * Cakupan (BR-ACC-05): gudang asal dan proyek harus sama-sama dalam cakupan.
 *
 * @property AssetHandoverStatus $status
 */
class AssetHandover extends Model
{
    use LogsActivity;
    use ScopedToUser;

    protected $table = 'asset_handovers';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'checked_out',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssetHandoverStatus::class,
            'checked_out_at' => 'datetime',
            'due_return_date' => 'date',
            'returned_at' => 'datetime',
            'lost_at' => 'datetime',
            'meter_out' => 'decimal:1',
            'meter_in' => 'decimal:1',
            'usage_hours' => 'decimal:1',
            'usage_km' => 'decimal:1',
            'usage_days' => 'integer',
        ];
    }

    public static function scopeWarehouseColumn(): ?string
    {
        return 'warehouse_id';
    }

    public static function scopeProjectColumn(): ?string
    {
        return 'project_id';
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(Serial::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withoutGlobalScopes();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withoutGlobalScopes();
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class)->withoutGlobalScopes();
    }

    public function shipmentLine(): BelongsTo
    {
        return $this->belongsTo(ShipmentLine::class);
    }

    public function goodsReturn(): BelongsTo
    {
        return $this->belongsTo(GoodsReturn::class)->withoutGlobalScopes();
    }

    public function goodsReturnLine(): BelongsTo
    {
        return $this->belongsTo(GoodsReturnLine::class);
    }

    public function lostReason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'lost_reason_id');
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id')->withoutGlobalScopes();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(AssetInspection::class);
    }

    public function latestInspection(): ?AssetInspection
    {
        return $this->inspections()->latest('id')->first();
    }

    /** BR-AST-06: dipinjam dan lewat tanggal kembali. */
    public function isOverdue(): bool
    {
        return $this->status === AssetHandoverStatus::CheckedOut
            && $this->lost_at === null
            && $this->due_return_date !== null
            && $this->due_return_date->lt(now()->startOfDay());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('asset')
            ->logOnly(['number', 'status', 'due_return_date', 'meter_out', 'meter_in'])
            ->logOnlyDirty();
    }
}

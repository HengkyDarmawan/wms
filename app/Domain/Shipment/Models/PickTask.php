<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Models;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopedToUser;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Shipment\Enums\PickTaskStatus;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * PCK — tugas mengambil barang dari bin ke Loading Area (15-picking-shipment).
 *
 * Barisnya adalah **alokasi keras**: bukan lagi janji seperti reservasi lunak
 * REQ, melainkan penunjukan bin, lot, serial, atau potongan tertentu.
 *
 * @property PickTaskStatus $status
 */
class PickTask extends Model
{
    use HasFactory;
    use LogsActivity;
    use ScopedToUser;

    protected $table = 'pick_tasks';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'pending',
        'source_type' => 'material_request',
    ];

    protected function casts(): array
    {
        return [
            'status' => PickTaskStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'freeze_override_at' => 'datetime',
        ];
    }

    /** A-240: Kepala Gudang mengizinkan picking dari bin beku untuk SJ mendesak. */
    public function hasFreezeOverride(): bool
    {
        return $this->freeze_override_at !== null;
    }

    public function freezeOverrider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'freeze_override_by');
    }

    /** BR-ACC-05: PCK mengikuti cakupan gudang penggunanya. */
    public static function scopeWarehouseColumn(): ?string
    {
        return 'warehouse_id';
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function cancelReason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'cancel_reason_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PickTaskLine::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            PickTaskStatus::Pending->value,
            PickTaskStatus::InProgress->value,
        ]);
    }

    /** BR-SJ-09: PCK yang siap dimuat ke SJ. */
    public function scopeReadyToShip(Builder $query): Builder
    {
        return $query->where('status', PickTaskStatus::Completed->value);
    }

    public function scopeForSource(Builder $query, string $type, int $id): Builder
    {
        return $query->where('source_type', $type)->where('source_id', $id);
    }

    /** BR-SJ-02: ada baris yang diambil kurang dari alokasinya. */
    public function hasShortPick(): bool
    {
        return $this->lines()->whereColumn('qty_picked', '<', 'qty_allocated')->exists();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('shipment')
            ->logOnly(['number', 'status', 'assigned_to', 'started_at', 'completed_at'])
            ->logOnlyDirty();
    }
}

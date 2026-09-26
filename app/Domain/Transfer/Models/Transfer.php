<?php

declare(strict_types=1);

namespace App\Domain\Transfer\Models;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Shipment\Enums\PickTaskStatus;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Transfer\Enums\TransferKind;
use App\Domain\Transfer\Enums\TransferOrigin;
use App\Domain\Transfer\Enums\TransferStatus;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * TRF — dokumen niat pemindahan stok antar gudang, antar proyek, atau antar
 * Gudang Site dalam satu proyek (Katalog Status §2.7, A-50, BR-RET-01/02).
 *
 * Pergerakan fisiknya lewat PCK di gudang asal, SJ, dan GRN transfer di gudang
 * tujuan (A-33); TRF sendiri tidak pernah menulis kartu stok. TRF aset
 * (`asset_onsite`, A-249) memindahkan aset On-site antar proyek lewat SJ antar
 * site tanpa PCK dan tanpa GRN.
 *
 * @property TransferStatus $status
 * @property TransferOrigin $origin
 */
class Transfer extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'transfers';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'submitted',
        'origin' => 'manual',
    ];

    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'origin' => TransferOrigin::class,
            'asset_onsite' => 'boolean',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * BR-ACC-05 — TRF terlihat bila gudang asal **atau** tujuannya ada di
     * cakupan pengguna: pengirim dan penerima sama-sama perlu membacanya
     * (A-109). Pengguna bercakupan proyek melihat TRF yang proyek asal/tujuannya
     * miliknya.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('cakupan', function (Builder $q): void {
            $user = Auth::user();

            if (! $user instanceof User) {
                return;
            }

            $gudang = $user->accessibleWarehouseIds();

            if ($gudang !== null) {
                $q->where(fn (Builder $w) => $w->whereIn('transfers.from_warehouse_id', $gudang)
                    ->orWhereIn('transfers.to_warehouse_id', $gudang));
            }

            $proyek = $user->accessibleProjectIds();

            if ($proyek !== null) {
                $q->where(fn (Builder $w) => $w->whereIn('transfers.from_project_id', $proyek)
                    ->orWhereIn('transfers.to_project_id', $proyek));
            }
        });
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id')->withoutGlobalScopes();
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id')->withoutGlobalScopes();
    }

    public function fromProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'from_project_id')->withoutGlobalScopes();
    }

    public function toProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'to_project_id')->withoutGlobalScopes();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectReason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'reject_reason_id');
    }

    public function cancelReason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'cancel_reason_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(TransferLine::class);
    }

    /** REQ asal bila TRF lahir dari backorder (BR-REQ-05). */
    public function sourceRequest(): ?MaterialRequest
    {
        if ($this->source_type !== 'material_request' || $this->source_id === null) {
            return null;
        }

        return MaterialRequest::withoutGlobalScopes()->find($this->source_id);
    }

    /** PCK di gudang asal (source_type = transfer). */
    public function pickTasks(): HasMany
    {
        return $this->hasMany(PickTask::class, 'source_id')
            ->withoutGlobalScopes()
            ->where('source_type', 'transfer');
    }

    public function hasLivePickTask(): bool
    {
        return $this->pickTasks()->where('status', '!=', PickTaskStatus::Cancelled->value)->exists();
    }

    /**
     * SJ antar site TRF aset (A-249): SJ tanpa PCK bersumber TRF ini yang
     * belum dibatalkan.
     */
    public function liveAssetShipment(): ?Shipment
    {
        return Shipment::query()->withoutGlobalScopes()
            ->where('source_type', 'transfer')->where('source_id', $this->id)
            ->where('status', '!=', ShipmentStatus::Cancelled->value)
            ->latest('id')->first();
    }

    public function kind(): TransferKind
    {
        if ($this->asset_onsite) {
            return TransferKind::AssetOnSite;
        }

        return TransferKind::between(
            $this->from_project_id !== null ? (int) $this->from_project_id : null,
            $this->to_project_id !== null ? (int) $this->to_project_id : null,
        );
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            TransferStatus::Completed->value,
            TransferStatus::Rejected->value,
            TransferStatus::Cancelled->value,
        ]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('transfer')
            ->logOnly(['number', 'status', 'from_warehouse_id', 'to_warehouse_id', 'approved_by', 'completed_at'])
            ->logOnlyDirty();
    }
}

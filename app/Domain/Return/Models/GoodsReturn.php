<?php

declare(strict_types=1);

namespace App\Domain\Return\Models;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Receipt\Enums\GoodsReceiptStatus;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Return\Enums\GoodsReturnStatus;
use App\Domain\Shipment\Enums\PickTaskStatus;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Shipment\Models\Shipment;
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
 * RET — retur dari proyek ke gudang (Katalog Status §2.8, BR-RET-01–05).
 *
 * Dokumen niat (A-33): barangnya bergerak lewat SJ balik (opsional) dan GRN
 * jenis retur ke bin Retur, lalu dipilah. Tabelnya `goods_returns` karena
 * `return` kata kunci PHP (Glosarium).
 *
 * @property GoodsReturnStatus $status
 */
class GoodsReturn extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'goods_returns';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'submitted',
        'self_delivered' => true,
    ];

    protected function casts(): array
    {
        return [
            'status' => GoodsReturnStatus::class,
            'self_delivered' => 'boolean',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'received_at' => 'datetime',
            'sorted_at' => 'datetime',
        ];
    }

    /**
     * BR-ACC-05 — pengguna bercakupan gudang melihat RET yang Gudang Site asal
     * atau gudang tujuannya miliknya; pengguna bercakupan proyek (Pemohon,
     * Klien) melihat RET proyeknya (BR-PRJ-06, A-109).
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
                $q->where(fn (Builder $w) => $w->whereIn('goods_returns.from_warehouse_id', $gudang)
                    ->orWhereIn('goods_returns.to_warehouse_id', $gudang));
            }

            $proyek = $user->accessibleProjectIds();

            if ($proyek !== null) {
                $q->whereIn('goods_returns.project_id', $proyek);
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withoutGlobalScopes();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id')->withoutGlobalScopes();
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id')->withoutGlobalScopes();
    }

    public function originShipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'origin_shipment_id')->withoutGlobalScopes();
    }

    public function returnShipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'return_shipment_id')->withoutGlobalScopes();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function sorter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sorted_by');
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
        return $this->hasMany(GoodsReturnLine::class);
    }

    /** Baris yang diajukan (bukan baris hasil pilah tambahan). */
    public function requestedLines(): HasMany
    {
        return $this->hasMany(GoodsReturnLine::class)->whereNull('split_from_line_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class, 'goods_return_id')->withoutGlobalScopes();
    }

    /** GRN retur yang masih berlaku (satu per RET, A-112). */
    public function activeReceipt(): ?GoodsReceipt
    {
        return $this->receipts()->where('status', '!=', GoodsReceiptStatus::Cancelled->value)->latest('id')->first();
    }

    /** PCK SJ balik di Gudang Site (A-111). */
    public function pickTasks(): HasMany
    {
        return $this->hasMany(PickTask::class, 'source_id')
            ->withoutGlobalScopes()
            ->where('source_type', 'goods_return');
    }

    public function livePickTask(): ?PickTask
    {
        return $this->pickTasks()->where('status', '!=', PickTaskStatus::Cancelled->value)->latest('id')->first();
    }

    public function isFromClient(): bool
    {
        return $this->requester?->client_id !== null;
    }

    /**
     * RET dijemput driver dengan SJ tanpa PCK (A-248): tidak diantar sendiri
     * dan tidak memuat stok Gudang Site (yang itu lewat PCK, A-111).
     */
    public function isPickup(): bool
    {
        return ! $this->self_delivered && $this->from_warehouse_id === null;
    }

    /**
     * Katalog §2.8: batal selama belum ada SJ balik berangkat. RET jemput sudah
     * `in_progress` sejak SJ jemput disusun, jadi masih bisa dibatalkan selama
     * SJ itu `prepared` (SJ-nya ikut dibatalkan).
     */
    public function canBeCancelled(): bool
    {
        if ($this->status->isCancellable()) {
            return true;
        }

        return $this->status === GoodsReturnStatus::InProgress
            && $this->isPickup()
            && $this->returnShipment?->status === ShipmentStatus::Prepared;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('return')
            ->logOnly(['number', 'status', 'to_warehouse_id', 'return_shipment_id', 'approved_by', 'sorted_at'])
            ->logOnlyDirty();
    }
}

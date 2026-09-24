<?php

declare(strict_types=1);

namespace App\Domain\Adjustment\Models;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopedToUser;
use App\Domain\Adjustment\Enums\AdjustmentOrigin;
use App\Domain\Adjustment\Enums\StockAdjustmentStatus;
use App\Domain\Count\Models\StockCount;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * ADJ — penyesuaian stok (Katalog Status §2.12, glosarium `stock_adjustment`).
 *
 * Manual: selalu lewat minimal satu lapis approval (A-09, BR-APR-02). Dari
 * opname: tetap `submitted` sampai sesi disetujui, lalu langsung diposting
 * (BR-OPN-06). Stok bergerak hanya saat `posted`, lewat `StockLedger` (P-01).
 *
 * @property StockAdjustmentStatus $status
 * @property AdjustmentOrigin $origin
 */
class StockAdjustment extends Model
{
    use HasFactory;
    use LogsActivity;
    use ScopedToUser;

    protected $table = 'stock_adjustments';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'submitted',
        'origin' => 'manual',
    ];

    protected function casts(): array
    {
        return [
            'status' => StockAdjustmentStatus::class,
            'origin' => AdjustmentOrigin::class,
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public static function scopeWarehouseColumn(): ?string
    {
        return 'warehouse_id';
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class)->withoutGlobalScopes();
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id')->withoutGlobalScopes();
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_id')->withoutGlobalScopes();
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'reason_code_id');
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
        return $this->hasMany(StockAdjustmentLine::class);
    }

    public function isManual(): bool
    {
        return $this->origin === AdjustmentOrigin::Manual;
    }

    /** ADJ pembalik yang masih berjalan atau sudah diposting (BR-LED-05: sekali saja). */
    public function activeReversal(): ?self
    {
        return self::query()->withoutGlobalScopes()
            ->where('reversal_of_id', $this->id)
            ->whereNotIn('status', [StockAdjustmentStatus::Rejected->value, StockAdjustmentStatus::Cancelled->value])
            ->first();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('adjustment')
            ->logOnly(['number', 'status', 'approved_by', 'posted_at'])
            ->logOnlyDirty();
    }
}

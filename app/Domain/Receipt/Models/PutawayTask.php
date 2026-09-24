<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Models;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopedToUser;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Receipt\Enums\PutawayTaskStatus;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * PUT — tugas menaruh barang dari bin Penerimaan ke bin penyimpanan
 * (Katalog Status §2.6, BR-GRN-03).
 *
 * @property PutawayTaskStatus $status
 */
class PutawayTask extends Model
{
    use HasFactory;
    use LogsActivity;
    use ScopedToUser;

    protected $table = 'putaway_tasks';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => PutawayTaskStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public static function scopeWarehouseColumn(): ?string
    {
        return 'warehouse_id';
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id')->withoutGlobalScopes();
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
        return $this->hasMany(PutawayTaskLine::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('receipt')
            ->logOnly(['number', 'status', 'assigned_to', 'completed_at'])
            ->logOnlyDirty();
    }
}

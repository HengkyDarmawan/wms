<?php

declare(strict_types=1);

namespace App\Domain\Count\Models;

use App\Domain\Access\Models\User;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Count\Enums\CountType;
use App\Domain\Count\Enums\StockCountStatus;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * OPN — sesi stock opname (Katalog Status §2.13, Blueprint §9, D-23).
 *
 * Satu sesi boleh mencakup beberapa gudang (`stock_count_warehouses`), jadi
 * cakupan akses (BR-ACC-05) tidak memakai satu kolom gudang: sesi terlihat
 * bila minimal satu gudangnya terlihat oleh pengguna. `whereHas` ikut global
 * scope `Warehouse`, sehingga aturan cakupannya sama persis.
 *
 * @property CountType $count_type
 * @property StockCountStatus $status
 * @property array<string, mixed> $scope
 * @property array<int, int>|null $team_user_ids
 */
class StockCount extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'stock_counts';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'planned',
        'freeze_bins' => true,
        'is_audit' => false,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('cakupan_gudang', function (Builder $query): void {
            if (auth()->user() instanceof User && auth()->user()->accessibleWarehouseIds() !== null) {
                $query->whereHas('warehouses');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'count_type' => CountType::class,
            'status' => StockCountStatus::class,
            'freeze_bins' => 'boolean',
            'is_audit' => 'boolean',
            'scope' => 'array',
            'team_user_ids' => 'array',
            'planned_start' => 'date',
            'started_at' => 'datetime',
            'reconciled_at' => 'datetime',
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
            'lock_date_set' => 'date',
        ];
    }

    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'stock_count_warehouses')->withPivot('stock_adjustment_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CountAssignment::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CountLine::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class)->withoutGlobalScopes();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

    /** @return array<int, int> */
    public function warehouseIds(): array
    {
        return array_values(array_map('intval', (array) ($this->scope['warehouse_ids'] ?? [])));
    }

    /** @return array<int, int> */
    public function teamIds(): array
    {
        return array_values(array_unique(array_map('intval', (array) ($this->team_user_ids ?? []))));
    }

    /** Penghitung yang sudah menyelesaikan minimal satu penugasan (BR-OPN-09). */
    public function counterIds(): array
    {
        return CountAssignment::query()
            ->where('stock_count_id', $this->id)
            ->whereNotNull('counter_user_id')
            ->where('status', 'done')
            ->pluck('counter_user_id')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();
    }

    /** BR-OPN-10: pemeriksaan mendadak tidak membekukan bin dan tidak memposting ADJ. */
    public function postsAdjustments(): bool
    {
        return ! $this->count_type->isSpotCheck();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('count')
            ->logOnly(['number', 'status', 'count_type', 'freeze_bins', 'approved_by', 'closed_at', 'lock_date_set'])
            ->logOnlyDirty();
    }
}

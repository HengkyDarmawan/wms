<?php

declare(strict_types=1);

namespace App\Domain\Asset\Models;

use App\Domain\Access\Models\User;
use App\Domain\Asset\Enums\MaintenanceSource;
use App\Domain\Asset\Enums\MaintenanceStatus;
use App\Domain\Master\Models\Serial;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jadwal servis aset (BR-AST-07, A-423, 33-pendukung-f2): `planned` →
 * `in_progress` (aset dipindah ke bin Karantina) → `done` (kembali ke bin
 * penyimpanan). Satu jadwal terbuka per aset.
 */
class MaintenanceSchedule extends Model
{
    protected $table = 'maintenance_schedules';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'date',
            'performed_at' => 'date',
            'interval_meter' => 'decimal:1',
            'due_meter' => 'decimal:1',
            'meter_done' => 'decimal:1',
            'status' => MaintenanceStatus::class,
            'source' => MaintenanceSource::class,
        ];
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(Serial::class);
    }

    public function originBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'origin_bin_id')->withoutGlobalScopes();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Jadwal yang belum selesai (`planned` / `in_progress`). */
    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', [MaintenanceStatus::Planned->value, MaintenanceStatus::InProgress->value]);
    }

    /** Jatuh tempo: direncanakan untuk hari ini atau sebelumnya. */
    public function scopeDue(Builder $q): Builder
    {
        return $q->where('status', MaintenanceStatus::Planned->value)->whereDate('scheduled_at', '<=', now()->toDateString());
    }
}

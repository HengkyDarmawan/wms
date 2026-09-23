<?php

declare(strict_types=1);

namespace App\Domain\Master\Models;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Proyek — pusat pelacakan material (Blueprint §6.9). Gudang Site menempel pada
 * proyek lewat `warehouses.project_id`; satu proyek boleh punya beberapa (A-40).
 * Proyek Internal (`is_internal`) dipakai konversi & peminjaman non-klien (A-06).
 *
 * @property ProjectStatus $status
 */
class Project extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'projects';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'is_internal' => 'boolean',
            'start_date' => 'date',
            'target_end_date' => 'date',
            'closed_at' => 'datetime',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function materialPlans(): HasMany
    {
        return $this->hasMany(ProjectMaterialPlan::class);
    }

    /** Aset yang sedang berada di proyek ini (BR-AST-01). */
    public function serialsOnLoan(): HasMany
    {
        return $this->hasMany(Serial::class, 'current_project_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProjectStatus::Active->value);
    }

    /** BR-PRJ-01: hanya proyek aktif yang menerima dokumen baru. */
    public function acceptsDocuments(): bool
    {
        return $this->status === ProjectStatus::Active;
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            ProjectStatus::Active => 'success',
            ProjectStatus::Closed => 'secondary',
            ProjectStatus::Cancelled => 'danger',
            ProjectStatus::Archived => 'dark',
        };
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('master')
            ->logOnly(['code', 'name', 'client_id', 'is_internal', 'status', 'pic_user_id', 'start_date', 'target_end_date', 'closed_at'])
            ->logOnlyDirty();
    }
}

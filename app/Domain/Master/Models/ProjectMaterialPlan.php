<?php

declare(strict_types=1);

namespace App\Domain\Master\Models;

use App\Domain\Access\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rencana kebutuhan material per proyek (BoQ kuantitas, BR-PRJ-09, A-62,
 * A-428): satu unggahan = satu versi seluruh proyek; hanya versi terbaru
 * `is_current`. Versi lama tidak dihapus (P-03).
 */
class ProjectMaterialPlan extends Model
{
    use HasFactory;

    protected $table = 'project_material_plans';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'planned_qty_base' => 'decimal:4',
            'is_current' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }
}

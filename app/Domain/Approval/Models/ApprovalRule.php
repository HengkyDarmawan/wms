<?php

declare(strict_types=1);

namespace App\Domain\Approval\Models;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Aturan approval per jenis dokumen (Blueprint §8.1, D-17).
 *
 * Aturan hidup; dokumen yang sedang menunggu memakai salinannya di
 * `approval_snapshots` (BR-APR-01). Tidak pernah dihapus, hanya dinonaktifkan
 * (P-03).
 *
 * @property ApprovalDocumentType $document_type
 * @property array<string, mixed>|null $conditions
 */
class ApprovalRule extends Model
{
    use LogsActivity;

    protected $table = 'approval_rules';

    protected $guarded = [];

    protected $attributes = [
        'is_active' => true,
        'priority' => 100,
    ];

    protected function casts(): array
    {
        return [
            'document_type' => ApprovalDocumentType::class,
            'conditions' => 'array',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class)->orderBy('step_no');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ApprovalSnapshot::class, 'rule_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Urutan pemeriksaan: prioritas kecil dulu, lalu yang lebih tua (BR-APR-01). */
    public function scopeEvaluationOrder(Builder $query): Builder
    {
        return $query->orderBy('priority')->orderBy('id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('approval')
            ->logOnly(['document_type', 'name', 'priority', 'conditions', 'is_active'])
            ->logOnlyDirty();
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Approval\Models;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Delegasi hak approve berperiode, tidak berantai (BR-APR-05).
 * Diakhiri dengan `is_active = false`, tidak dihapus (P-03).
 *
 * @property array<int, string>|null $document_types
 */
class ApprovalDelegation extends Model
{
    use LogsActivity;

    protected $table = 'approval_delegations';

    protected $guarded = [];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'from_user_id' => 'integer',
            'to_user_id' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'document_types' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /** Delegasi aktif yang periodenya mencakup waktu tertentu. */
    public function scopeEffectiveAt(Builder $query, CarbonInterface $at): Builder
    {
        return $query->where('is_active', true)
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>', $at);
    }

    /** Delegasi aktif yang periodenya beririsan dengan [awal, akhir). */
    public function scopeOverlapping(Builder $query, CarbonInterface $awal, CarbonInterface $akhir): Builder
    {
        return $query->where('is_active', true)
            ->where('starts_at', '<', $akhir)
            ->where('ends_at', '>', $awal);
    }

    public function covers(ApprovalDocumentType|string $type): bool
    {
        $nilai = $type instanceof ApprovalDocumentType ? $type->value : $type;

        return $this->document_types === null || $this->document_types === [] || in_array($nilai, $this->document_types, true);
    }

    /** Irisan jenis dokumen dua delegasi (null = semua). */
    public function sharesTypesWith(?array $types): bool
    {
        if ($this->document_types === null || $this->document_types === [] || $types === null || $types === []) {
            return true;
        }

        return array_intersect($this->document_types, $types) !== [];
    }

    public function isEffective(): bool
    {
        return $this->is_active && $this->starts_at->lte(now()) && $this->ends_at->gt(now());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('approval')
            ->logOnly(['from_user_id', 'to_user_id', 'starts_at', 'ends_at', 'document_types', 'is_active', 'notes'])
            ->logOnlyDirty();
    }
}

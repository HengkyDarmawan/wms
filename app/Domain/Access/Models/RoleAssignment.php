<?php

declare(strict_types=1);

namespace App\Domain\Access\Models;

use App\Domain\Access\Enums\ScopeType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penugasan role x cakupan — BR-GEN-09, A-46, A-04.
 * Cakupan efektif user = gabungan semua penugasan yang berlaku (BR-ACC-05).
 *
 * @property int $user_id
 * @property int $role_id
 * @property ScopeType $scope_type
 * @property int|null $scope_id
 */
class RoleAssignment extends Model
{
    use HasFactory;

    protected $table = 'role_assignments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scope_type' => ScopeType::class,
            'scope_id' => 'integer',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /** Penugasan yang berlaku hari ini (BR-ACC-01, BR-ACC-05). */
    public function scopeValid(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->where(function (Builder $q) use ($today) {
            $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today);
        })->where(function (Builder $q) use ($today) {
            $q->whereNull('valid_until')->orWhere('valid_until', '>=', $today);
        });
    }

    public function isValid(): bool
    {
        $today = now()->startOfDay();

        if ($this->valid_from !== null && $this->valid_from->greaterThan($today)) {
            return false;
        }

        return ! ($this->valid_until !== null && $this->valid_until->lessThan($today));
    }

    public function describeScope(): string
    {
        return $this->scope_type === ScopeType::All
            ? ScopeType::All->label()
            : $this->scope_type->label().' #'.$this->scope_id;
    }
}

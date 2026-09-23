<?php

declare(strict_types=1);

namespace App\Domain\Master\Models;

use App\Domain\Access\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Klien = pemilik proyek, pelanggan dari company (Blueprint §6.3a).
 * Satu klien bisa punya banyak proyek dan banyak user portal.
 */
class Client extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'clients';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function portalUsers(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** BR-MST-05: klien dengan proyek aktif tidak bisa dinonaktifkan. */
    public function activeProjectCount(): int
    {
        return $this->projects()->where('status', 'active')->count();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('master')
            ->logOnly(['code', 'name', 'tax_id', 'contact_name', 'phone', 'email', 'is_active'])
            ->logOnlyDirty();
    }
}

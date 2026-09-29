<?php

declare(strict_types=1);

namespace App\Domain\Master\Models;

use App\Domain\Access\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * PIC Klien (A-326) — orang dari **pihak klien**, bukan staf company.
 * Jangan tertukar dengan PIC Proyek (`projects.pic_user_id`), yang orang kita
 * sendiri. Tidak pernah dihapus, hanya dinonaktifkan (P-03).
 *
 * @property int $client_id
 * @property string $name
 * @property bool $is_active
 */
class ClientContact extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'client_contacts';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Proyek yang diurus PIC — info kontak, bukan hak akses (Tim site yang mengatur akses). */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'client_contact_project')->withTimestamps();
    }

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function hasPortalAccount(): bool
    {
        return $this->user_id !== null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('master')
            ->logOnly(['client_id', 'name', 'position', 'phone', 'email', 'user_id', 'is_active'])
            ->logOnlyDirty();
    }
}

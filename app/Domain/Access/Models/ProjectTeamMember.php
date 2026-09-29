<?php

declare(strict_types=1);

namespace App\Domain\Access\Models;

use App\Domain\Access\Enums\SiteTeamStatus;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ReasonCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Anggota Tim site (A-337): orang yang **ditempatkan di site proyek** untuk
 * periode tertentu. Inilah satu-satunya tempat akses berbatas waktu; akun dan
 * peran biasa berlaku sampai dinonaktifkan.
 *
 * Baris ini memiliki `role_assignments` bertanggal yang dibuatnya
 * (`role_assignments.project_team_member_id`); yang tidak dimilikinya tidak
 * pernah disentuh.
 *
 * @property int $project_id
 * @property int $user_id
 * @property int $role_id
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 */
class ProjectTeamMember extends Model
{
    use HasFactory;

    protected $table = 'project_team_members';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'ended_at' => 'datetime',
            'reminded_at' => 'datetime',
            'grants_access' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function endReason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'end_reason_code_id');
    }

    /** Penugasan role bertanggal yang dibuat keanggotaan ini. */
    public function assignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    public function status(): SiteTeamStatus
    {
        if ($this->ended_at !== null) {
            return SiteTeamStatus::Ended;
        }

        $sisa = $this->sisaHari();

        if ($sisa < 0) {
            return SiteTeamStatus::Expired;
        }

        if ($this->starts_on->startOfDay()->greaterThan(now()->startOfDay())) {
            return SiteTeamStatus::Upcoming;
        }

        return $sisa <= SiteTeamStatus::AMBANG_HARI ? SiteTeamStatus::EndingSoon : SiteTeamStatus::Active;
    }

    /** Sisa hari sampai tanggal selesai; negatif bila sudah lewat. */
    public function sisaHari(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->ends_on->startOfDay(), false);
    }

    /** Keanggotaan yang belum diakhiri dan belum lewat tanggal selesainya. */
    public function scopeRunning(Builder $query): Builder
    {
        return $query->whereNull('ended_at')->whereDate('ends_on', '>=', now()->toDateString());
    }

    /** Berakhir dalam `$hari` hari ke depan dan belum pernah diingatkan (A-340). */
    public function scopeEndingSoon(Builder $query, int $hari): Builder
    {
        return $query->running()
            ->whereNull('reminded_at')
            ->whereDate('ends_on', '<=', now()->addDays($hari)->toDateString());
    }
}

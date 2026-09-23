<?php

declare(strict_types=1);

namespace App\Domain\Request\Models;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopedToUser;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Enums\RequesterType;
use App\Domain\Request\Enums\RequestOrigin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * REQ — Permintaan Material (14-request).
 *
 * Dokumen niat: tidak pernah menyentuh kartu stok. Jejaknya di gudang hanya
 * reservasi lunak yang lahir saat disetujui (BR-REQ-05) dan dilepas saat
 * dibatalkan atau ditutup dengan sisa.
 *
 * @property MaterialRequestStatus $status
 * @property RequesterType $requester_type
 * @property RequestOrigin $origin
 */
class MaterialRequest extends Model
{
    use HasFactory;
    use LogsActivity;
    use ScopedToUser;

    protected $table = 'material_requests';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'draft',
        'requester_type' => 'internal',
        'origin' => 'regular',
    ];

    protected function casts(): array
    {
        return [
            'status' => MaterialRequestStatus::class,
            'requester_type' => RequesterType::class,
            'origin' => RequestOrigin::class,
            'required_date' => 'date',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /** BR-ACC-05: REQ mengikuti cakupan proyek penggunanya. */
    public static function scopeProjectColumn(): ?string
    {
        return 'project_id';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function closedReason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'closed_reason_id');
    }

    public function cancelReason(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'cancel_reason_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_request_id');
    }

    /** REQ Tambahan milik REQ ini (A-54). */
    public function supplements(): HasMany
    {
        return $this->hasMany(self::class, 'parent_request_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MaterialRequestLine::class);
    }

    /** Baris yang masih hidup; yang dibatalkan tetap tersimpan tetapi tidak dihitung. */
    public function openLines(): HasMany
    {
        return $this->lines()->where('material_request_lines.status', '!=', 'cancelled');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            MaterialRequestStatus::Completed->value,
            MaterialRequestStatus::ClosedShort->value,
            MaterialRequestStatus::Rejected->value,
            MaterialRequestStatus::Cancelled->value,
        ]);
    }

    /**
     * BR-REQ-14: REQ klien yang terlalu lama ditinjau.
     *
     * Dihitung dari `created_at`, bukan dari saat masuk `under_review`: yang
     * dirasakan klien adalah lamanya menunggu sejak mengajukan.
     */
    public function scopeReviewOverdue(Builder $query, int $days): Builder
    {
        return $query
            ->where('status', MaterialRequestStatus::UnderReview->value)
            ->where('created_at', '<=', now()->subDays($days));
    }

    public function isFromClient(): bool
    {
        return $this->requester_type === RequesterType::Client;
    }

    public function isSupplement(): bool
    {
        return $this->origin === RequestOrigin::Supplement;
    }

    public function reviewAgeInDays(): int
    {
        return $this->created_at === null
            ? 0
            : (int) $this->created_at->startOfDay()->diffInDays(now()->startOfDay());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('request')
            ->logOnly(['number', 'status', 'required_date', 'reviewed_by', 'approved_by', 'origin'])
            ->logOnlyDirty();
    }
}

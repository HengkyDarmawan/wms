<?php

declare(strict_types=1);

namespace App\Domain\Access\Models;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Enums\UserStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * User di database company (Blueprint §4.2).
 *
 * Hak akses TIDAK melekat pada user melainkan pada penugasan role x cakupan
 * (BR-GEN-09, A-46). User klien ditandai `client_id` dan hanya boleh role Klien
 * (BR-ACC-03); ia masuk lewat /portal (BR-PRJ-07).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property bool $is_active
 */
class User extends Authenticatable
{
    use HasFactory;
    use LogsActivity;
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /** Cache per instance agar satu request tidak menghitung ulang. */
    private ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'locked_until' => 'datetime',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'failed_login_count' => 'integer',
        ];
    }

    // ------------------------------------------------------------------ relasi

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /** Atasan langsung — dipakai approval "atasan langsung" (D-16, BR-APR-03). */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(UserInvitation::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function passwordHistories(): HasMany
    {
        return $this->hasMany(PasswordHistory::class);
    }

    // ------------------------------------------------------------------ status

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function isClient(): bool
    {
        return $this->client_id !== null;
    }

    public function hasPendingInvitation(): bool
    {
        return $this->invitations()->whereNull('accepted_at')->exists();
    }

    public function status(): UserStatus
    {
        if (! $this->is_active) {
            return UserStatus::Inactive;
        }

        if ($this->isLocked()) {
            return UserStatus::Locked;
        }

        if ($this->password === null || $this->hasPendingInvitation()) {
            return UserStatus::Invited;
        }

        return UserStatus::Active;
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    // ------------------------------------------------- role, permission, cakupan

    /** Penugasan role yang berlaku hari ini. */
    public function validAssignments(): Collection
    {
        return $this->roleAssignments()
            ->valid()
            ->with('role')
            ->get()
            ->filter(fn (RoleAssignment $a) => $a->role !== null && $a->role->is_active)
            ->values();
    }

    /** @return array<int, string> kode role yang berlaku */
    public function roleCodes(): array
    {
        return $this->validAssignments()
            ->map(fn (RoleAssignment $a) => $a->role->code)
            ->unique()
            ->values()
            ->all();
    }

    public function hasRoleCode(string $code): bool
    {
        return in_array($code, $this->roleCodes(), true);
    }

    /** @return array<int, string> kunci permission gabungan semua role yang berlaku */
    public function permissionKeys(): array
    {
        if ($this->permissionCache !== null) {
            return $this->permissionCache;
        }

        $roleIds = $this->validAssignments()
            ->map(fn (RoleAssignment $a) => $a->role_id)
            ->unique()
            ->all();

        if ($roleIds === []) {
            return $this->permissionCache = [];
        }

        $keys = Permission::query()
            ->whereHas('roles', fn (Builder $q) => $q->whereIn('roles.id', $roleIds))
            ->pluck('name')
            ->all();

        return $this->permissionCache = array_values(array_unique($keys));
    }

    public function hasPermission(string $key): bool
    {
        return in_array($key, $this->permissionKeys(), true);
    }

    public function forgetPermissionCache(): void
    {
        $this->permissionCache = null;
    }

    /**
     * BR-ACC-05 — id gudang yang boleh diakses; null berarti semua gudang.
     *
     * @return array<int, int>|null
     */
    public function accessibleWarehouseIds(): ?array
    {
        return $this->accessibleScopeIds(ScopeType::Warehouse);
    }

    /** @return array<int, int>|null null berarti semua proyek */
    public function accessibleProjectIds(): ?array
    {
        return $this->accessibleScopeIds(ScopeType::Project);
    }

    /**
     * BR-ACC-05 — id yang boleh diakses pada satu dimensi cakupan.
     *
     * `null` berarti **tidak dibatasi** pada dimensi ini, dan itu terjadi pada
     * dua keadaan: user punya penugasan bercakupan `all`, atau user tidak
     * punya penugasan pada dimensi ini sama sekali. Yang kedua penting:
     * Kepala Gudang yang dibatasi gudang tidak dengan sendirinya dibatasi
     * proyek, jadi daftar proyeknya tidak boleh menjadi kosong.
     *
     * Karena itu metode ini tidak pernah mengembalikan array kosong, dan
     * seluruh pemakainya boleh membaca `null` sebagai 'semua' tanpa cabang lain.
     *
     * @return array<int, int>|null
     */
    private function accessibleScopeIds(ScopeType $type): ?array
    {
        $assignments = $this->validAssignments();

        if ($assignments->contains(fn (RoleAssignment $a) => $a->scope_type === ScopeType::All)) {
            return null;
        }

        $ids = $assignments
            ->filter(fn (RoleAssignment $a) => $a->scope_type === $type)
            ->map(fn (RoleAssignment $a) => (int) $a->scope_id)
            ->unique()
            ->values()
            ->all();

        return $ids === [] ? null : $ids;
    }

    public function canAccessWarehouse(int $warehouseId): bool
    {
        $ids = $this->accessibleWarehouseIds();

        return $ids === null || in_array($warehouseId, $ids, true);
    }

    public function canAccessProject(int $projectId): bool
    {
        $ids = $this->accessibleProjectIds();

        return $ids === null || in_array($projectId, $ids, true);
    }

    /** BR-ACC-01: tanpa penugasan role yang berlaku, user tidak boleh masuk. */
    public function canSignIn(): bool
    {
        return $this->is_active && ! $this->isLocked() && $this->validAssignments()->isNotEmpty();
    }

    // ------------------------------------------------------------------ audit

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('access')
            ->logOnly([
                'name', 'email', 'phone', 'client_id', 'org_unit_id',
                'position_id', 'manager_id', 'is_active', 'locked_until',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // ------------------------------------------------------------------ query

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInternal(Builder $query): Builder
    {
        return $query->whereNull('client_id');
    }

    public function scopeClients(Builder $query): Builder
    {
        return $query->whereNotNull('client_id');
    }
}

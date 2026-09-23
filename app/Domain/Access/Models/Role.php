<?php

declare(strict_types=1);

namespace App\Domain\Access\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Role bawaan atau buatan company (Blueprint §4.2).
 * `code` = kode stabil (warehouse_head), `name` = label Bahasa Indonesia.
 * Penugasan role ke user TIDAK memakai tabel spatie, melainkan `role_assignments`
 * yang membawa cakupan (BR-GEN-09, A-46).
 *
 * @property string $code
 * @property string $name
 * @property bool $is_builtin
 * @property bool $is_client_role
 * @property bool $is_active
 */
class Role extends SpatieRole
{
    protected $table = 'roles';

    protected function casts(): array
    {
        return [
            'is_builtin' => 'boolean',
            'is_client_role' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    public static function findByCode(string $code): ?self
    {
        return static::query()->where('code', $code)->first();
    }

    /** Role internal boleh bercakupan `all` (BR-ACC-04). */
    public function allowsAllScope(): bool
    {
        return ! $this->is_client_role;
    }
}

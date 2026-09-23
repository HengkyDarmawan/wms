<?php

declare(strict_types=1);

namespace App\Domain\Access\Support;

use App\Domain\Access\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * BR-ACC-05 — membatasi query ke cakupan penugasan role user yang masuk.
 *
 * Model yang memakainya menyatakan kolom cakupannya:
 *
 *   class Warehouse extends Model {
 *       use ScopedToUser;
 *       protected static function scopeWarehouseColumn(): ?string { return 'id'; }
 *   }
 *
 *   class MaterialRequest extends Model {
 *       use ScopedToUser;
 *       protected static function scopeProjectColumn(): ?string { return 'project_id'; }
 *   }
 *
 * Cakupan `all` (Admin Company, Manajemen) melewati pembatasan. Dipakai modul
 * Warehouse, Master, dan seterusnya; modul Access sendiri belum punya tabel
 * bergudang/berproyek.
 */
trait ScopedToUser
{
    public static function bootScopedToUser(): void
    {
        static::addGlobalScope(new class implements Scope
        {
            public function apply(Builder $builder, Model $model): void
            {
                /** @var User|null $user */
                $user = Auth::user();

                if (! $user instanceof User) {
                    return;
                }

                $warehouseColumn = $model::scopeWarehouseColumn();
                $projectColumn = $model::scopeProjectColumn();

                if ($warehouseColumn !== null) {
                    $ids = $user->accessibleWarehouseIds();

                    if ($ids !== null) {
                        $builder->whereIn($model->qualifyColumn($warehouseColumn), $ids);
                    }
                }

                if ($projectColumn !== null) {
                    $ids = $user->accessibleProjectIds();

                    if ($ids !== null) {
                        $builder->whereIn($model->qualifyColumn($projectColumn), $ids);
                    }
                }
            }
        });
    }

    /** Kolom yang menunjuk gudang; null bila model tidak bergudang. */
    public static function scopeWarehouseColumn(): ?string
    {
        return null;
    }

    /** Kolom yang menunjuk proyek; null bila model tidak berproyek. */
    public static function scopeProjectColumn(): ?string
    {
        return null;
    }
}

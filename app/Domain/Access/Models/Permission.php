<?php

declare(strict_types=1);

namespace App\Domain\Access\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Permission `<modul>.<aksi>` (Katalog Status §konvensi). Kolom `name` menyimpan
 * kunci permission (ERD menyebutnya `key`) karena spatie/laravel-permission
 * mewajibkan nama kolom tersebut — lihat 10-access §3.
 *
 * @property string $name
 * @property string $module
 * @property string|null $label
 */
class Permission extends SpatiePermission
{
    protected $table = 'permissions';

    public function getKeyString(): string
    {
        return $this->name;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permission: `role.create` / `role.update`.
 *
 * Role bawaan boleh diubah permission-nya, tetapi kode, sifat klien, dan status
 * bawaannya dikunci (10-access §6.4, P-03).
 *
 * @param  array<int, string>  $permissionKeys
 */
class SaveRole
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>  $permissionKeys
     */
    public function handle(?Role $role, array $attributes, array $permissionKeys, ?User $actor = null): Role
    {
        $baru = $role === null;

        $nama = trim((string) ($attributes['name'] ?? ''));

        if ($nama === '') {
            throw AccessRuleException::rule('BR-GEN-11', 'Nama role wajib diisi.');
        }

        // `roles` unik pada (name, guard_name). Tanpa penjagaan ini, nama kembar
        // memunculkan galat integritas 500, bukan pesan yang bisa dibaca user.
        $namaKembar = Role::query()->where('name', $nama)->where('guard_name', 'web')
            ->when(! $baru, fn ($q) => $q->whereKeyNot($role->getKey()))
            ->exists();

        if ($namaKembar) {
            throw new AccessRuleException('Nama role "'.$nama.'" sudah dipakai.');
        }

        $role = DB::transaction(function () use ($role, $baru, $attributes, $nama, $permissionKeys): Role {
            if ($baru) {
                $kode = Str::of((string) ($attributes['code'] ?? $nama))
                    ->lower()->replace(' ', '_')->replaceMatches('/[^a-z0-9_]/', '')->value();

                if ($kode === '') {
                    throw AccessRuleException::rule('BR-GEN-11', 'Kode role wajib diisi.');
                }

                if (Role::findByCode($kode) !== null) {
                    throw new AccessRuleException('Kode role "'.$kode.'" sudah dipakai.');
                }

                $role = Role::create([
                    'code' => $kode,
                    'name' => $nama,
                    'guard_name' => 'web',
                    'is_builtin' => false,
                    'is_client_role' => (bool) ($attributes['is_client_role'] ?? false),
                    'is_active' => true,
                ]);
            } else {
                $data = ['name' => $nama];

                // Sifat role bawaan dikunci agar aturan BR-ACC-03 tetap konsisten.
                if (! $role->is_builtin) {
                    $data['is_client_role'] = (bool) ($attributes['is_client_role'] ?? $role->is_client_role);
                }

                $role->fill($data)->save();
            }

            $ids = Permission::query()->whereIn('name', $permissionKeys)->pluck('id')->all();
            $role->permissions()->sync($ids);

            return $role;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        activity('access')
            ->performedOn($role)
            ->causedBy($actor)
            ->withProperties(['permissions' => count($permissionKeys)])
            ->log($baru ? 'Role dibuat' : 'Role diubah');

        return $role->refresh();
    }
}

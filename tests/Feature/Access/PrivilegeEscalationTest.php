<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Livewire\RoleForm;
use App\Domain\Access\Livewire\UserDetail;
use App\Domain\Access\Livewire\UserForm;
use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\User;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-ACC-29 — properti id komponen tidak boleh bisa ditimpa dari browser, dan
 * setiap metode yang menulis harus mengotorisasi ulang.
 *
 * Livewire mengirim seluruh properti publik ke klien lalu menerimanya kembali.
 * Otorisasi yang hanya ada di `mount()` karena itu tidak melindungi apa pun:
 * satu permintaan Livewire bisa memanggil metode tulis tanpa pernah melewati
 * mount, dengan id yang sudah diganti.
 */
class PrivilegeEscalationTest extends TenantTestCase
{
    /**
     * Role buatan company dengan permission tertentu saja.
     *
     * @param  array<int, string>  $permissions
     */
    private function berikanRole(User $user, string $code, array $permissions): void
    {
        $role = Role::create([
            'code' => $code,
            'name' => ucfirst(str_replace('_', ' ', $code)),
            'guard_name' => 'web',
            'is_builtin' => false,
            'is_client_role' => false,
            'is_active' => true,
        ]);

        $role->permissions()->sync(
            Permission::query()
                ->whereIn('name', [...$permissions, 'auth.login', 'auth.logout', 'profile.update'])
                ->pluck('id')->all(),
        );

        $this->assignRole($user, $code);
        $user->forgetPermissionCache();
    }

    #[Test]
    public function tc_acc_29_id_pengguna_pada_form_tidak_bisa_ditimpa_dari_klien(): void
    {
        $admin = $this->makeUser('company_admin');
        $korban = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($admin)
            ->test(UserForm::class)
            ->set('userId', $korban->id);
    }

    #[Test]
    public function tc_acc_29b_mengubah_penugasan_role_menuntut_izin_role_assign(): void
    {
        $pengelola = $this->makeUser('', ScopeType::All);
        $this->berikanRole($pengelola, 'pengelola_user', ['user.view', 'user.update']);

        $this->assertTrue($pengelola->hasPermission('user.update'));
        $this->assertFalse($pengelola->hasPermission('role.assign'));

        $adminRole = Role::findByCode('company_admin');

        // Mencoba menambahkan role Admin Company untuk dirinya sendiri.
        Livewire::actingAs($pengelola)
            ->test(UserForm::class, ['userId' => $pengelola->id])
            ->set('assignments', [
                [
                    'role_id' => $adminRole->id,
                    'scope_type' => ScopeType::All->value,
                    'scope_id' => null,
                    'valid_from' => null,
                    'valid_until' => null,
                ],
            ])
            ->call('save')
            ->assertForbidden();

        $this->assertSame(
            0,
            RoleAssignment::query()
                ->where('user_id', $pengelola->id)
                ->where('role_id', $adminRole->id)
                ->count(),
            'Penugasan Admin Company tidak boleh tersimpan.',
        );
    }

    #[Test]
    public function tc_acc_29c_menyimpan_tanpa_mengubah_penugasan_tidak_menuntut_role_assign(): void
    {
        $pengelola = $this->makeUser('', ScopeType::All);
        $this->berikanRole($pengelola, 'pengelola_user', ['user.view', 'user.update']);

        $korban = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1, ['name' => 'Nama Lama']);

        Livewire::actingAs($pengelola)
            ->test(UserForm::class, ['userId' => $korban->id])
            ->set('name', 'Nama Baru')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Nama Baru', $korban->refresh()->name);
    }

    #[Test]
    public function tc_acc_29d_id_role_pada_form_tidak_bisa_ditimpa_dari_klien(): void
    {
        $admin = $this->makeUser('company_admin');
        $manajemen = Role::findByCode('management');

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($admin)
            ->test(RoleForm::class)
            ->set('roleId', $manajemen->id);
    }

    #[Test]
    public function tc_acc_29e_membuka_form_ubah_role_tanpa_izin_ubah_ditolak(): void
    {
        $pembuat = $this->makeUser('', ScopeType::All);
        $this->berikanRole($pembuat, 'pembuat_role', ['role.view', 'role.create']);

        $manajemen = Role::findByCode('management');
        $jumlahSemula = $manajemen->permissions()->count();

        Livewire::actingAs($pembuat)
            ->test(RoleForm::class, ['roleId' => $manajemen->id])
            ->assertForbidden();

        $this->assertSame($jumlahSemula, $manajemen->refresh()->permissions()->count());
    }

    #[Test]
    public function tc_acc_29f_id_pada_detail_pengguna_tidak_bisa_ditimpa_dari_klien(): void
    {
        // UserPolicy::view meluluskan user melihat dirinya sendiri, jadi tanpa
        // kunci properti siapa pun bisa membaca detail pengguna lain.
        $biasa = $this->makeUser('driver');
        $korban = $this->makeUser('company_admin', ScopeType::All, null, ['name' => 'Rahasia Admin']);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($biasa)
            ->test(UserDetail::class, ['userId' => $biasa->id])
            ->set('userId', $korban->id);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Livewire\RoleForm;
use App\Domain\Access\Livewire\RoleList;
use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\Role;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * Layar pengelolaan role — docs/wms/10-access.md §6.4.
 */
class RoleManagementTest extends TenantTestCase
{
    #[Test]
    public function tc_acc_ui_20_hanya_yang_berhak_membuka_daftar_role(): void
    {
        $admin = $this->makeUser('company_admin');
        $driver = $this->makeUser('driver');

        $this->actingAs($admin)->get($this->tenantUrl('/roles'))->assertOk();
        $this->actingAs($driver)->get($this->tenantUrl('/roles'))->assertForbidden();
    }

    #[Test]
    public function tc_acc_ui_21_daftar_role_menampilkan_jumlah_permission_dan_penugasan(): void
    {
        $admin = $this->makeUser('company_admin');
        $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1);

        Livewire::actingAs($admin)->test(RoleList::class)
            ->assertSee('Kepala Gudang')
            ->assertSee('warehouse_staff')
            ->assertSee('Bawaan');

        Livewire::actingAs($admin)->test(RoleList::class)
            ->set('search', 'driver')
            ->assertSee('Driver')
            ->assertDontSee('Kepala Gudang');
    }

    #[Test]
    public function tc_acc_ui_22_buat_role_baru_dengan_permission_terpilih(): void
    {
        $admin = $this->makeUser('company_admin');

        Livewire::actingAs($admin)->test(RoleForm::class)
            ->set('code', 'kepala_gudang_regional')
            ->set('name', 'Kepala Gudang Regional')
            ->set('selected', ['auth.login', 'auth.logout', 'profile.update', 'user.view'])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $role = Role::findByCode('kepala_gudang_regional');

        $this->assertNotNull($role);
        $this->assertFalse($role->is_builtin, 'Role buatan company bukan role bawaan.');
        $this->assertTrue($role->is_active);
        $this->assertEqualsCanonicalizing(
            ['auth.login', 'auth.logout', 'profile.update', 'user.view'],
            $role->permissions()->pluck('name')->all(),
        );
    }

    #[Test]
    public function tc_acc_ui_23_kode_role_divalidasi_dan_tidak_boleh_ganda(): void
    {
        $admin = $this->makeUser('company_admin');

        Livewire::actingAs($admin)->test(RoleForm::class)
            ->set('code', 'Huruf Besar Dan Spasi')
            ->set('name', 'Contoh')
            ->call('save')
            ->assertHasErrors('code');

        Livewire::actingAs($admin)->test(RoleForm::class)
            ->set('code', 'warehouse_head')   // sudah ada (bawaan)
            ->set('name', 'Duplikat')
            ->call('save')
            ->assertHasErrors('code');
    }

    #[Test]
    public function tc_acc_ui_24_salin_permission_dari_role_bawaan(): void
    {
        $admin = $this->makeUser('company_admin');
        $bawaan = Role::findByCode('warehouse_head');

        $komponen = Livewire::actingAs($admin)->test(RoleForm::class)
            ->set('copyFrom', (string) $bawaan->id)
            ->call('salin');

        // Urutannya mengikuti urutan penyimpanan, bukan bagian dari kontrak;
        // yang dijamin adalah isinya sama persis.
        $this->assertEqualsCanonicalizing(
            $bawaan->permissions()->pluck('name')->all(),
            $komponen->get('selected'),
        );
    }

    #[Test]
    public function tc_acc_ui_25_pilih_dan_kosongkan_permission_satu_modul(): void
    {
        $admin = $this->makeUser('company_admin');
        $kunciModulUser = Permission::where('module', 'user')->pluck('name')->all();

        $komponen = Livewire::actingAs($admin)->test(RoleForm::class)
            ->set('selected', [])
            ->call('pilihModul', 'user', true);

        $this->assertEqualsCanonicalizing($kunciModulUser, $komponen->get('selected'));

        $komponen->call('pilihModul', 'user', false);
        $this->assertSame([], $komponen->get('selected'));
    }

    #[Test]
    public function tc_acc_ui_26_role_bawaan_bisa_diubah_permission_tetapi_tidak_dinonaktifkan(): void
    {
        $admin = $this->makeUser('company_admin');
        $bawaan = Role::findByCode('driver');

        // Permission role bawaan boleh diubah.
        Livewire::actingAs($admin)->test(RoleForm::class, ['roleId' => $bawaan->id])
            ->set('selected', ['auth.login', 'auth.logout'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEqualsCanonicalizing(
            ['auth.login', 'auth.logout'],
            $bawaan->refresh()->permissions()->pluck('name')->all(),
        );
        $this->assertTrue($bawaan->is_builtin, 'Sifat bawaan tidak berubah.');

        // Tetapi tidak bisa dinonaktifkan.
        Livewire::actingAs($admin)->test(RoleList::class)->call('deactivate', $bawaan->id);

        $this->assertTrue($bawaan->refresh()->is_active);
    }

    #[Test]
    public function tc_acc_ui_27_role_yang_masih_dipakai_tidak_bisa_dinonaktifkan(): void
    {
        $admin = $this->makeUser('company_admin');

        $role = Role::create([
            'code' => 'peran_sementara',
            'name' => 'Peran Sementara',
            'guard_name' => 'web',
            'is_builtin' => false,
            'is_client_role' => false,
            'is_active' => true,
        ]);

        $pemakai = $this->makeUser('driver');
        $this->assignRole($pemakai, 'peran_sementara');

        Livewire::actingAs($admin)->test(RoleList::class)->call('deactivate', $role->id);
        $this->assertTrue($role->refresh()->is_active, 'Role yang masih dipakai tidak boleh dinonaktifkan.');

        // Setelah penugasan dicabut, role bisa dinonaktifkan lalu diaktifkan lagi.
        $pemakai->roleAssignments()->where('role_id', $role->id)->delete();

        Livewire::actingAs($admin)->test(RoleList::class)->call('deactivate', $role->id);
        $this->assertFalse($role->refresh()->is_active);

        Livewire::actingAs($admin)->test(RoleList::class)->call('reactivate', $role->id);
        $this->assertTrue($role->refresh()->is_active);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Enums\UserStatus;
use App\Domain\Access\Livewire\UserDetail;
use App\Domain\Access\Livewire\UserForm;
use App\Domain\Access\Livewire\UserList;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Access\Models\UserInvitation;
use App\Domain\Access\Notifications\UserInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * Layar pengelolaan pengguna — docs/wms/10-access.md §6.3.
 */
class UserManagementTest extends TenantTestCase
{
    #[Test]
    public function tc_acc_ui_01_hanya_yang_berhak_membuka_daftar_pengguna(): void
    {
        $admin = $this->makeUser('company_admin');
        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1);

        $this->actingAs($admin)->get($this->tenantUrl('/users'))->assertOk();
        $this->actingAs($staf)->get($this->tenantUrl('/users'))->assertForbidden();
    }

    #[Test]
    public function tc_acc_ui_02_daftar_pengguna_bisa_dicari_dan_difilter(): void
    {
        $admin = $this->makeUser('company_admin', ScopeType::All, null, ['name' => 'Rina Admin']);
        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 7, ['name' => 'Dedi Staf Gudang']);
        $nonaktif = $this->makeUser('driver', ScopeType::All, null, ['name' => 'Gani Nonaktif', 'is_active' => false]);

        $komponen = Livewire::actingAs($admin)->test(UserList::class);

        $komponen->assertSee('Rina Admin')->assertSee('Dedi Staf Gudang');

        // Cari nama
        $komponen->set('search', 'Dedi')
            ->assertSee('Dedi Staf Gudang')
            ->assertDontSee('Rina Admin');

        // Filter status nonaktif
        $komponen->set('search', '')
            ->set('statusFilter', UserStatus::Inactive->value)
            ->assertSee('Gani Nonaktif')
            ->assertDontSee('Dedi Staf Gudang');

        // Filter role
        $komponen->set('statusFilter', '')
            ->set('roleFilter', (string) Role::findByCode('warehouse_staff')->id)
            ->assertSee('Dedi Staf Gudang')
            ->assertDontSee('Rina Admin');

        // Filter cakupan gudang tertentu
        $komponen->set('roleFilter', '')
            ->set('scopeTypeFilter', ScopeType::Warehouse->value)
            ->set('scopeIdFilter', '7')
            ->assertSee('Dedi Staf Gudang')
            ->assertDontSee('Gani Nonaktif');

        $this->assertNotNull($staf->id);
        $this->assertNotNull($nonaktif->id);
    }

    #[Test]
    public function tc_acc_ui_03_nonaktifkan_pengguna_wajib_alasan(): void
    {
        $admin = $this->makeUser('company_admin');
        $target = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1);

        $komponen = Livewire::actingAs($admin)->test(UserList::class)
            ->call('confirmDeactivate', $target->id)
            ->call('deactivate')
            ->assertHasErrors(['deactivateReason' => 'required']);

        $this->assertTrue($target->refresh()->is_active, 'User tidak boleh nonaktif tanpa alasan.');

        $komponen->set('deactivateReason', 'Mengundurkan diri')
            ->set('deactivateNotes', 'Efektif akhir bulan')
            ->call('deactivate')
            ->assertHasNoErrors();

        $this->assertFalse($target->refresh()->is_active);
        $this->assertDatabaseHas('users', ['id' => $target->id], 'tenant');
    }

    #[Test]
    public function tc_acc_ui_04_admin_company_terakhir_tidak_bisa_dinonaktifkan_lewat_layar(): void
    {
        // Satu-satunya Admin Company yang aktif.
        $admin = $this->makeUser('company_admin');

        // Petugas lain yang berhak menonaktifkan user (role buatan company).
        $petugas = $this->makeUser('warehouse_head', ScopeType::Warehouse, 1);
        $this->berikanRolePengelolaUser($petugas);

        Livewire::actingAs($petugas)->test(UserList::class)
            ->call('confirmDeactivate', $admin->id)
            ->set('deactivateReason', 'Coba nonaktifkan admin terakhir')
            ->call('deactivate')
            ->assertHasErrors('deactivateReason');

        $this->assertTrue(
            $admin->refresh()->is_active,
            'Admin Company aktif terakhir harus tetap aktif (BR-ACC-02).',
        );
    }

    #[Test]
    public function tc_acc_ui_04b_tidak_bisa_menonaktifkan_diri_sendiri(): void
    {
        $admin = $this->makeUser('company_admin');
        $this->makeUser('company_admin'); // admin kedua agar BR-ACC-02 tidak ikut memblokir

        Livewire::actingAs($admin)->test(UserList::class)
            ->call('confirmDeactivate', $admin->id)
            ->set('deactivateReason', 'Nonaktifkan diri sendiri')
            ->call('deactivate')
            ->assertForbidden();

        $this->assertTrue($admin->refresh()->is_active);
    }

    /** Role buatan company yang boleh melihat & menonaktifkan user. */
    private function berikanRolePengelolaUser(User $user): void
    {
        $role = Role::create([
            'code' => 'pengelola_user',
            'name' => 'Pengelola User',
            'guard_name' => 'web',
            'is_builtin' => false,
            'is_client_role' => false,
            'is_active' => true,
        ]);

        $role->permissions()->sync(
            \App\Domain\Access\Models\Permission::query()
                ->whereIn('name', ['auth.login', 'auth.logout', 'profile.update', 'user.view', 'user.deactivate'])
                ->pluck('id')->all(),
        );

        $this->assignRole($user, 'pengelola_user');
        $user->forgetPermissionCache();
    }

    #[Test]
    public function tc_acc_ui_05_undang_ulang_dan_kirim_tautan_password(): void
    {
        Notification::fake();

        $admin = $this->makeUser('company_admin');
        $belumAktif = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1, ['password' => null]);

        Livewire::actingAs($admin)->test(UserList::class)
            ->call('resendInvitation', $belumAktif->id)
            ->assertHasNoErrors();

        Notification::assertSentTo($belumAktif, UserInvitationNotification::class);
        $this->assertSame(1, UserInvitation::where('user_id', $belumAktif->id)->count());

        $aktif = $this->makeUser('driver');

        Livewire::actingAs($admin)->test(UserList::class)
            ->call('sendPasswordReset', $aktif->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $aktif->email], 'tenant');
    }

    #[Test]
    public function tc_acc_ui_06_tambah_pengguna_membuat_penugasan_dan_undangan(): void
    {
        Notification::fake();

        $admin = $this->makeUser('company_admin');
        $roleStaf = Role::findByCode('warehouse_staff');

        Livewire::actingAs($admin)->test(UserForm::class)
            ->set('name', 'Pengguna Baru')
            ->set('email', 'baru@demo.wms.test')
            ->set('phone', '+628123456789')
            ->set('assignments', [[
                'role_id' => $roleStaf->id,
                'scope_type' => ScopeType::Warehouse->value,
                'scope_id' => 3,
                'valid_from' => null,
                'valid_until' => null,
            ]])
            ->set('sendInvitation', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $baru = User::where('email', 'baru@demo.wms.test')->firstOrFail();

        $this->assertSame(['warehouse_staff'], $baru->roleCodes());
        $this->assertSame([3], $baru->accessibleWarehouseIds());
        $this->assertSame(UserStatus::Invited, $baru->status());

        Notification::assertSentTo($baru, UserInvitationNotification::class);
    }

    #[Test]
    public function tc_acc_ui_07_form_menolak_data_tidak_lengkap_dan_role_klien_untuk_user_internal(): void
    {
        $admin = $this->makeUser('company_admin');

        // Nama & email wajib, penugasan wajib minimal satu.
        Livewire::actingAs($admin)->test(UserForm::class)
            ->set('name', '')
            ->set('email', 'bukan-email')
            ->set('assignments', [['role_id' => null, 'scope_type' => 'all', 'scope_id' => null, 'valid_from' => null, 'valid_until' => null]])
            ->call('save')
            ->assertHasErrors(['name', 'email', 'assignments.0.role_id']);

        // BR-ACC-03: role Klien untuk user tanpa client_id ditolak Action.
        Livewire::actingAs($admin)->test(UserForm::class)
            ->set('name', 'Salah Pasang')
            ->set('email', 'salah@demo.wms.test')
            ->set('assignments', [[
                'role_id' => Role::findByCode('client_user')->id,
                'scope_type' => ScopeType::Project->value,
                'scope_id' => 1,
                'valid_from' => null,
                'valid_until' => null,
            ]])
            ->call('save')
            ->assertHasErrors('assignments');

        $this->assertDatabaseMissing('users', ['email' => 'salah@demo.wms.test'], 'tenant');
    }

    #[Test]
    public function tc_acc_ui_08_ubah_pengguna_menyinkronkan_penugasan(): void
    {
        $admin = $this->makeUser('company_admin');
        $target = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1);

        $roleKepala = Role::findByCode('warehouse_head');

        Livewire::actingAs($admin)->test(UserForm::class, ['userId' => $target->id])
            ->set('name', 'Nama Diubah')
            ->set('assignments', [[
                'role_id' => $roleKepala->id,
                'scope_type' => ScopeType::Warehouse->value,
                'scope_id' => 5,
                'valid_from' => null,
                'valid_until' => null,
            ]])
            ->call('save')
            ->assertHasNoErrors();

        $target->refresh()->forgetPermissionCache();

        $this->assertSame('Nama Diubah', $target->name);
        $this->assertSame(['warehouse_head'], $target->roleCodes(), 'Penugasan lama harus dicabut.');
        $this->assertSame([5], $target->accessibleWarehouseIds());
    }

    #[Test]
    public function tc_acc_ui_09_email_terkunci_setelah_undangan_diterima(): void
    {
        $admin = $this->makeUser('company_admin');
        $target = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1, [
            'email' => 'terkunci@demo.wms.test',
            'email_verified_at' => now(),
        ]);

        Livewire::actingAs($admin)->test(UserForm::class, ['userId' => $target->id])
            ->assertSet('email', 'terkunci@demo.wms.test')
            ->set('email', 'email-baru@demo.wms.test')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(
            'terkunci@demo.wms.test',
            $target->refresh()->email,
            'Email tidak boleh berubah setelah undangan diterima.',
        );
    }

    #[Test]
    public function tc_acc_ui_10_detail_pengguna_menampilkan_tab_role_dan_riwayat(): void
    {
        $admin = $this->makeUser('company_admin');
        $target = $this->makeUser('warehouse_head', ScopeType::Warehouse, 2, ['name' => 'Andi Kepala']);

        activity('access')->performedOn($target)->log('Uji riwayat');

        $komponen = Livewire::actingAs($admin)->test(UserDetail::class, ['userId' => $target->id]);

        $komponen->assertSee('Andi Kepala')->assertSee('Ringkasan');

        $komponen->call('pilihTab', 'role')
            ->assertSee('Kepala Gudang')
            ->assertSee('Gudang #2');

        $komponen->call('pilihTab', 'riwayat')->assertSee('Uji riwayat');
    }

    #[Test]
    public function tc_acc_ui_11_aktifkan_kembali_butuh_penugasan_yang_berlaku(): void
    {
        $admin = $this->makeUser('company_admin');
        $nonaktif = $this->makeUser('', ScopeType::All, null, ['is_active' => false]);

        Livewire::actingAs($admin)->test(UserList::class)
            ->call('reactivate', $nonaktif->id);

        $this->assertFalse($nonaktif->refresh()->is_active, 'Tanpa penugasan role, user tidak boleh diaktifkan.');

        $this->assignRole($nonaktif, 'driver');

        Livewire::actingAs($admin)->test(UserList::class)
            ->call('reactivate', $nonaktif->id);

        $this->assertTrue($nonaktif->refresh()->is_active);
    }
}

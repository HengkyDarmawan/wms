<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Actions\DeactivateUser;
use App\Domain\Access\Actions\RevokeRole;
use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Enums\UserStatus;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-ACC-14, TC-ACC-15, TC-ACC-16, TC-ACC-25 — siklus hidup user & role
 * (P-03, BR-ACC-02, BR-GEN-11).
 */
class UserLifecycleTest extends TenantTestCase
{
    #[Test]
    public function tc_acc_14_admin_company_terakhir_tidak_bisa_dinonaktifkan(): void
    {
        $admin = $this->makeUser('company_admin');

        try {
            app(DeactivateUser::class)->handle($admin, 'Pindah tugas');
            $this->fail('Admin Company terakhir seharusnya tidak bisa dinonaktifkan.');
        } catch (AccessRuleException $e) {
            $this->assertSame('BR-ACC-02', $e->rule);
        }

        $this->assertTrue($admin->refresh()->is_active);

        // Rolenya juga tidak bisa dicabut.
        $assignment = RoleAssignment::query()
            ->where('user_id', $admin->id)
            ->where('role_id', Role::findByCode('company_admin')->id)
            ->firstOrFail();

        try {
            app(RevokeRole::class)->handle($assignment);
            $this->fail('Role Admin Company terakhir seharusnya tidak bisa dicabut.');
        } catch (AccessRuleException $e) {
            $this->assertSame('BR-ACC-02', $e->rule);
        }
    }

    #[Test]
    public function tc_acc_14b_admin_kedua_membuat_penonaktifan_diizinkan(): void
    {
        $admin1 = $this->makeUser('company_admin');
        $this->makeUser('company_admin');

        $hasil = app(DeactivateUser::class)->handle($admin1, 'Pindah tugas');

        $this->assertFalse($hasil->is_active);
    }

    #[Test]
    public function tc_acc_15_user_dinonaktifkan_tidak_dihapus_dan_sesinya_dicabut(): void
    {
        config(['access.login.throttle_per_minute' => 100]);

        $user = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1);
        $userId = $user->id;

        DB::table('sessions')->insert([
            'id' => 'sesi-aktif',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'uji',
            'payload' => base64_encode('kosong'),
            'last_activity' => now()->timestamp,
        ]);

        app(DeactivateUser::class)->handle($user, 'Mengundurkan diri', 'Efektif akhir bulan');

        // P-03: baris user tetap ada untuk jejak dokumen historis.
        $this->assertDatabaseHas('users', ['id' => $userId, 'is_active' => 0]);
        $this->assertSame(UserStatus::Inactive, User::find($userId)->status());

        $this->assertSame(0, DB::table('sessions')->where('user_id', $userId)->count());

        $this->post($this->tenantUrl('/login'), [
            'email' => $user->email,
            'password' => 'Rahasia#2026!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function tc_acc_16_menonaktifkan_user_wajib_mengisi_alasan(): void
    {
        $user = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1);

        try {
            app(DeactivateUser::class)->handle($user, '   ');
            $this->fail('Alasan kosong seharusnya ditolak.');
        } catch (AccessRuleException $e) {
            $this->assertSame('BR-GEN-11', $e->rule);
            $this->assertSame('Alasan wajib diisi.', $e->getMessage());
        }

        // Keterangan boleh kosong.
        $hasil = app(DeactivateUser::class)->handle($user, 'Alasan yang jelas', null);
        $this->assertFalse($hasil->is_active);
    }

    #[Test]
    public function tc_acc_25_role_bawaan_tidak_bisa_dinonaktifkan_tetapi_bisa_disalin(): void
    {
        $admin = $this->makeUser('company_admin');
        $bawaan = Role::findByCode('warehouse_head');

        $this->assertTrue($bawaan->is_builtin);
        $this->assertFalse(
            $admin->can('deactivate', $bawaan),
            'Role bawaan tidak boleh dinonaktifkan (RolePolicy).',
        );

        // Salin permission role bawaan ke role baru buatan company.
        $baru = Role::create([
            'code' => 'kepala_gudang_regional',
            'name' => 'Kepala Gudang Regional',
            'guard_name' => 'web',
            'is_builtin' => false,
            'is_client_role' => false,
            'is_active' => true,
        ]);

        $baru->permissions()->sync($bawaan->permissions()->pluck('permissions.id')->all());

        $this->assertEqualsCanonicalizing(
            $bawaan->permissions()->pluck('name')->all(),
            $baru->refresh()->permissions()->pluck('name')->all(),
        );

        $this->assertTrue($admin->can('deactivate', $baru), 'Role buatan company boleh dinonaktifkan.');
    }

    #[Test]
    public function tc_acc_bonus_status_user_diturunkan_dari_kolom(): void
    {
        $user = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1);
        $this->assertSame(UserStatus::Active, $user->status());

        $user->forceFill(['locked_until' => now()->addMinutes(10)])->save();
        $this->assertSame(UserStatus::Locked, $user->refresh()->status());

        $user->forceFill(['locked_until' => null, 'is_active' => false])->save();
        $this->assertSame(UserStatus::Inactive, $user->refresh()->status());
    }
}

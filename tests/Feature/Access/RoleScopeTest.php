<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Actions\AssignRole;
use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\Role;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-ACC-10 s.d. TC-ACC-13 dan TC-ACC-26 — role, cakupan, dan permission
 * (BR-GEN-09, BR-ACC-01, BR-ACC-03, BR-ACC-04, BR-ACC-05, A-04).
 */
class RoleScopeTest extends TenantTestCase
{
    #[Test]
    public function tc_acc_10_satu_user_dua_role_di_gudang_berbeda(): void
    {
        // A-04: Kepala Gudang di CKG (1) dan Staf Gudang di BKS (2).
        $user = $this->makeUser('warehouse_head', ScopeType::Warehouse, 1);
        $this->assignRole($user, 'warehouse_staff', ScopeType::Warehouse, 2);

        $user->forgetPermissionCache();

        $this->assertEqualsCanonicalizing(
            ['warehouse_head', 'warehouse_staff'],
            $user->roleCodes(),
        );

        $this->assertEqualsCanonicalizing([1, 2], $user->accessibleWarehouseIds());
        $this->assertTrue($user->canAccessWarehouse(1));
        $this->assertTrue($user->canAccessWarehouse(2));
        $this->assertFalse($user->canAccessWarehouse(3), 'Gudang di luar cakupan harus ditolak.');

        // Permission gabungan dari kedua role.
        $this->assertTrue($user->hasPermission('user.view'));      // dari Kepala Gudang
        $this->assertTrue($user->hasPermission('device.manage'));  // dari Staf Gudang
        $this->assertFalse($user->hasPermission('user.create'));   // tidak dimiliki keduanya
    }

    #[Test]
    public function tc_acc_11_role_klien_tidak_bisa_digabung_role_internal(): void
    {
        $assign = app(AssignRole::class);
        $internal = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1);
        $clientRole = Role::findByCode('client_user');

        try {
            $assign->handle($internal, $clientRole, ScopeType::Project, 1);
            $this->fail('Role Klien seharusnya ditolak untuk user internal.');
        } catch (AccessRuleException $e) {
            $this->assertSame('BR-ACC-03', $e->rule);
        }

        // Sebaliknya: user klien tidak boleh diberi role internal.
        $proyek = $this->makeProject();
        $client = $this->makeUser('client_user', ScopeType::Project, $proyek->id, ['client_id' => $proyek->client_id]);

        try {
            $assign->handle($client, Role::findByCode('warehouse_staff'), ScopeType::Warehouse, 1);
            $this->fail('Role internal seharusnya ditolak untuk user klien.');
        } catch (AccessRuleException $e) {
            $this->assertSame('BR-ACC-03', $e->rule);
        }
    }

    #[Test]
    public function tc_acc_12_cakupan_semua_ditolak_untuk_role_klien(): void
    {
        $assign = app(AssignRole::class);
        $proyek = $this->makeProject();
        $client = $this->makeUser('client_user', ScopeType::Project, $proyek->id, ['client_id' => $proyek->client_id]);

        try {
            $assign->handle($client, Role::findByCode('client_user'), ScopeType::All);
            $this->fail('Cakupan Semua seharusnya ditolak untuk role Klien.');
        } catch (AccessRuleException $e) {
            $this->assertSame('BR-ACC-04', $e->rule);
        }
    }

    #[Test]
    public function tc_acc_12b_cakupan_gudang_wajib_memilih_gudang(): void
    {
        $assign = app(AssignRole::class);
        $user = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1);

        try {
            $assign->handle($user, Role::findByCode('warehouse_head'), ScopeType::Warehouse, null);
            $this->fail('Cakupan gudang tanpa id seharusnya ditolak.');
        } catch (AccessRuleException $e) {
            $this->assertSame('BR-ACC-04', $e->rule);
        }
    }

    #[Test]
    public function tc_acc_13_user_tanpa_penugasan_role_tidak_bisa_masuk(): void
    {
        config(['access.login.throttle_per_minute' => 100]);

        // Dibuat tanpa penugasan role sama sekali.
        $user = $this->makeUser('', ScopeType::All);

        $this->assertFalse($user->canSignIn());

        $this->post($this->tenantUrl('/login'), [
            'email' => $user->email,
            'password' => 'Rahasia#2026!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->assertDatabaseHas('login_attempts', [
            'email' => $user->email,
            'result' => \App\Domain\Access\Enums\LoginResult::NoRole->value,
        ]);
    }

    #[Test]
    public function tc_acc_26_penugasan_yang_sudah_lewat_masa_berlaku_diabaikan(): void
    {
        $user = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1);

        // Penugasan kedua sudah berakhir kemarin (mis. auditor eksternal).
        $this->assignRole($user, 'internal_auditor', ScopeType::All, null, now()->subDay()->toDateString());

        $user->forgetPermissionCache();

        $this->assertSame(['warehouse_staff'], $user->roleCodes());
        $this->assertSame([1], $user->accessibleWarehouseIds(), 'Cakupan dari penugasan kedaluwarsa tidak boleh ikut.');
        $this->assertTrue($user->canSignIn(), 'Masih bisa masuk karena ada penugasan lain yang berlaku.');
    }

    #[Test]
    public function tc_acc_bonus_cakupan_semua_membuka_semua_gudang(): void
    {
        $admin = $this->makeUser('company_admin');

        $this->assertNull($admin->accessibleWarehouseIds(), 'Cakupan semua ditandai null.');
        $this->assertTrue($admin->canAccessWarehouse(99));
        $this->assertTrue($admin->hasPermission('user.create'));
        $this->assertTrue($admin->hasPermission('support_access.grant'));
    }
}

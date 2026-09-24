<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use Database\Seeders\Tenant\DemoSeeder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-ACC-27 — seeder demo menghasilkan akun persis seperti docs/00-akun-uji.md.
 */
class DemoSeederTest extends TenantTestCase
{
    #[Test]
    public function tc_acc_27_seeder_demo_membuat_semua_akun_dan_penugasannya(): void
    {
        (new DemoSeeder)->run();

        $harapan = [
            'admin@demo.wms.test' => ['company_admin'],
            'manajemen@demo.wms.test' => ['management'],
            'kagudang.ckg@demo.wms.test' => ['warehouse_head', 'warehouse_staff'],
            'kagudang.bks@demo.wms.test' => ['warehouse_head'],
            'staf1.ckg@demo.wms.test' => ['warehouse_staff'],
            'staf2.ckg@demo.wms.test' => ['warehouse_staff'],
            'staf.krw@demo.wms.test' => ['warehouse_staff'],
            'driver1@demo.wms.test' => ['driver'],
            'driver2@demo.wms.test' => ['driver'],
            'pemohon.prj001@demo.wms.test' => ['internal_requester'],
            'pr@demo.wms.test' => ['pr_follow_up'],
            'auditor@demo.wms.test' => ['internal_auditor'],
            'klien1@klien-satu.test' => ['client_user'],
            'klien2@klien-dua.test' => ['client_user'],
        ];

        foreach ($harapan as $email => $roles) {
            $user = User::query()->where('email', $email)->first();

            $this->assertNotNull($user, 'Akun '.$email.' harus ada.');
            $this->assertTrue($user->is_active, 'Akun '.$email.' harus aktif.');
            $this->assertEqualsCanonicalizing($roles, $user->roleCodes(), 'Role '.$email.' tidak sesuai.');
            $this->assertTrue($user->canSignIn(), 'Akun '.$email.' harus bisa masuk.');
        }

        // Klien memakai client_id, user internal tidak.
        $this->assertNotNull(User::query()->where('email', 'klien1@klien-satu.test')->first()->client_id);
        $this->assertNull(User::query()->where('email', 'admin@demo.wms.test')->first()->client_id);

        // Atasan langsung terisi untuk approval "atasan langsung" (D-16, BR-APR-03).
        $staf = User::query()->where('email', 'staf1.ckg@demo.wms.test')->firstOrFail();
        $this->assertNotNull($staf->manager_id);
        $this->assertSame('kagudang.ckg@demo.wms.test', $staf->manager->email);
    }

    #[Test]
    public function tc_acc_27b_referensi_role_dan_permission_lengkap(): void
    {
        // Dihitung per modul supaya menambah modul baru tidak memecahkan uji ini.
        $perModul = Permission::query()->selectRaw('module, count(*) as jumlah')
            ->groupBy('module')->pluck('jumlah', 'module')->all();

        $this->assertSame(24, (int) ($perModul['master'] ?? 0), 'Jumlah permission modul Master.');
        $this->assertSame(8, (int) ($perModul['warehouse'] ?? 0), 'Jumlah permission modul Warehouse.');
        $this->assertSame(5, (int) ($perModul['stock'] ?? 0), 'Jumlah permission modul Stock.');
        $this->assertSame(14, (int) ($perModul['request'] ?? 0), 'Jumlah permission modul Request.');
        $this->assertSame(5, (int) ($perModul['picking'] ?? 0), 'Jumlah permission modul Picking.');
        $this->assertSame(7, (int) ($perModul['shipment'] ?? 0), 'Jumlah permission modul Shipment.');
        $this->assertSame(6, (int) ($perModul['receipt'] ?? 0), 'Jumlah permission modul Receipt.');
        $this->assertSame(3, (int) ($perModul['putaway'] ?? 0), 'Jumlah permission modul Put-away.');
        $this->assertSame(6, (int) ($perModul['vendor_return'] ?? 0), 'Jumlah permission modul Retur ke vendor.');
        $this->assertSame(5, (int) ($perModul['approval'] ?? 0), 'Jumlah permission modul Approval.');
        $this->assertSame(8, (int) ($perModul['count'] ?? 0), 'Jumlah permission modul Count (stock opname).');
        $this->assertSame(4, (int) ($perModul['adjustment'] ?? 0), 'Jumlah permission modul Adjustment.');

        $akses = collect($perModul)
            ->except(['master', 'warehouse', 'stock', 'request', 'picking', 'shipment', 'receipt', 'putaway', 'vendor_return', 'approval', 'count', 'adjustment'])
            ->sum();

        $this->assertSame(22, (int) $akses, 'Jumlah permission modul Access.');
        $this->assertSame(10, Role::count(), 'Jumlah role bawaan (Blueprint §4.2).');

        $this->assertTrue(Role::findByCode('client_user')->is_client_role);
        $this->assertFalse(Role::findByCode('warehouse_head')->is_client_role);

        foreach (Role::all() as $role) {
            $this->assertTrue($role->is_builtin, 'Role '.$role->code.' harus ditandai bawaan.');
        }

        // Admin Company memegang seluruh permission modul.
        $this->assertSame(
            Permission::count(),
            Role::findByCode('company_admin')->permissions()->count(),
        );
    }
}

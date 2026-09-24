<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\OrgUnit;
use App\Domain\Access\Models\Position;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Client;
use App\Domain\Master\Models\Project;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Data contoh company DEMO — sumber kebenaran: docs/00-akun-uji.md.
 * HANYA untuk dev/demo/staging. Jangan dijalankan di produksi.
 *
 * Klien, proyek, dan gudang dibuat seeder masing-masing lalu dicari lewat
 * kodenya, jadi cakupan role menunjuk baris yang benar-benar ada (BR-GEN-09).
 */
class DemoSeeder extends Seeder
{
    private const PASSWORD = 'Demo#2026!';

    public function run(): void
    {
        $this->call(ReferenceSeeder::class);
        $this->call(MasterReferenceSeeder::class);
        $this->call(WarehouseReferenceSeeder::class);
        $this->call(TemplateReferenceSeeder::class);

        // Master lebih dulu: users.client_id menunjuk clients, cakupan menunjuk projects.
        $this->call(MasterDemoSeeder::class);

        // Gudang menyusul proyek: Gudang Site terikat proyek (BR-WH-04).
        $this->call(WarehouseDemoSeeder::class);

        $units = $this->seedOrgUnits();
        $positions = $this->seedPositions($units);
        $this->seedUsers($units, $positions);
        $this->assignProjectPics();

        // Stok awal dan kendaraan menyusul user: kendaraan menunjuk driver (A-72).
        $this->call(StockDemoSeeder::class);

        // Aturan approval demo §5 (REQ, RTV); butuh role Manajemen.
        $this->call(ApprovalDemoSeeder::class);

        $this->command?->info('Data demo siap: '.User::count().' user, password '.self::PASSWORD.'.');
    }

    /** @return array<string, OrgUnit> */
    private function seedOrgUnits(): array
    {
        $direksi = OrgUnit::updateOrCreate(['code' => 'DIR'], ['name' => 'Direksi', 'parent_id' => null]);

        $units = ['DIR' => $direksi];

        foreach ([
            'OPS' => 'Operasional',
            'PRJ' => 'Proyek',
            'FIN' => 'Keuangan',
            'AUD' => 'Audit',
        ] as $code => $name) {
            $units[$code] = OrgUnit::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'parent_id' => $direksi->id],
            );
        }

        return $units;
    }

    /**
     * @param  array<string, OrgUnit>  $units
     * @return array<string, Position>
     */
    private function seedPositions(array $units): array
    {
        $definitions = [
            'DIREKTUR' => ['DIR', 'Direktur', 1],
            'KA_GUDANG' => ['OPS', 'Kepala Gudang', 2],
            'STAF_GUDANG' => ['OPS', 'Staf Gudang', 3],
            'DRIVER' => ['OPS', 'Driver', 3],
            'ENGINEER' => ['PRJ', 'Engineer / PIC Proyek', 2],
            'PURCHASING' => ['FIN', 'Purchasing', 2],
            'AUDITOR' => ['AUD', 'Auditor Internal', 2],
        ];

        $positions = [];

        foreach ($definitions as $code => [$unitCode, $name, $level]) {
            $positions[$code] = Position::updateOrCreate(
                ['code' => $code],
                ['org_unit_id' => $units[$unitCode]->id, 'name' => $name, 'level' => $level],
            );
        }

        return $positions;
    }

    /**
     * @param  array<string, OrgUnit>  $units
     * @param  array<string, Position>  $positions
     */
    private function seedUsers(array $units, array $positions): void
    {
        $clientId = Client::query()->pluck('id', 'code')->all();
        $projectId = Project::query()->pluck('id', 'code')->all();
        $warehouseId = Warehouse::withoutGlobalScopes()->pluck('id', 'code')->all();

        // [email, nama, unit, jabatan, atasan(email|null), client, [ [roleCode, scopeType, scopeId], … ], phone]
        $definitions = [
            ['admin@demo.wms.test', 'Rina Admin', 'DIR', 'DIREKTUR', null, null,
                [['company_admin', ScopeType::All, null]], '+6281200000001'],

            ['manajemen@demo.wms.test', 'Budi Direktur', 'DIR', 'DIREKTUR', null, null,
                [['management', ScopeType::All, null]], '+6281200000002'],

            ['kagudang.ckg@demo.wms.test', 'Andi Kepala', 'OPS', 'KA_GUDANG', 'manajemen@demo.wms.test', null,
                [
                    ['warehouse_head', ScopeType::Warehouse, $warehouseId['CKG']],
                    ['warehouse_staff', ScopeType::Warehouse, $warehouseId['BKS']],
                ], '+6281200000003'],

            ['kagudang.bks@demo.wms.test', 'Sari Kepala', 'OPS', 'KA_GUDANG', 'manajemen@demo.wms.test', null,
                [['warehouse_head', ScopeType::Warehouse, $warehouseId['BKS']]], '+6281200000004'],

            ['staf1.ckg@demo.wms.test', 'Dedi Staf', 'OPS', 'STAF_GUDANG', 'kagudang.ckg@demo.wms.test', null,
                [['warehouse_staff', ScopeType::Warehouse, $warehouseId['CKG']]], '+6281200000005'],

            ['staf2.ckg@demo.wms.test', 'Eko Staf', 'OPS', 'STAF_GUDANG', 'kagudang.ckg@demo.wms.test', null,
                [['warehouse_staff', ScopeType::Warehouse, $warehouseId['CKG']]], '+6281200000006'],

            ['staf.krw@demo.wms.test', 'Fajar Site', 'OPS', 'STAF_GUDANG', 'kagudang.ckg@demo.wms.test', null,
                [
                    ['warehouse_staff', ScopeType::Warehouse, $warehouseId['KRW1']],
                    ['warehouse_staff', ScopeType::Warehouse, $warehouseId['KRW2']],
                ], '+6281200000007'],

            ['driver1@demo.wms.test', 'Gani Driver', 'OPS', 'DRIVER', 'kagudang.ckg@demo.wms.test', null,
                [['driver', ScopeType::All, null]], '+6281200000008'],

            ['driver2@demo.wms.test', 'Hadi Driver', 'OPS', 'DRIVER', 'kagudang.ckg@demo.wms.test', null,
                [['driver', ScopeType::All, null]], '+6281200000009'],

            ['pemohon.prj001@demo.wms.test', 'Indra Engineer', 'PRJ', 'ENGINEER', 'manajemen@demo.wms.test', null,
                [['internal_requester', ScopeType::Project, $projectId['PRJ-001']]], '+6281200000010'],

            ['pr@demo.wms.test', 'Joko Purchasing', 'FIN', 'PURCHASING', 'manajemen@demo.wms.test', null,
                [['pr_follow_up', ScopeType::All, null]], '+6281200000011'],

            ['auditor@demo.wms.test', 'Kartika Auditor', 'AUD', 'AUDITOR', 'manajemen@demo.wms.test', null,
                [['internal_auditor', ScopeType::All, null]], '+6281200000012'],

            ['klien1@klien-satu.test', 'Lina (PT Klien Satu)', null, null, null, $clientId['KL1'],
                [['client_user', ScopeType::Project, $projectId['PRJ-001']]], '+6281200000013'],

            // Proyek PRJ-002 milik CV Klien Dua sengaja masih kosong: portal menampilkan
            // daftar kosong tanpa melihat proyek klien lain (A-21).
            ['klien2@klien-dua.test', 'Maman (CV Klien Dua)', null, null, null, $clientId['KL2'],
                [['client_user', ScopeType::Project, $projectId['PRJ-002']]], '+6281200000014'],
        ];

        $created = [];

        // Tahap 1: buat user tanpa atasan.
        foreach ($definitions as [$email, $name, $unitCode, $positionCode, , $clientId, , $phone]) {
            $created[$email] = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'phone' => $phone,
                    'password' => Hash::make(self::PASSWORD),
                    'client_id' => $clientId,
                    'org_unit_id' => $unitCode !== null ? $units[$unitCode]->id : null,
                    'position_id' => $positionCode !== null ? $positions[$positionCode]->id : null,
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'password_changed_at' => now(),
                ],
            );
        }

        // Tahap 2: atasan langsung + penugasan role (D-16, BR-GEN-09).
        foreach ($definitions as [$email, , , , $managerEmail, , $roles]) {
            $user = $created[$email];

            if ($managerEmail !== null && isset($created[$managerEmail])) {
                $user->forceFill(['manager_id' => $created[$managerEmail]->id])->save();
            }

            foreach ($roles as [$roleCode, $scopeType, $scopeId]) {
                $role = Role::findByCode($roleCode);

                if ($role === null) {
                    continue;
                }

                RoleAssignment::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'role_id' => $role->id,
                        'scope_type' => $scopeType->value,
                        'scope_id' => $scopeId,
                    ],
                    ['valid_from' => null, 'valid_until' => null],
                );
            }
        }
    }

    /** PIC proyek demo sesuai docs/00-akun-uji.md §3. */
    private function assignProjectPics(): void
    {
        $pic = User::query()->where('email', 'pemohon.prj001@demo.wms.test')->value('id');

        if ($pic !== null) {
            Project::query()->where('code', 'PRJ-001')->update(['pic_user_id' => $pic]);
        }
    }
}

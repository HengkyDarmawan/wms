<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\User;
use App\Domain\Master\Models\FeatureSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Company DEMO "kosong" untuk latihan dari nol (docs/00-akun-uji.md §6):
 * hanya data acuan company baru (sama dengan provisioning, `TenantDatabaseSeeder`)
 * dan satu Admin Company yang langsung bisa masuk. Tanpa gudang, master, user
 * lain, stok, maupun aturan approval — wizard *Setup awal* mulai dari 0.
 * HANYA dev/demo/staging; jalankan setelah `tenants:migrate-fresh`.
 */
class BlankDemoSeeder extends Seeder
{
    public const EMAIL = 'admin@demo.wms.test';

    private const PASSWORD = 'Demo#2026!';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('BlankDemoSeeder tidak boleh dijalankan di produksi.');
        }

        $this->call(TenantDatabaseSeeder::class);

        // A-284: latihan dari nol memakai bawaan company baru — tanpa per potong & QC.
        FeatureSetting::seed(['piece' => false, 'qc' => false], overwrite: true);

        $role = Role::findByCode('company_admin')
            ?? throw new RuntimeException('Role Admin Company tidak ada setelah data acuan diisi.');

        $admin = User::updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Rina Admin',
                'password' => Hash::make(self::PASSWORD),
                'is_active' => true,
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ],
        );

        RoleAssignment::updateOrCreate(
            ['user_id' => $admin->id, 'role_id' => $role->id, 'scope_type' => ScopeType::All->value, 'scope_id' => null],
            ['valid_from' => null, 'valid_until' => null],
        );

        $this->command?->info('Company kosong siap: hanya '.self::EMAIL.' (password '.self::PASSWORD.').');
    }
}

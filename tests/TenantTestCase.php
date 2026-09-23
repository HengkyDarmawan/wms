<?php

declare(strict_types=1);

namespace Tests;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ProjectStatus;
use App\Domain\Master\Models\Client;
use App\Domain\Master\Models\Project;
use App\Domain\Platform\Enums\CompanyStatus;
use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Domain\Platform\Models\Company;
use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\Subscription;
use Database\Seeders\Tenant\MasterReferenceSeeder;
use Database\Seeders\Tenant\WarehouseReferenceSeeder;
use Database\Seeders\Tenant\ReferenceSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Basis uji modul tenant.
 *
 * Database pusat & tenant uji disiapkan sekali per proses, lalu setiap test
 * dibungkus transaksi pada kedua koneksi supaya cepat dan saling terisolasi.
 */
abstract class TenantTestCase extends TestCase
{
    protected const COMPANY_CODE = 'TEST';

    protected const SUBDOMAIN = 'test';

    protected static bool $databasesReady = false;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        // Uji tidak bergantung pada hasil build Vite.
        $this->withoutVite();

        if (! static::$databasesReady) {
            $this->prepareDatabases();
            static::$databasesReady = true;
        }

        $this->company = Company::query()->where('code', self::COMPANY_CODE)->firstOrFail();

        tenancy()->initialize($this->company);

        DB::connection('central')->beginTransaction();
        DB::connection('tenant')->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Rollback tidak boleh bergantung pada tenancy masih aktif: satu uji
        // yang memanggil tenancy()->end() dulu membuat transaksi tenant tidak
        // pernah dibatalkan, sehingga datanya bocor ke uji berikutnya.
        foreach (['tenant', 'central'] as $koneksi) {
            $db = DB::connection($koneksi);

            while ($db->transactionLevel() > 0) {
                $db->rollBack();
            }
        }

        tenancy()->end();

        parent::tearDown();
    }

    /** Host company uji, mis. test.wms.test. */
    protected function tenantHost(): string
    {
        return self::SUBDOMAIN.'.'.config('tenancy.central_domains.0', 'wms.test');
    }

    /** Alamat penuh di subdomain company uji (akar tanpa garis miring akhir). */
    protected function tenantUrl(string $path = '/'): string
    {
        $path = ltrim($path, '/');

        return 'http://'.$this->tenantHost().($path === '' ? '' : '/'.$path);
    }

    /**
     * Membuat user tenant lengkap dengan penugasan role (BR-GEN-09).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function makeUser(
        string $roleCode = 'warehouse_staff',
        ScopeType $scopeType = ScopeType::All,
        ?int $scopeId = null,
        array $attributes = [],
    ): User {
        static $counter = 0;
        $counter++;

        $user = User::create(array_merge([
            'name' => 'Pengguna Uji '.$counter,
            'email' => 'uji'.$counter.'.'.uniqid().'@test.wms.test',
            'password' => Hash::make('Rahasia#2026!'),
            'is_active' => true,
            'email_verified_at' => now(),
            'password_changed_at' => now(),
        ], $attributes));

        if ($roleCode !== '') {
            $this->assignRole($user, $roleCode, $scopeType, $scopeId);
        }

        return $user->refresh();
    }

    /**
     * Klien tenant untuk user portal. Dibuat lewat helper supaya foreign key
     * `users.client_id` selalu menunjuk baris yang benar-benar ada.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function makeClient(array $attributes = []): Client
    {
        static $counter = 0;
        $counter++;

        return Client::create(array_merge([
            'code' => 'KLU'.$counter.strtoupper(substr(uniqid(), -4)),
            'name' => 'Klien Uji '.$counter,
            'is_active' => true,
        ], $attributes));
    }

    /**
     * Proyek tenant; klien dibuat otomatis kecuali proyeknya internal (A-06).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function makeProject(array $attributes = []): Project
    {
        static $counter = 0;
        $counter++;

        $internal = (bool) ($attributes['is_internal'] ?? false);

        return Project::create(array_merge([
            'code' => 'PRU'.$counter.strtoupper(substr(uniqid(), -4)),
            'name' => 'Proyek Uji '.$counter,
            'client_id' => $internal ? null : $this->makeClient()->id,
            'is_internal' => $internal,
            'status' => ProjectStatus::Active,
        ], $attributes));
    }

    protected function assignRole(
        User $user,
        string $roleCode,
        ScopeType $scopeType = ScopeType::All,
        ?int $scopeId = null,
        ?string $validUntil = null,
    ): RoleAssignment {
        $role = Role::findByCode($roleCode);

        self::assertNotNull($role, 'Role bawaan '.$roleCode.' tidak ditemukan.');

        $assignment = RoleAssignment::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => $scopeType->value,
            'scope_id' => $scopeType->needsScopeId() ? $scopeId : null,
            'valid_until' => $validUntil,
        ]);

        $user->forgetPermissionCache();

        return $assignment;
    }

    protected function subscription(): Subscription
    {
        return Subscription::query()
            ->where('company_id', $this->company->getTenantKey())
            ->latest('id')
            ->firstOrFail();
    }

    protected function setSubscriptionStatus(SubscriptionStatus $status): void
    {
        $this->subscription()->forceFill(['status' => $status])->save();
    }

    /**
     * Sekali per proses: migrasi database pusat uji, buat company uji
     * (memicu pembuatan + migrasi database tenant), lalu isi data referensi.
     */
    private function prepareDatabases(): void
    {
        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);

        $tenantDb = config('tenancy.database.prefix').self::SUBDOMAIN;

        DB::connection('central')->statement('DROP DATABASE IF EXISTS `'.$tenantDb.'`');

        $plan = Plan::create([
            'code' => 'uji',
            'name' => 'Paket Uji',
            'monthly_price' => 0,
            'is_active' => true,
        ]);

        $company = Company::create([
            'code' => self::COMPANY_CODE,
            'name' => 'PT Uji Otomatis',
            'subdomain' => self::SUBDOMAIN,
            'db_name' => $tenantDb,
            'timezone' => 'Asia/Jakarta',
            'status' => CompanyStatus::Active,
            'plan_id' => $plan->id,
        ]);

        Subscription::create([
            'company_id' => $company->getTenantKey(),
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now()->toDateString(),
            'current_period_end' => now()->addMonth()->toDateString(),
        ]);

        tenancy()->initialize($company);
        (new ReferenceSeeder)->run();
        (new MasterReferenceSeeder)->run();
        (new WarehouseReferenceSeeder)->run();
        tenancy()->end();
    }
}

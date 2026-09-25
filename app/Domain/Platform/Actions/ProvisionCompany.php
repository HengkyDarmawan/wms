<?php

declare(strict_types=1);

namespace App\Domain\Platform\Actions;

use App\Domain\Access\Actions\CreateUser;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Platform\Enums\CompanyStatus;
use App\Domain\Platform\Exceptions\PlatformRuleException;
use App\Domain\Platform\Models\Company;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\Platform\Support\PlatformAudit;
use Database\Seeders\Tenant\TenantDatabaseSeeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Provisioning company (alur 10 langkah 3, Arsitektur §3 langkah 5, A-176):
 * database → migrasi tenant → data acuan (`TenantDatabaseSeeder`) → Admin
 * Company pertama + undangan → status `active`. Aman diulang: setiap langkah
 * memeriksa hasil sebelumnya, sehingga company yang gagal di tengah jalan
 * bisa dilanjutkan dari layar detail Super Admin.
 */
class ProvisionCompany
{
    public function handle(Company $company, ?PlatformUser $actor = null, bool $databaseReady = false): Company
    {
        if ($company->status !== CompanyStatus::Provisioning) {
            throw PlatformRuleException::rule('BR-GEN-01', 'Company '.$company->code.' sudah disiapkan.');
        }

        $asal = tenant();

        try {
            if (! $databaseReady) {
                $this->database($company);
            }

            $company->run(function () {
                (new TenantDatabaseSeeder)->run();
                $this->adminCompany(tenant());
            });
        } catch (\Throwable $e) {
            // Tenant::run() tidak memulihkan konteks bila callback gagal.
            $asal !== null ? tenancy()->initialize($asal) : tenancy()->end();
            $this->gagal($company, $e, $actor);

            return $company->refresh();
        }

        $company->setAttribute('provisioning_error', null);
        $company->setAttribute('provisioned_at', now()->toIso8601String());
        $company->status = CompanyStatus::Active;
        $company->save();

        PlatformAudit::record('Company siap dipakai', $company, $actor, ['db' => $company->db_name]);

        return $company->refresh();
    }

    /** Menandai provisioning gagal tanpa menghapus apa pun (P-03); dilanjutkan lewat `handle()`. */
    public function gagal(Company $company, \Throwable $e, ?PlatformUser $actor = null): void
    {
        report($e);

        $company->setAttribute('provisioning_error', mb_substr($e->getMessage(), 0, 500));
        $company->save();

        PlatformAudit::record('Provisioning company gagal', $company, $actor, ['error' => mb_substr($e->getMessage(), 0, 500)]);
    }

    private function database(Company $company): void
    {
        $db = $company->database();

        if (! $db->manager()->databaseExists($db->getName())) {
            $db->manager()->createDatabase($company);
        }

        $kode = Artisan::call('tenants:migrate', ['--tenants' => [$company->getTenantKey()], '--force' => true]);

        if ($kode !== 0) {
            throw new \RuntimeException('Migrasi tenant gagal: '.trim(Artisan::output()));
        }
    }

    /** Admin Company pertama dengan cakupan semua, lalu undangan (BR-ACC-01). */
    private function adminCompany(Company $company): void
    {
        $email = (string) $company->getAttribute('admin_email');

        if ($email === '' || User::query()->where('email', $email)->exists()) {
            return;
        }

        $role = Role::findByCode('company_admin')
            ?? throw new \RuntimeException('Role Admin Company tidak ada setelah data acuan diisi.');

        app(CreateUser::class)->handle(
            ['name' => (string) ($company->getAttribute('admin_name') ?: 'Admin Company'), 'email' => $email],
            [['role_id' => $role->id, 'scope_type' => 'all', 'scope_id' => null]],
            true,
        );
    }
}

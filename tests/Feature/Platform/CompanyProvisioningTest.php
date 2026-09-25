<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Access\Models\UserInvitation;
use App\Domain\Platform\Enums\CompanyStatus;
use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Domain\Platform\Models\Company;
use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\PlatformAuditLog;
use App\Domain\Platform\Models\PlatformUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-PLT-03 — Super Admin membuat company dari layar: database company dibuat,
 * dimigrasi, diisi data acuan, trial dimulai, Admin Company diundang (A-176).
 *
 * Uji ini membuat database sungguhan (`wms_tenant_test_prv`). CREATE DATABASE
 * menutup transaksi koneksi pusat, jadi semua baris yang dibuat dibersihkan
 * sendiri di akhir uji.
 */
class CompanyProvisioningTest extends TenantTestCase
{
    private const KODE = 'PRV';

    protected function tearDown(): void
    {
        $db = config('tenancy.database.prefix').strtolower(self::KODE);
        $pusat = DB::connection('central');
        $ids = $pusat->table('companies')->where('code', self::KODE)->pluck('id');

        $pusat->table('audit_logs')->where('subject_type', (new Company)->getMorphClass())->whereIn('subject_id', $ids)->delete();
        $pusat->table('audit_logs')->where('log_name', 'platform')->where('description', 'like', 'Company%')->delete();
        $pusat->table('companies')->where('code', self::KODE)->delete();
        $pusat->table('platform_users')->where('email', 'prv-admin@wms.test')->delete();
        $pusat->statement('DROP DATABASE IF EXISTS `'.$db.'`');

        parent::tearDown();
    }

    private function centralUrl(string $path): string
    {
        return 'http://'.config('tenancy.central_domains.0').'/'.ltrim($path, '/');
    }

    #[Test]
    public function tc_plt_03_company_baru_lahir_lengkap_dengan_data_acuan_trial_dan_undangan(): void
    {
        $admin = PlatformUser::create(['email' => 'prv-admin@wms.test', 'name' => 'Super Admin Uji', 'password' => Hash::make('Rahasia#2026!')]);
        $plan = Plan::query()->where('code', 'uji')->firstOrFail();
        $plan->forceFill(['trial_days' => 10])->save();

        $this->actingAs($admin, 'platform')->get($this->centralUrl('admin/companies/create'))->assertOk()->assertSee('Paket Uji');

        // Validasi: subdomain milik platform, kode sudah dipakai.
        $this->actingAs($admin, 'platform')->post($this->centralUrl('admin/companies'), [
            'code' => self::KODE, 'name' => 'PT Provisioning', 'subdomain' => 'admin', 'timezone' => 'Asia/Jakarta',
            'plan_id' => $plan->id, 'admin_name' => 'Rina Admin', 'admin_email' => 'rina@prv.test',
        ])->assertSessionHasErrors('subdomain');
        $this->actingAs($admin, 'platform')->post($this->centralUrl('admin/companies'), [
            'code' => 'TEST', 'name' => 'PT Ganda', 'subdomain' => 'ganda', 'timezone' => 'Asia/Jakarta',
            'plan_id' => $plan->id, 'admin_name' => 'Rina Admin', 'admin_email' => 'rina@prv.test',
        ])->assertSessionHasErrors('code');

        $respon = $this->actingAs($admin, 'platform')->post($this->centralUrl('admin/companies'), [
            'code' => 'prv', 'name' => 'PT Provisioning', 'subdomain' => 'prv', 'timezone' => 'Asia/Makassar',
            'plan_id' => $plan->id, 'admin_name' => 'Rina Admin', 'admin_email' => 'Rina@PRV.test',
        ]);

        $company = Company::query()->where('code', self::KODE)->firstOrFail();
        $respon->assertRedirect($this->centralUrl('admin/companies/'.$company->id));

        $this->assertSame(CompanyStatus::Active, $company->status, (string) $company->provisioningError());
        $this->assertNull($company->provisioningError());
        $this->assertSame(config('tenancy.database.prefix').'prv', $company->db_name);
        $this->assertSame('rina@prv.test', $company->getAttribute('admin_email'));

        $sub = $company->subscription;
        $this->assertSame(SubscriptionStatus::Trial, $sub->status);
        $this->assertSame(now()->addDays(10)->toDateString(), $sub->trial_ends_at->toDateString(), 'Trial bawaan paket (A-11).');

        // Database company terisi data acuan dan Admin Company pertama yang diundang.
        $company->run(function () {
            $this->assertSame(10, Role::count(), 'Role bawaan.');
            $rina = User::query()->where('email', 'rina@prv.test')->sole();
            $this->assertNull($rina->password);
            $this->assertTrue($rina->hasRoleCode('company_admin'));
            $this->assertSame(1, UserInvitation::query()->where('user_id', $rina->id)->count());
        });

        $this->assertTrue(PlatformAuditLog::query()->where('description', 'Company siap dipakai')->where('subject_id', $company->id)->exists());

        // Detail & beranda menampilkan company; provisioning ulang ditolak.
        $this->actingAs($admin, 'platform')->get($this->centralUrl('admin/companies/'.$company->id))->assertOk()->assertSee('prv.'.config('tenancy.central_domains.0'));
        $this->actingAs($admin, 'platform')->get($this->centralUrl('admin'))->assertOk()->assertSee('PT Provisioning');
        $this->actingAs($admin, 'platform')->post($this->centralUrl('admin/companies/'.$company->id.'/provision'))->assertSessionHasErrors('platform');
    }
}

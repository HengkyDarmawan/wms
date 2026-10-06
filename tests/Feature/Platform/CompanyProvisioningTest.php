<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Domain\Access\Actions\InstallBasicOrganization;
use App\Domain\Access\Models\Position;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Access\Models\UserInvitation;
use App\Domain\Approval\Actions\InstallBasicApprovalRules;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Models\ApprovalRule;
use App\Domain\Approval\Support\ApprovalRegistry;
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
 * dimigrasi, diisi data acuan, trial dimulai, Admin Company diundang (A-176),
 * jabatan & akun dasar nonaktif dibuat (A-407), aturan dasar approval aktif (A-405).
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

            // A-407: 6 akun dasar nonaktif tanpa password + peta jabatan dasar.
            $dasar = User::query()->where('email', 'like', '%@prv.wms')->with('position')->get();
            $this->assertSame(
                ['auditor-internal@prv.wms', 'kepala-gudang@prv.wms', 'manajemen@prv.wms', 'pemohon-internal@prv.wms', 'penindak-lanjut-pr@prv.wms', 'staf-gudang@prv.wms'],
                $dasar->pluck('email')->sort()->values()->all(),
            );
            $this->assertTrue($dasar->every(fn (User $u) => ! $u->is_active && $u->password === null && $u->position !== null));
            $this->assertSame(0, UserInvitation::query()->whereIn('user_id', $dasar->pluck('id'))->count(), 'Akun dasar tidak diundang.');
            $kepala = $dasar->firstWhere('email', 'kepala-gudang@prv.wms');
            $this->assertTrue($kepala->hasRoleCode('warehouse_head'));
            $this->assertSame('MGT', Position::query()->find($kepala->position->reports_to_position_id)->code);

            // A-405: aturan dasar aktif untuk setiap jenis kecuali lapis minimum.
            $harap = collect(app(ApprovalRegistry::class)->types())
                ->reject(fn (ApprovalDocumentType $t) => in_array($t, InstallBasicApprovalRules::DILEWATI, true))
                ->map->value->sort()->values()->all();
            $aturan = ApprovalRule::query()->get();
            $this->assertSame($harap, $aturan->map(fn ($r) => $r->document_type->value)->sort()->values()->all());
            $this->assertTrue($aturan->every(fn ($r) => $r->is_basic && $r->is_active && $r->priority === 900));

            // Diulang (mis. lanjut setelah gagal) tidak menggandakan.
            $this->assertSame([], app(InstallBasicOrganization::class)->handle('PRV'));
            $this->assertSame([], app(InstallBasicApprovalRules::class)->handle());
        });

        $this->assertTrue(PlatformAuditLog::query()->where('description', 'Company siap dipakai')->where('subject_id', $company->id)->exists());

        // Detail & beranda menampilkan company; provisioning ulang ditolak.
        $this->actingAs($admin, 'platform')->get($this->centralUrl('admin/companies/'.$company->id))->assertOk()->assertSee('prv.'.config('tenancy.central_domains.0'));
        $this->actingAs($admin, 'platform')->get($this->centralUrl('admin'))->assertOk()->assertSee('PT Provisioning');
        $this->actingAs($admin, 'platform')->post($this->centralUrl('admin/companies/'.$company->id.'/provision'))->assertSessionHasErrors('platform');
    }

    /** TC-PLT-15 (A-408) — satu perintah membuat contoh company yang langsung bisa dimasuki; diulang aman. */
    #[Test]
    public function tc_plt_15_perintah_contoh_company_siap_login(): void
    {
        PlatformUser::create(['email' => 'prv-admin@wms.test', 'name' => 'Super Admin Uji', 'password' => Hash::make('Rahasia#2026!')]);

        // Seperti di cPanel: database company dibuat manual lebih dulu, sehingga
        // langkah "buat database" gagal dan perintah melanjutkan penyiapan.
        DB::connection('central')->statement('CREATE DATABASE IF NOT EXISTS `'.config('tenancy.database.prefix').'prv`');

        $this->artisan('companies:create-sample', [
            '--code' => 'prv', '--subdomain' => 'prv', '--email' => 'rina@prv.test', '--password' => 'pendek',
        ])->assertFailed();

        // Company sudah aktif, password pendek ditolak → ulangi dengan password sah.
        $this->artisan('companies:create-sample', [
            '--code' => 'prv', '--subdomain' => 'prv', '--email' => 'rina@prv.test', '--password' => 'Beton-Palu-2026',
        ])->expectsOutputToContain('/login')->assertSuccessful();

        $company = Company::query()->where('code', self::KODE)->sole();
        $this->assertSame(CompanyStatus::Active, $company->status);

        $company->run(function (): void {
            $rina = User::query()->where('email', 'rina@prv.test')->sole();
            $this->assertTrue($rina->canSignIn());
            $this->assertTrue(password_verify('Beton-Palu-2026', (string) $rina->password));
            $this->assertTrue(ApprovalRule::query()->where('is_basic', true)->exists(), 'Aturan dasar ikut (A-405).');
            $this->assertSame(6, User::query()->where('email', 'like', '%@prv.wms')->count(), 'Akun dasar ikut (A-407).');
        });

        // Diulang: tidak membuat company baru dan tidak mengganti password yang sudah dipakai.
        $this->artisan('companies:create-sample', ['--code' => 'prv', '--password' => 'Ambil-Alih-2026'])
            ->expectsOutputToContain('tidak diubah')->assertSuccessful();
        $this->assertSame(1, Company::query()->where('code', self::KODE)->count());
        $company->run(fn () => $this->assertTrue(password_verify('Beton-Palu-2026', (string) User::query()->where('email', 'rina@prv.test')->value('password'))));
    }

    #[Test]
    public function tc_plt_14_super_admin_menyerahkan_akun_admin_company_pertama(): void
    {
        $admin = PlatformUser::create(['email' => 'prv-admin@wms.test', 'name' => 'Super Admin Uji', 'password' => Hash::make('Rahasia#2026!')]);
        $plan = Plan::query()->where('code', 'uji')->firstOrFail();

        $this->actingAs($admin, 'platform')->post($this->centralUrl('admin/companies'), [
            'code' => 'prv', 'name' => 'PT Serah Terima', 'subdomain' => 'prv', 'timezone' => 'Asia/Jakarta',
            'plan_id' => $plan->id, 'admin_name' => 'Rina Admin', 'admin_email' => 'rina@prv.test',
        ]);

        $company = Company::query()->where('code', self::KODE)->firstOrFail();
        $this->assertSame(CompanyStatus::Active, $company->status, (string) $company->provisioningError());

        $tautanLama = $company->run(fn () => UserInvitation::query()->latest('id')->firstOrFail()->url());

        $this->assertStringStartsWith('http://prv.', (string) $tautanLama, 'A-333: tautan memakai host company, lengkap dengan port.');

        // A-335: kartu penyerahan tampil selama company belum dipakai.
        $this->actingAs($admin, 'platform')->get($this->centralUrl('admin/companies/'.$company->id))
            ->assertOk()
            ->assertSee('Serahkan akun Admin Company')
            ->assertSee($tautanLama);

        // Kirim ulang membuat tautan baru dan mematikan yang lama.
        $this->actingAs($admin, 'platform')
            ->post($this->centralUrl('admin/companies/'.$company->id.'/admin-invite'))
            ->assertRedirect();

        $tautanBaru = $company->run(fn () => UserInvitation::query()->pending()->latest('id')->firstOrFail()->url());

        $this->assertNotSame($tautanLama, $tautanBaru);

        // Password pendek ditolak.
        $this->actingAs($admin, 'platform')
            ->post($this->centralUrl('admin/companies/'.$company->id.'/admin-password'), ['password' => 'pendek'])
            ->assertSessionHasErrors('password');

        $this->actingAs($admin, 'platform')
            ->post($this->centralUrl('admin/companies/'.$company->id.'/admin-password'), ['password' => 'Beton-Palu-2026'])
            ->assertRedirect();

        $company->run(function (): void {
            $rina = User::query()->where('email', 'rina@prv.test')->sole();

            $this->assertTrue($rina->canSignIn());
            $this->assertTrue(password_verify('Beton-Palu-2026', (string) $rina->password));
            $this->assertSame(0, UserInvitation::query()->pending()->count(), 'Undangan tertunda dibatalkan.');
        });

        // Kartu hilang setelah tidak ada undangan berjalan.
        $this->actingAs($admin, 'platform')->get($this->centralUrl('admin/companies/'.$company->id))
            ->assertOk()
            ->assertDontSee('Serahkan akun Admin Company');

        // TC-PLT-14b (BR-SUB-04): setelah akun bisa masuk, kedua aksi POST ditolak
        // walau dikirim langsung — Super Admin tidak bisa mengambil alih akun.
        $this->actingAs($admin, 'platform')
            ->post($this->centralUrl('admin/companies/'.$company->id.'/admin-password'), ['password' => 'Ambil-Alih-2026'])
            ->assertForbidden();

        $this->actingAs($admin, 'platform')
            ->post($this->centralUrl('admin/companies/'.$company->id.'/admin-invite'))
            ->assertForbidden();

        $company->run(function (): void {
            $rina = User::query()->where('email', 'rina@prv.test')->sole();

            $this->assertTrue(password_verify('Beton-Palu-2026', (string) $rina->password), 'Password tidak berubah.');
            $this->assertSame(0, UserInvitation::query()->pending()->count(), 'Tidak ada undangan baru.');
        });
    }
}

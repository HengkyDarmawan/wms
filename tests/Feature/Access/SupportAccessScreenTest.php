<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Livewire\SupportAccessManager;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\Platform\Models\SupportAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * Layar akses dukungan — docs/wms/10-access.md §6.7 (A-27, BR-SUB-04).
 */
class SupportAccessScreenTest extends TenantTestCase
{
    #[Test]
    public function tc_acc_ui_50_hanya_admin_company_yang_bisa_membuka_layar(): void
    {
        $admin = $this->makeUser('company_admin');
        $kepala = $this->makeUser('warehouse_head', ScopeType::Warehouse, 1);

        $this->actingAs($admin)->get($this->tenantUrl('/settings/support-access'))->assertOk();
        $this->actingAs($kepala)->get($this->tenantUrl('/settings/support-access'))->assertForbidden();
    }

    #[Test]
    public function tc_acc_ui_51_memberi_akses_dukungan_membuat_izin_yang_berlaku(): void
    {
        $admin = $this->makeUser('company_admin');
        $superAdmin = $this->superAdmin();

        $this->assertFalse($superAdmin->hasActiveSupportAccess((int) $this->company->getTenantKey()));

        $zona = $this->company->timezone;

        Livewire::actingAs($admin)->test(SupportAccessManager::class)
            ->set('platformUserId', $superAdmin->id)
            ->set('startsAt', now($zona)->format('Y-m-d\TH:i'))
            ->set('endsAt', now($zona)->addDays(2)->format('Y-m-d\TH:i'))
            ->set('reason', 'Menelusuri selisih stok gudang Cakung')
            ->call('beri')
            ->assertHasNoErrors()
            ->assertSee('Menelusuri selisih stok gudang Cakung');

        $akses = SupportAccess::query()->where('company_id', $this->company->getTenantKey())->firstOrFail();

        $this->assertSame($admin->id, (int) $akses->granted_by_tenant_user_id);
        $this->assertTrue($superAdmin->hasActiveSupportAccess((int) $this->company->getTenantKey()));
    }

    #[Test]
    public function tc_acc_ui_52_alasan_wajib_dan_periode_dibatasi(): void
    {
        $admin = $this->makeUser('company_admin');
        $superAdmin = $this->superAdmin();
        $zona = $this->company->timezone;

        // Alasan kosong (BR-GEN-11)
        Livewire::actingAs($admin)->test(SupportAccessManager::class)
            ->set('platformUserId', $superAdmin->id)
            ->set('reason', '')
            ->call('beri')
            ->assertHasErrors('reason');

        // Selesai sebelum mulai
        Livewire::actingAs($admin)->test(SupportAccessManager::class)
            ->set('platformUserId', $superAdmin->id)
            ->set('startsAt', now($zona)->format('Y-m-d\TH:i'))
            ->set('endsAt', now($zona)->subHour()->format('Y-m-d\TH:i'))
            ->set('reason', 'Periode terbalik')
            ->call('beri')
            ->assertHasErrors('endsAt');

        // Melebihi batas maksimum (default 7 hari)
        $maks = (int) config('access.support_access.max_days');

        Livewire::actingAs($admin)->test(SupportAccessManager::class)
            ->set('platformUserId', $superAdmin->id)
            ->set('startsAt', now($zona)->format('Y-m-d\TH:i'))
            ->set('endsAt', now($zona)->addDays($maks + 3)->format('Y-m-d\TH:i'))
            ->set('reason', 'Terlalu lama')
            ->call('beri')
            ->assertHasErrors('reason');

        $this->assertSame(0, SupportAccess::query()->where('company_id', $this->company->getTenantKey())->count());
    }

    #[Test]
    public function tc_acc_ui_53_mencabut_akses_menutup_kembali_data_company(): void
    {
        $admin = $this->makeUser('company_admin');
        $superAdmin = $this->superAdmin();

        $akses = SupportAccess::create([
            'company_id' => $this->company->getTenantKey(),
            'platform_user_id' => $superAdmin->id,
            'granted_by_tenant_user_id' => $admin->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'reason' => 'Pemeriksaan data',
        ]);

        $this->assertTrue($superAdmin->hasActiveSupportAccess((int) $this->company->getTenantKey()));

        Livewire::actingAs($admin)->test(SupportAccessManager::class)
            ->call('cabut', $akses->id)
            ->assertHasNoErrors();

        $this->assertNotNull($akses->refresh()->revoked_at);
        $this->assertFalse($superAdmin->hasActiveSupportAccess((int) $this->company->getTenantKey()));

        // Riwayat tetap tampil sebagai "Dicabut" (P-03, jejak audit).
        Livewire::actingAs($admin)->test(SupportAccessManager::class)
            ->assertSee('Pemeriksaan data')
            ->assertSee('Dicabut');
    }

    #[Test]
    public function tc_acc_ui_54_akses_company_lain_tidak_bisa_dicabut_dari_sini(): void
    {
        $admin = $this->makeUser('company_admin');
        $superAdmin = $this->superAdmin();

        // Baris company disisipkan langsung agar tidak memicu pembuatan database tenant.
        $companyLainId = DB::connection('central')->table('companies')->insertGetId([
            'code' => 'LAIN',
            'name' => 'PT Company Lain',
            'subdomain' => 'lain',
            'db_name' => 'wms_tenant_lain_tidak_dipakai',
            'timezone' => 'Asia/Jakarta',
            'status' => 'provisioning',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $aksesLain = SupportAccess::create([
            'company_id' => $companyLainId,
            'platform_user_id' => $superAdmin->id,
            'granted_by_tenant_user_id' => 1,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'reason' => 'Akses company lain',
        ]);

        Livewire::actingAs($admin)->test(SupportAccessManager::class)
            ->call('cabut', $aksesLain->id)
            ->assertHasNoErrors()
            ->assertDontSee('Akses company lain');

        $this->assertNull(
            $aksesLain->refresh()->revoked_at,
            'Izin milik company lain tidak boleh tersentuh dari layar ini.',
        );
    }

    private function superAdmin(): PlatformUser
    {
        return PlatformUser::create([
            'name' => 'Super Admin Uji',
            'email' => 'sa.layar@wms.test',
            'password' => Hash::make('Rahasia#2026!'),
        ]);
    }
}

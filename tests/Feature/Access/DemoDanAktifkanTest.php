<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Livewire\UserDetail;
use App\Domain\Master\Livewire\CompanySettingsForm;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\TenantTestCase;

/**
 * TC-ACC-60–62 — Coba demo (A-418), aktifkan akun dalam satu tombol (A-420),
 * dan nama perusahaan pemilik gudang di Pengaturan company (A-419).
 */
class DemoDanAktifkanTest extends TenantTestCase
{
    #[Test]
    public function tc_acc_60_coba_demo_hanya_bila_setelan_menyala(): void
    {
        $kepala = $this->makeUser('warehouse_head', attributes: ['name' => 'Budi Kepala']);
        $this->makeUser('warehouse_staff', attributes: ['name' => 'Staf Nonaktif', 'is_active' => false]);

        // Setelan mati (bawaan): tanpa kartu, POST 404.
        config(['access.demo_login' => false]);
        $this->get($this->tenantUrl('login'))->assertOk()->assertDontSeeHtml('data-coba-demo');
        $this->post($this->tenantUrl('demo-login/warehouse_head'))->assertNotFound();

        // Menyala: kartu hanya untuk peran yang punya pengguna aktif.
        config(['access.demo_login' => true]);
        $this->get($this->tenantUrl('login'))->assertOk()->assertSeeHtml('data-coba-demo')
            ->assertSee('Budi Kepala')->assertSeeHtml('data-demo-peran="warehouse_head"')->assertDontSee('Staf Nonaktif');
        $this->get($this->tenantUrl('selamat-datang'))->assertOk()->assertSeeHtml('data-tombol-masuk')->assertSeeHtml('data-coba-demo');

        $this->post($this->tenantUrl('demo-login/warehouse_head'))->assertRedirect();
        $this->assertAuthenticatedAs($kepala);
        $this->assertTrue(Activity::query()->where('description', 'Masuk demo sebagai Kepala Gudang')->exists());
        $this->get($this->tenantUrl(''))->assertOk()->assertSeeHtml('data-mode-demo');

        // Peran tak dikenal / tanpa pengguna aktif → 404.
        auth()->logout();
        $this->post($this->tenantUrl('demo-login/client_user'))->assertNotFound();
    }

    #[Test]
    public function tc_acc_61_akun_dasar_aktif_dan_berpassword_dalam_satu_tombol(): void
    {
        $admin = $this->makeUser('company_admin');
        $dasar = $this->makeUser('warehouse_head', attributes: ['is_active' => false, 'password' => null, 'password_changed_at' => null]);

        Livewire::actingAs($admin)->test(UserDetail::class, ['userId' => $dasar->id])
            ->assertSeeHtml('data-aktifkan-sekaligus')
            ->call('aktifkanDanBuatkanPassword')
            ->assertSet('ruleError', '')
            ->assertNotSet('passwordDibuat', '');

        $dasar->refresh();
        $this->assertTrue($dasar->is_active);
        $this->assertNotNull($dasar->password);

        // Akun yang sudah bisa masuk tidak ditawari dan ditolak (A-351).
        $lama = $this->makeUser('warehouse_staff', attributes: ['is_active' => false]);
        Livewire::actingAs($admin)->test(UserDetail::class, ['userId' => $lama->id])
            ->assertDontSeeHtml('data-aktifkan-sekaligus')
            ->call('aktifkanDanBuatkanPassword')
            ->assertNotSet('ruleError', '');
        $this->assertFalse($lama->refresh()->is_active);
        $this->assertTrue(Hash::check('Rahasia#2026!', (string) $lama->password));
    }

    #[Test]
    public function tc_acc_62_nama_perusahaan_diubah_admin(): void
    {
        $admin = $this->makeUser('company_admin');

        Livewire::actingAs($admin)->test(CompanySettingsForm::class)
            ->assertSeeHtml('data-nama-perusahaan')
            ->set('nama', 'PT')->call('simpan')->assertHasErrors('nama')
            ->set('nama', 'PT Gudang Sentosa')->call('simpan')->assertHasNoErrors();

        $this->assertSame('PT Gudang Sentosa', tenant()->refresh()->name);
        $this->assertTrue(Activity::query()->where('description', 'Nama perusahaan diubah')->exists());
        $this->actingAs($admin)->get($this->tenantUrl(''))->assertOk()->assertSee('PT Gudang Sentosa');

        Livewire::actingAs($this->makeUser('management'))->test(CompanySettingsForm::class)
            ->set('nama', 'Diubah Staf')->call('simpan')->assertForbidden();
    }
}

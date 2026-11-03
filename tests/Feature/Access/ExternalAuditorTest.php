<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Livewire\UserForm;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\User;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-F2-10 s.d. TC-F2-12 — auditor eksternal berbatas gudang & tanggal
 * (BR-ACC-07, A-425) dan BR-ACC-01 diperiksa per request.
 */
class ExternalAuditorTest extends TenantTestCase
{
    private int $gudang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gudang = (int) app(SaveWarehouse::class)->handle(null, [
            'code' => 'AUD-A',
            'name' => 'Gudang Audit',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ])->id;
    }

    #[Test]
    public function tc_f2_10_peran_auditor_wajib_gudang_dan_tanggal_akhir(): void
    {
        $admin = $this->makeUser('company_admin');

        $komponen = Livewire::actingAs($admin)->test(UserForm::class)
            ->set('name', 'Auditor KAP')
            ->set('email', 'auditor.kap@demo.wms.test')
            ->call('pilihPeran', Role::findByCode('external_auditor')->id)
            ->call('save')
            ->assertHasErrors(['gudangDipilih', 'aksesSampai']);

        $this->assertSame('Isi tanggal akhir akses auditor.', $komponen->errors()->first('aksesSampai'));

        $komponen->set('gudangDipilih', [(string) $this->gudang])
            ->set('aksesSampai', now()->addDays(91)->toDateString())
            ->call('save')
            ->assertHasErrors('aksesSampai');

        $komponen->set('aksesSampai', now()->subDay()->toDateString())->call('save')->assertHasErrors('aksesSampai');

        $komponen->set('aksesSampai', now()->addDays(30)->toDateString())->call('save')->assertHasNoErrors();

        $auditor = User::query()->where('email', 'auditor.kap@demo.wms.test')->firstOrFail();
        $a = $auditor->roleAssignments()->sole();
        $this->assertSame(ScopeType::Warehouse, $a->scope_type);
        $this->assertSame($this->gudang, (int) $a->scope_id);
        $this->assertSame(now()->toDateString(), $a->valid_from?->toDateString());
        $this->assertSame(now()->addDays(30)->toDateString(), $a->valid_until?->toDateString());

        // Mode ubah memuat ulang tanggalnya di pertanyaan peran, bukan di Pengaturan lanjutan.
        Livewire::actingAs($admin)->test(UserForm::class, ['userId' => $auditor->id])
            ->assertSet('aksesSampai', now()->addDays(30)->toDateString())
            ->assertSet('gudangDipilih', [(string) $this->gudang])
            ->assertSet('lanjutanTerbuka', false)
            ->assertSet('assignments', []);
    }

    #[Test]
    public function tc_f2_11_auditor_lewat_tanggal_tidak_bisa_login(): void
    {
        $auditor = $this->makeUser('external_auditor', ScopeType::Warehouse, $this->gudang);
        RoleAssignment::query()->where('user_id', $auditor->id)->update(['valid_until' => now()->subDay()->toDateString()]);

        $this->post($this->tenantUrl('/login'), ['email' => $auditor->email, 'password' => 'Rahasia#2026!'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    #[Test]
    public function tc_f2_12_sesi_berjalan_dikeluarkan_setelah_tanggal_akhir(): void
    {
        $auditor = $this->makeUser('external_auditor', ScopeType::Warehouse, $this->gudang);
        RoleAssignment::query()->where('user_id', $auditor->id)->update(['valid_until' => now()->toDateString()]);

        $this->actingAs($auditor)->get($this->tenantUrl('/'))->assertOk();

        $this->travel(1)->days();
        $this->actingAs($auditor->fresh())->get($this->tenantUrl('/'))
            ->assertRedirect($this->tenantUrl('/login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}

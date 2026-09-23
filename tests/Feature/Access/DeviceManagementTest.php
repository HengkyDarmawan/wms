<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Livewire\DeviceList;
use App\Domain\Access\Models\Device;
use App\Domain\Access\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * Layar perangkat — docs/wms/10-access.md §6.6 (Blueprint §11, P-03).
 */
class DeviceManagementTest extends TenantTestCase
{
    #[Test]
    public function tc_acc_ui_40_user_biasa_hanya_melihat_perangkatnya_sendiri(): void
    {
        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1);  // punya device.manage saja
        $orangLain = $this->makeUser('driver');

        $this->buatPerangkat($staf, 'HP Dedi', 'android');
        $this->buatPerangkat($orangLain, 'HP Gani', 'android');

        Livewire::actingAs($staf)->test(DeviceList::class)
            ->assertSet('search', '')
            ->assertSee('HP Dedi')
            ->assertDontSee('HP Gani');
    }

    #[Test]
    public function tc_acc_ui_41_pemegang_device_view_melihat_semua_perangkat(): void
    {
        $kepala = $this->makeUser('warehouse_head', ScopeType::Warehouse, 1);  // punya device.view
        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1, ['name' => 'Dedi Staf']);

        $this->buatPerangkat($staf, 'Tablet Gudang', 'android');

        Livewire::actingAs($kepala)->test(DeviceList::class)
            ->assertSee('Tablet Gudang')
            ->assertSee('Dedi Staf');
    }

    #[Test]
    public function tc_acc_ui_42_mencabut_perangkat_tidak_menghapus_datanya(): void
    {
        $admin = $this->makeUser('company_admin');
        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1);
        $perangkat = $this->buatPerangkat($staf, 'HP Lama', 'android');

        Livewire::actingAs($admin)->test(DeviceList::class)
            ->call('cabut', $perangkat->id);

        $this->assertFalse($perangkat->refresh()->is_active);
        $this->assertDatabaseHas('devices', ['id' => $perangkat->id], 'tenant');

        Livewire::actingAs($admin)->test(DeviceList::class)
            ->call('aktifkan', $perangkat->id);

        $this->assertTrue($perangkat->refresh()->is_active);
    }

    #[Test]
    public function tc_acc_ui_43_daftar_bisa_dicari_dan_difilter_status(): void
    {
        $admin = $this->makeUser('company_admin');
        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1);

        $aktif = $this->buatPerangkat($staf, 'HP Aktif', 'android');
        $dicabut = $this->buatPerangkat($staf, 'HP Dicabut', 'ios');
        $dicabut->forceFill(['is_active' => false])->save();

        $komponen = Livewire::actingAs($admin)->test(DeviceList::class)
            ->assertSee('HP Aktif')
            ->assertSee('HP Dicabut');

        $komponen->set('statusFilter', 'aktif')
            ->assertSee('HP Aktif')
            ->assertDontSee('HP Dicabut');

        $komponen->set('statusFilter', 'dicabut')
            ->assertSee('HP Dicabut')
            ->assertDontSee('HP Aktif');

        $komponen->set('statusFilter', '')
            ->set('search', 'Aktif')
            ->assertSee('HP Aktif')
            ->assertDontSee('HP Dicabut');

        $this->assertNotNull($aktif->id);
    }

    #[Test]
    public function tc_acc_ui_44_user_tidak_bisa_mencabut_perangkat_orang_lain(): void
    {
        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1);
        $orangLain = $this->makeUser('driver');
        $perangkatOrangLain = $this->buatPerangkat($orangLain, 'HP Gani', 'android');

        Livewire::actingAs($staf)->test(DeviceList::class)
            ->call('cabut', $perangkatOrangLain->id)
            ->assertForbidden();

        $this->assertTrue($perangkatOrangLain->refresh()->is_active);
    }

    #[Test]
    public function tc_acc_ui_45_user_boleh_mencabut_perangkatnya_sendiri(): void
    {
        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1);
        $perangkat = $this->buatPerangkat($staf, 'HP Sendiri', 'android');

        Livewire::actingAs($staf)->test(DeviceList::class)
            ->call('cabut', $perangkat->id)
            ->assertHasNoErrors();

        $this->assertFalse($perangkat->refresh()->is_active);
    }

    private function buatPerangkat(User $user, string $nama, string $platform): Device
    {
        return Device::create([
            'user_id' => $user->id,
            'device_uid' => 'uid-'.uniqid(),
            'name' => $nama,
            'platform' => $platform,
            'last_seen_at' => now(),
            'is_active' => true,
        ]);
    }
}

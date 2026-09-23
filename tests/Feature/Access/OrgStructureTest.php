<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Livewire\OrgTree;
use App\Domain\Access\Models\OrgUnit;
use App\Domain\Access\Models\Position;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * Layar struktur organisasi — docs/wms/10-access.md §6.5 (D-16, Blueprint §8.1).
 */
class OrgStructureTest extends TenantTestCase
{
    #[Test]
    public function tc_acc_ui_30_hanya_yang_berhak_membuka_struktur_organisasi(): void
    {
        $admin = $this->makeUser('company_admin');
        $kepala = $this->makeUser('warehouse_head', ScopeType::Warehouse, 1);  // punya org.view
        $driver = $this->makeUser('driver');                                    // tidak punya org.view

        $this->actingAs($admin)->get($this->tenantUrl('/org'))->assertOk();
        $this->actingAs($kepala)->get($this->tenantUrl('/org'))->assertOk();
        $this->actingAs($driver)->get($this->tenantUrl('/org'))->assertForbidden();
    }

    #[Test]
    public function tc_acc_ui_31_membuat_unit_dan_sub_unit(): void
    {
        $admin = $this->makeUser('company_admin');

        $komponen = Livewire::actingAs($admin)->test(OrgTree::class)
            ->call('unitBaru')
            ->set('unitCode', 'dir')
            ->set('unitName', 'Direksi')
            ->set('unitParentId', null)
            ->call('simpanUnit')
            ->assertHasNoErrors();

        $direksi = OrgUnit::where('code', 'DIR')->first();
        $this->assertNotNull($direksi, 'Kode unit dinormalkan menjadi huruf besar.');
        $this->assertSame('Direksi', $direksi->name);

        $komponen->call('unitBaru', $direksi->id)
            ->set('unitCode', 'OPS')
            ->set('unitName', 'Operasional')
            ->call('simpanUnit')
            ->assertHasNoErrors()
            ->assertSee('Operasional');

        $operasional = OrgUnit::where('code', 'OPS')->firstOrFail();
        $this->assertSame($direksi->id, $operasional->parent_id);
    }

    #[Test]
    public function tc_acc_ui_31b_kode_unit_tidak_boleh_ganda_dan_nama_wajib(): void
    {
        $admin = $this->makeUser('company_admin');
        OrgUnit::create(['code' => 'DIR', 'name' => 'Direksi']);

        Livewire::actingAs($admin)->test(OrgTree::class)
            ->call('unitBaru')
            ->set('unitCode', 'DIR')
            ->set('unitName', 'Direksi Lain')
            ->call('simpanUnit')
            ->assertHasErrors('unitName');

        Livewire::actingAs($admin)->test(OrgTree::class)
            ->call('unitBaru')
            ->set('unitCode', 'BARU')
            ->set('unitName', '')
            ->call('simpanUnit')
            ->assertHasErrors('unitName');

        $this->assertSame(1, OrgUnit::count());
    }

    #[Test]
    public function tc_acc_ui_32_induk_tidak_boleh_dirinya_sendiri_atau_turunannya(): void
    {
        $admin = $this->makeUser('company_admin');

        $induk = OrgUnit::create(['code' => 'DIR', 'name' => 'Direksi']);
        $anak = OrgUnit::create(['code' => 'OPS', 'name' => 'Operasional', 'parent_id' => $induk->id]);

        // Diri sendiri
        Livewire::actingAs($admin)->test(OrgTree::class)
            ->call('editUnit', $induk->id)
            ->set('unitParentId', $induk->id)
            ->call('simpanUnit')
            ->assertHasErrors('unitName');

        // Turunannya
        Livewire::actingAs($admin)->test(OrgTree::class)
            ->call('editUnit', $induk->id)
            ->set('unitParentId', $anak->id)
            ->call('simpanUnit')
            ->assertHasErrors('unitName');

        $this->assertNull($induk->refresh()->parent_id);
    }

    #[Test]
    public function tc_acc_ui_33_unit_yang_masih_dipakai_tidak_bisa_dinonaktifkan(): void
    {
        $admin = $this->makeUser('company_admin');

        $induk = OrgUnit::create(['code' => 'DIR', 'name' => 'Direksi']);
        $anak = OrgUnit::create(['code' => 'OPS', 'name' => 'Operasional', 'parent_id' => $induk->id]);

        $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1, ['org_unit_id' => $anak->id]);

        // Punya sub-unit aktif
        Livewire::actingAs($admin)->test(OrgTree::class)->call('nonaktifkanUnit', $induk->id);
        $this->assertTrue($induk->refresh()->is_active);

        // Punya user aktif
        Livewire::actingAs($admin)->test(OrgTree::class)->call('nonaktifkanUnit', $anak->id);
        $this->assertTrue($anak->refresh()->is_active);
    }

    #[Test]
    public function tc_acc_ui_33b_unit_kosong_bisa_dinonaktifkan_dan_diaktifkan_lagi(): void
    {
        $admin = $this->makeUser('company_admin');
        $unit = OrgUnit::create(['code' => 'KOSONG', 'name' => 'Unit Kosong']);
        $jabatan = Position::create([
            'org_unit_id' => $unit->id, 'code' => 'STAF_KOSONG', 'name' => 'Staf', 'level' => 3,
        ]);

        Livewire::actingAs($admin)->test(OrgTree::class)->call('nonaktifkanUnit', $unit->id);

        $this->assertFalse($unit->refresh()->is_active);
        $this->assertFalse($jabatan->refresh()->is_active, 'Jabatan di unit ikut dinonaktifkan.');
        $this->assertDatabaseHas('org_units', ['id' => $unit->id], 'tenant');

        Livewire::actingAs($admin)->test(OrgTree::class)->call('aktifkanUnit', $unit->id);
        $this->assertTrue($unit->refresh()->is_active);
    }

    #[Test]
    public function tc_acc_ui_34_menambah_dan_mengubah_jabatan(): void
    {
        $admin = $this->makeUser('company_admin');
        $unit = OrgUnit::create(['code' => 'OPS', 'name' => 'Operasional']);

        $komponen = Livewire::actingAs($admin)->test(OrgTree::class)
            ->call('pilihUnit', $unit->id)
            ->call('jabatanBaru')
            ->set('positionCode', 'ka gudang')
            ->set('positionName', 'Kepala Gudang')
            ->set('positionLevel', 2)
            ->call('simpanJabatan')
            ->assertHasNoErrors();

        $jabatan = Position::where('code', 'KA_GUDANG')->first();
        $this->assertNotNull($jabatan);
        $this->assertSame($unit->id, $jabatan->org_unit_id);
        $this->assertSame(2, $jabatan->level);

        $komponen->call('editJabatan', $jabatan->id)
            ->set('positionName', 'Kepala Gudang Utama')
            ->set('positionLevel', 1)
            ->call('simpanJabatan')
            ->assertHasNoErrors();

        $this->assertSame('Kepala Gudang Utama', $jabatan->refresh()->name);
        $this->assertSame(1, $jabatan->level);
    }

    #[Test]
    public function tc_acc_ui_34b_level_jabatan_minimal_satu(): void
    {
        $admin = $this->makeUser('company_admin');
        $unit = OrgUnit::create(['code' => 'OPS', 'name' => 'Operasional']);

        Livewire::actingAs($admin)->test(OrgTree::class)
            ->call('pilihUnit', $unit->id)
            ->call('jabatanBaru')
            ->set('positionCode', 'X')
            ->set('positionName', 'Jabatan')
            ->set('positionLevel', 0)
            ->call('simpanJabatan')
            ->assertHasErrors('positionLevel');

        $this->assertSame(0, Position::count());
    }

    #[Test]
    public function tc_acc_ui_35_jabatan_yang_dipakai_user_aktif_tidak_bisa_dinonaktifkan(): void
    {
        $admin = $this->makeUser('company_admin');
        $unit = OrgUnit::create(['code' => 'OPS', 'name' => 'Operasional']);
        $jabatan = Position::create([
            'org_unit_id' => $unit->id, 'code' => 'STAF', 'name' => 'Staf Gudang', 'level' => 3,
        ]);

        $pemakai = $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1, [
            'org_unit_id' => $unit->id,
            'position_id' => $jabatan->id,
        ]);

        Livewire::actingAs($admin)->test(OrgTree::class)
            ->call('pilihUnit', $unit->id)
            ->call('nonaktifkanJabatan', $jabatan->id);

        $this->assertTrue($jabatan->refresh()->is_active);

        // Setelah user dipindah, jabatan bisa dinonaktifkan.
        $pemakai->forceFill(['position_id' => null])->save();

        Livewire::actingAs($admin)->test(OrgTree::class)
            ->call('pilihUnit', $unit->id)
            ->call('nonaktifkanJabatan', $jabatan->id);

        $this->assertFalse($jabatan->refresh()->is_active);
    }

    #[Test]
    public function tc_acc_ui_36_daftar_anggota_menampilkan_jabatan_dan_atasan(): void
    {
        $admin = $this->makeUser('company_admin');
        $unit = OrgUnit::create(['code' => 'OPS', 'name' => 'Operasional']);
        $jabatan = Position::create([
            'org_unit_id' => $unit->id, 'code' => 'STAF', 'name' => 'Staf Gudang', 'level' => 3,
        ]);

        $atasan = $this->makeUser('warehouse_head', ScopeType::Warehouse, 1, [
            'name' => 'Andi Kepala', 'org_unit_id' => $unit->id,
        ]);

        $this->makeUser('warehouse_staff', ScopeType::Warehouse, 1, [
            'name' => 'Dedi Staf',
            'org_unit_id' => $unit->id,
            'position_id' => $jabatan->id,
            'manager_id' => $atasan->id,
        ]);

        Livewire::actingAs($admin)->test(OrgTree::class)
            ->call('pilihUnit', $unit->id)
            ->assertSee('Dedi Staf')
            ->assertSee('Staf Gudang')
            ->assertSee('Andi Kepala')
            ->assertSee('belum diisi');   // atasan Andi sendiri belum diisi
    }

    #[Test]
    public function tc_acc_ui_37_pemegang_org_view_tidak_bisa_mengubah(): void
    {
        $kepala = $this->makeUser('warehouse_head', ScopeType::Warehouse, 1);

        Livewire::actingAs($kepala)->test(OrgTree::class)
            ->call('unitBaru')
            ->assertForbidden();
    }
}

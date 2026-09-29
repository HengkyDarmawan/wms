<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Livewire\OrgTree;
use App\Domain\Access\Livewire\UserForm;
use App\Domain\Access\Models\OrgUnit;
use App\Domain\Access\Models\Position;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-ACC-48–TC-ACC-50c — P2 form pengguna: jabatan mengikuti unit (A-385),
 * Atasan langsung berkelompok Unit ini → Unit induk → Unit lain & dicari ke
 * server (A-386), lingkaran atasan ditolak di pengguna dan jabatan (A-387).
 */
class UserFormP2Test extends TenantTestCase
{
    private User $admin;

    private OrgUnit $direksi;

    private OrgUnit $operasional;

    private OrgUnit $gudang;

    private OrgUnit $keuangan;

    private Position $kepala;

    private Position $staf;

    private Position $akuntan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('company_admin', attributes: ['name' => 'Zaki Admin']);
        $this->direksi = OrgUnit::create(['code' => 'DIR', 'name' => 'Direksi']);
        $this->operasional = OrgUnit::create(['code' => 'OPS', 'name' => 'Operasional', 'parent_id' => $this->direksi->id]);
        $this->gudang = OrgUnit::create(['code' => 'GDG', 'name' => 'Gudang', 'parent_id' => $this->operasional->id]);
        $this->keuangan = OrgUnit::create(['code' => 'KEU', 'name' => 'Keuangan']);

        $this->kepala = Position::create(['org_unit_id' => $this->gudang->id, 'code' => 'KAG', 'name' => 'Kepala Gudang', 'level' => 1, 'is_active' => true]);
        $this->staf = Position::create(['org_unit_id' => $this->gudang->id, 'code' => 'STG', 'name' => 'Staf Gudang', 'level' => 1, 'is_active' => true]);
        Position::create(['org_unit_id' => $this->gudang->id, 'code' => 'OLD', 'name' => 'Jabatan Lama', 'level' => 1, 'is_active' => false]);
        $this->akuntan = Position::create(['org_unit_id' => $this->keuangan->id, 'code' => 'AKT', 'name' => 'Akuntan', 'level' => 1, 'is_active' => true]);
    }

    private function form(?User $untuk = null)
    {
        return Livewire::actingAs($this->admin)->test(UserForm::class, $untuk === null ? [] : ['userId' => $untuk->id]);
    }

    #[Test]
    public function tc_acc_48_jabatan_mengikuti_unit(): void
    {
        $target = $this->makeUser('warehouse_staff', attributes: ['name' => 'Target']);
        $form = $this->form($target)->set('orgUnitId', $this->gudang->id);

        $this->assertSame(['Kepala Gudang', 'Staf Gudang'], array_column($form->viewData('positions'), 'text'), 'Hanya jabatan aktif unit Gudang.');

        // Ganti unit → jabatan yang tidak ada di unit baru dikosongkan.
        $form->set('positionId', $this->kepala->id)->set('orgUnitId', $this->keuangan->id);
        $this->assertNull($form->get('positionId'));

        // Tanpa unit: semua jabatan aktif berkelompok per unit; memilih jabatan mengisi unitnya.
        $form->set('orgUnitId', null);
        $kelompok = collect($form->viewData('positions'))->pluck('group', 'text');
        $this->assertSame(['Akuntan' => 'Keuangan', 'Kepala Gudang' => 'Gudang', 'Staf Gudang' => 'Gudang'], $kelompok->sortKeys()->all());
        $form->set('positionId', $this->akuntan->id);
        $this->assertSame((int) $this->keuangan->id, (int) $form->get('orgUnitId'));

        // Jabatan unit lain dikirim langsung dari browser (tanpa hook) → ditolak simpan.
        $form->set('orgUnitId', $this->gudang->id)->set('positionId', $this->akuntan->id)
            ->call('save')->assertHasErrors('positionId');
        $this->assertNull($target->refresh()->position_id);

        // Data lama: pasangan unit + jabatan yang tidak cocok tetap bisa disimpan bila tidak diubah.
        $target->forceFill(['org_unit_id' => $this->gudang->id, 'position_id' => $this->akuntan->id])->save();
        $this->form($target)->set('name', 'Target Baru')->call('save')->assertHasNoErrors();
        $this->assertSame('Target Baru', $target->refresh()->name);
    }

    #[Test]
    public function tc_acc_49_atasan_berkelompok_dan_dicari_ke_server(): void
    {
        $this->makeUser('warehouse_head', attributes: ['name' => 'Andi Gudang', 'org_unit_id' => $this->gudang->id, 'position_id' => $this->kepala->id]);
        $this->makeUser('management', attributes: ['name' => 'Budi Direktur', 'org_unit_id' => $this->direksi->id]);
        $this->makeUser('management', attributes: ['name' => 'Cici Operasional', 'org_unit_id' => $this->operasional->id]);
        $this->makeUser('warehouse_staff', attributes: ['name' => 'Dodi Keuangan', 'org_unit_id' => $this->keuangan->id]);
        $this->makeUser('warehouse_staff', attributes: ['name' => 'Eka Keluar', 'org_unit_id' => $this->gudang->id, 'is_active' => false]);
        $klien = $this->makeUser('', attributes: ['name' => 'Fani Klien', 'client_id' => $this->makeClient()->id]);
        $target = $this->makeUser('warehouse_staff', attributes: ['name' => 'Gita Target', 'org_unit_id' => $this->gudang->id]);

        $form = $this->form($target)->set('lanjutanTerbuka', true);
        $opsi = collect($form->viewData('managers'))->keyBy('text');

        $this->assertSame(__('Unit ini'), $opsi['Andi Gudang']['group']);
        $this->assertSame(__('Unit induk'), $opsi['Cici Operasional']['group']);
        $this->assertSame(__('Unit induk'), $opsi['Budi Direktur']['group']);
        $this->assertSame(__('Unit lain'), $opsi['Dodi Keuangan']['group']);
        $this->assertSame(__('Unit lain'), $opsi['Zaki Admin']['group'], 'Tanpa unit masuk Unit lain.');
        $urut = array_keys($opsi->all());
        $this->assertLessThan(array_search('Budi Direktur', $urut), array_search('Cici Operasional', $urut), 'Induk terdekat dulu.');
        $this->assertLessThan(array_search('Cici Operasional', $urut), array_search('Andi Gudang', $urut));
        $this->assertLessThan(array_search('Dodi Keuangan', $urut), array_search('Budi Direktur', $urut));
        foreach (['Gita Target', 'Eka Keluar', 'Fani Klien'] as $nama) {
            $this->assertFalse($opsi->has($nama), $nama.' tidak ditawarkan.');
        }
        $form->assertSeeHtml('<optgroup label="'.__('Unit induk').'">')->assertSeeHtml('data-server="1"');

        // Cari ke server: kelompok ikut, pencarian nama/jabatan/unit, tanpa yang dikecualikan.
        $form->call('cariPilihan', 'managerId', 'kepala gudang');
        $this->assertSame([['Andi Gudang', __('Unit ini')]], array_map(fn ($o) => [$o['text'], $o['group']], $form->effects['returns'][0]));
        $form->call('cariPilihan', 'managerId', 'Klien');
        $this->assertSame([], $form->effects['returns'][0]);
        $form->call('cariPilihan', 'name', 'Andi');
        $this->assertSame([], $form->effects['returns'][0], 'Isian lain tidak bisa dicari.');

        // Nilai dari browser di luar daftar (akun Klien) ditolak simpan.
        $this->form($target)->set('managerId', $klien->id)->call('save')->assertHasErrors('managerId');
        $this->assertNull($target->refresh()->manager_id);

        // Tanpa hak mengubah pengguna: method cari tidak membuka daftar.
        $staf = $this->makeUser('warehouse_staff');
        $this->actingAs($staf);
        $form->call('cariPilihan', 'managerId', 'Andi');
        $this->assertEmpty($form->effects['returns'][0] ?? []);
    }

    #[Test]
    public function tc_acc_50_lingkaran_atasan_manual_ditolak(): void
    {
        $andi = $this->makeUser('warehouse_head', attributes: ['name' => 'Andi']);
        $budi = $this->makeUser('warehouse_staff', attributes: ['name' => 'Budi', 'manager_id' => $andi->id]);
        $cici = $this->makeUser('warehouse_staff', attributes: ['name' => 'Cici', 'manager_id' => $budi->id]);

        $form = $this->form($andi)->set('managerId', $cici->id)->call('save')->assertHasErrors('managerId');
        $this->assertSame(
            'Pilihan ini membuat lingkaran atasan: Andi → Cici → Budi → Andi. Pilih atasan lain atau ubah jabatannya.',
            $form->errors()->first('managerId'),
        );
        $this->assertTrue($form->get('lanjutanTerbuka'), 'Pengaturan lanjutan dibuka supaya pesannya terlihat.');
        $this->assertNull($andi->refresh()->manager_id);

        // Tanpa lingkaran tetap tersimpan seperti biasa.
        $dodi = $this->makeUser('management', attributes: ['name' => 'Dodi']);
        $this->form($andi)->set('managerId', $dodi->id)->call('save')->assertHasNoErrors();
        $this->assertSame((int) $dodi->id, (int) $andi->refresh()->manager_id);
    }

    #[Test]
    public function tc_acc_50b_lingkaran_lewat_jabatan_ditolak_di_pengguna_dan_jabatan(): void
    {
        // Staf Gudang melapor ke Kepala Gudang (peta jabatan).
        $this->staf->forceFill(['reports_to_position_id' => $this->kepala->id])->save();
        $kepala = $this->makeUser('warehouse_head', attributes: ['name' => 'Kepala', 'org_unit_id' => $this->gudang->id, 'position_id' => $this->kepala->id]);
        $staf = $this->makeUser('warehouse_staff', attributes: ['name' => 'Staf', 'org_unit_id' => $this->gudang->id, 'position_id' => $this->staf->id]);

        // Kepala diberi atasan manual Staf → Staf (lewat jabatan) melapor ke Kepala → lingkaran.
        $this->form($kepala)->set('managerId', $staf->id)->call('save')->assertHasErrors('managerId');
        $this->assertNull($kepala->refresh()->manager_id);

        // Pengguna baru memegang Kepala Gudang dengan atasan manual Staf → lingkaran juga.
        $this->form()->set('name', 'Kepala Baru')->set('email', 'kepala.baru@demo.wms.test')
            ->call('pilihPeran', Role::findByCode('management')->id)
            ->set('orgUnitId', $this->gudang->id)->set('positionId', $this->kepala->id)->set('managerId', $staf->id)
            ->call('save')->assertHasErrors('managerId');
        $this->assertFalse(User::query()->where('email', 'kepala.baru@demo.wms.test')->exists());

        // Jabatan: peta jabatan tidak berputar, tetapi atasan manual pemegangnya membuat lingkaran.
        $this->staf->forceFill(['reports_to_position_id' => null])->save();
        $kepala->forceFill(['manager_id' => $staf->id])->save();

        Livewire::actingAs($this->admin)->test(OrgTree::class)
            ->call('pilihUnit', $this->gudang->id)
            ->call('editJabatan', $this->staf->id)
            ->set('positionReportsTo', $this->kepala->id)
            ->call('simpanJabatan')
            ->assertHasErrors('positionReportsTo');
        $this->assertNull($this->staf->refresh()->reports_to_position_id);

        // Atasan manual dilepas → jabatan boleh dipasang.
        $kepala->forceFill(['manager_id' => null])->save();
        Livewire::actingAs($this->admin)->test(OrgTree::class)
            ->call('pilihUnit', $this->gudang->id)
            ->call('editJabatan', $this->staf->id)
            ->set('positionReportsTo', $this->kepala->id)
            ->call('simpanJabatan')
            ->assertHasNoErrors();
        $this->assertSame((int) $this->kepala->id, (int) $this->staf->refresh()->reports_to_position_id);
    }
}

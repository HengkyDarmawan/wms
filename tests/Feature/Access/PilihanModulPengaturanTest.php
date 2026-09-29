<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Livewire\OrgTree;
use App\Domain\Access\Livewire\ProjectTeam;
use App\Domain\Access\Livewire\RoleForm;
use App\Domain\Access\Livewire\UserList;
use App\Domain\Access\Models\OrgUnit;
use App\Domain\Access\Models\Position;
use App\Domain\Approval\Livewire\ApprovalMap;
use App\Domain\Approval\Livewire\ApprovalSimulation;
use App\Domain\Approval\Livewire\DelegationManager;
use App\Domain\Approval\Livewire\RuleForm;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-ACC-51–TC-ACC-51c — `<x-pilih>` di modul Pengaturan & pengguna (A-388):
 * calon Tim site dicari ke server tanpa akun klien lain, rujukan approver
 * "orang tertentu" / pemohon / delegat dicari ke server, dan layar master
 * pendek memakai kotak yang bisa dicari. Nilai tersimpan tidak berubah.
 */
class PilihanModulPengaturanTest extends TenantTestCase
{
    /** @return array<int, array<string, mixed>> */
    private function hasil($komponen): array
    {
        return $komponen->effects['returns'][0] ?? [];
    }

    #[Test]
    public function tc_acc_51_calon_tim_site_dicari_ke_server_tanpa_klien_lain(): void
    {
        $admin = $this->makeUser('company_admin');
        $proyek = $this->makeProject();
        $this->makeUser('warehouse_staff', attributes: ['name' => 'Timo Staf']);
        $this->makeUser('', attributes: ['name' => 'Timo Klien Sendiri', 'client_id' => $proyek->client_id]);
        $asing = $this->makeUser('', attributes: ['name' => 'Timo Klien Asing', 'client_id' => $this->makeClient()->id]);

        $tim = Livewire::actingAs($admin)->test(ProjectTeam::class, ['project' => $proyek])->call('tambah')
            ->assertSeeHtml('data-server="1"')->call('cariPilihan', 'userId', 'timo');
        $this->assertSame(
            [['Timo Staf', __('Staf kita')], ['Timo Klien Sendiri', __('Akun portal klien')]],
            array_map(fn ($o) => [$o['text'], $o['group']], $this->hasil($tim)),
        );

        // Akun klien lain dikirim langsung dari browser → ditolak.
        $tim->set('userId', (string) $asing->id)->set('roleId', '1')->call('simpanTambah')->assertHasErrors('userId');

        // Tanpa `role.assign`: method cari tidak membuka daftar.
        $staf = $this->makeUser('warehouse_staff');
        $this->actingAs($staf);
        $tim->call('cariPilihan', 'userId', 'timo');
        $this->assertSame([], $this->hasil($tim));
    }

    #[Test]
    public function tc_acc_51b_approver_pemohon_dan_delegat_dicari_ke_server(): void
    {
        $admin = $this->makeUser('company_admin', attributes: ['name' => 'Rina Admin']);
        $this->makeUser('management', attributes: ['name' => 'Rian Direktur']);
        $this->makeUser('', attributes: ['name' => 'Rika Klien', 'client_id' => $this->makeClient()->id]);

        // Aturan approval: rujukan "orang tertentu" = pengguna internal aktif; jenis lain tidak bisa dicari.
        $aturan = Livewire::actingAs($admin)->test(RuleForm::class)
            ->set('steps.0.approver_type', 'user')->call('cariPilihan', 'steps.0.approver_ref_id', 'ri');
        $this->assertEqualsCanonicalizing(['Rina Admin', 'Rian Direktur'], array_column($this->hasil($aturan), 'text'));
        $aturan->set('steps.0.approver_type', 'role')->call('cariPilihan', 'steps.0.approver_ref_id', 'ri');
        $this->assertSame([], $this->hasil($aturan));

        // Peta approval: pemohon termasuk akun Klien (bertanda).
        $peta = Livewire::actingAs($admin)->test(ApprovalMap::class)->call('cariPilihan', 'pemohon', 'rika');
        $this->assertSame(__('klien'), $this->hasil($peta)[0]['badge']);

        $sim = Livewire::actingAs($admin)->test(ApprovalSimulation::class)->call('cariPilihan', 'manual.requester_id', 'rian');
        $this->assertSame(['Rian Direktur'], array_column($this->hasil($sim), 'text'));

        // Delegasi: internal aktif saja.
        $del = Livewire::actingAs($admin)->test(DelegationManager::class)->call('buat')->call('cariPilihan', 'form.to_user_id', 'ri');
        $this->assertContains('Rian Direktur', array_column($this->hasil($del), 'text'));
        $this->assertNotContains('Rika Klien', array_column($this->hasil($del), 'text'));
    }

    #[Test]
    public function tc_acc_51c_layar_pengaturan_memakai_kotak_pilihan_yang_bisa_dicari(): void
    {
        $admin = $this->makeUser('company_admin');
        $unit = OrgUnit::create(['code' => 'OPS', 'name' => 'Operasional']);
        $atas = Position::create(['org_unit_id' => $unit->id, 'code' => 'DIR', 'name' => 'Direktur', 'level' => 1, 'is_active' => true]);

        Livewire::actingAs($admin)->test(UserList::class)
            ->assertSeeHtml('id="f-role"')->assertSeeHtml('class="nx-pilih"')
            ->set('unitFilter', (string) $unit->id)->assertOk();

        Livewire::actingAs($admin)->test(OrgTree::class)->call('pilihUnit', $unit->id)->call('jabatanBaru')
            ->assertSeeHtml('<optgroup label="Operasional">')->assertSeeHtml('<option value="'.$atas->id.'">Direktur</option>');

        Livewire::actingAs($admin)->test(RoleForm::class)->assertSeeHtml('id="copyFrom"')->assertSeeHtml('data-badge="'.__('bawaan').'"');
    }
}

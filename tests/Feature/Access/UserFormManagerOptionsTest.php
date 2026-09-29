<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Domain\Access\Livewire\UserForm;
use App\Domain\Access\Models\OrgUnit;
use App\Domain\Access\Models\Position;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-ACC-47 — pilihan "Atasan langsung (bila beda dari jabatan)" (A-358):
 * nama + badge jabatan + unit, tanpa diri sendiri / nonaktif / Klien,
 * "— Ikuti jabatan —" paling atas, dan nilai tersimpan sama seperti sebelumnya.
 */
class UserFormManagerOptionsTest extends TenantTestCase
{
    private User $admin;

    private User $kepala;

    private User $tanpaJabatan;

    private User $tanpaUnit;

    private User $nonaktif;

    private User $klien;

    private User $target;

    protected function setUp(): void
    {
        parent::setUp();

        $gudang = OrgUnit::create(['code' => 'GDG', 'name' => 'Gudang']);
        $jabatan = Position::create(['org_unit_id' => $gudang->id, 'code' => 'KAG', 'name' => 'Kepala Gudang', 'level' => 1, 'is_active' => true]);

        $this->admin = $this->makeUser('company_admin', attributes: ['name' => 'Zaki Admin']);
        $this->kepala = $this->makeUser('warehouse_head', attributes: ['name' => 'Andi Kepala', 'position_id' => $jabatan->id, 'org_unit_id' => $gudang->id]);
        $this->tanpaJabatan = $this->makeUser('warehouse_staff', attributes: ['name' => 'Beni Staf', 'org_unit_id' => $gudang->id]);
        $this->tanpaUnit = $this->makeUser('warehouse_staff', attributes: ['name' => 'Cici Lepas']);
        $this->nonaktif = $this->makeUser('warehouse_staff', attributes: ['name' => 'Dodi Keluar', 'is_active' => false]);
        $this->klien = $this->makeUser('', attributes: ['name' => 'Eka Klien', 'client_id' => $this->makeClient()->id]);
        $this->target = $this->makeUser('warehouse_staff', attributes: ['name' => 'Fani Target']);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function opsi(?User $untuk = null)
    {
        return collect(Livewire::actingAs($this->admin)->test(UserForm::class, ['userId' => ($untuk ?? $this->target)->id])->viewData('managers'))->keyBy('value');
    }

    #[Test]
    public function tc_acc_47_pilihan_atasan_berlabel_jabatan_dan_unit(): void
    {
        $opsi = $this->opsi();

        $this->assertSame(['value' => (int) $this->kepala->id, 'text' => 'Andi Kepala', 'badge' => 'Kepala Gudang', 'sub' => 'Gudang'], $opsi[$this->kepala->id]);
        $this->assertSame([null, 'Gudang'], [$opsi[$this->tanpaJabatan->id]['badge'], $opsi[$this->tanpaJabatan->id]['sub']], 'Tanpa jabatan: tanpa badge.');
        $this->assertSame([null, null], [$opsi[$this->tanpaUnit->id]['badge'], $opsi[$this->tanpaUnit->id]['sub']], 'Tanpa unit: tanpa teks unit.');

        // Yang dikecualikan: orang yang sedang diubah, nonaktif, akun Klien.
        foreach ([$this->target, $this->nonaktif, $this->klien] as $u) {
            $this->assertFalse($opsi->has($u->id), $u->name.' tidak boleh ditawarkan.');
        }

        // Layar: label baru, "— Ikuti jabatan —" paling atas, data badge & unit ikut dicari.
        $layar = Livewire::actingAs($this->admin)->test(UserForm::class, ['userId' => $this->target->id])
            ->set('lanjutanTerbuka', true)
            ->assertSee(__('Atasan langsung (bila beda dari jabatan)'))
            ->assertDontSee(__('Atasan langsung (isi manual)'))
            ->assertSeeHtml('class="nx-pilih"')
            ->assertSeeHtml('data-badge="Kepala Gudang"')
            ->assertSeeHtml('data-sub="Gudang"');
        $html = $layar->html();
        $this->assertLessThan(strpos($html, 'Andi Kepala'), strpos($html, '<option value="">'.__('— Ikuti jabatan —').'</option>'), '"Ikuti jabatan" paling atas.');
        $this->assertStringContainsString(__('Jabatan ini belum punya atasan di Struktur organisasi; isi bila perlu.'), $html, 'Keterangan A-345 tetap ada.');
    }

    #[Test]
    public function tc_acc_47b_nilai_atasan_tersimpan_sama_seperti_sebelumnya(): void
    {
        // Ubah pengguna: pilih atasan, lalu kembali ke "Ikuti jabatan".
        Livewire::actingAs($this->admin)->test(UserForm::class, ['userId' => $this->target->id])
            ->set('managerId', $this->kepala->id)->call('save')->assertHasNoErrors();
        $this->assertSame((int) $this->kepala->id, (int) $this->target->refresh()->manager_id);

        Livewire::actingAs($this->admin)->test(UserForm::class, ['userId' => $this->target->id])
            ->set('managerId', null)->call('save')->assertHasNoErrors();
        $this->assertNull($this->target->refresh()->manager_id);

        // Tambah pengguna dengan atasan.
        Livewire::actingAs($this->admin)->test(UserForm::class)
            ->set('name', 'Gita Baru')->set('email', 'gita.baru@demo.wms.test')
            ->call('pilihPeran', Role::findByCode('management')->id)
            ->set('managerId', $this->kepala->id)
            ->call('save')->assertHasNoErrors();
        $this->assertSame((int) $this->kepala->id, (int) User::query()->where('email', 'gita.baru@demo.wms.test')->value('manager_id'));

        // Atasan tersimpan yang kini nonaktif tetap tampil (bertanda), tidak hilang diam-diam.
        $this->target->forceFill(['manager_id' => $this->nonaktif->id])->save();
        $opsi = $this->opsi();
        $this->assertSame('Dodi Keluar ('.__('nonaktif').')', $opsi[$this->nonaktif->id]['text']);
        $this->assertSame((int) $this->nonaktif->id, (int) $opsi->keys()->first(), 'Ditaruh paling atas.');
    }
}

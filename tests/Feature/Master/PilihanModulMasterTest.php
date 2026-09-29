<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Domain\Master\Livewire\ItemCategoryList;
use App\Domain\Master\Livewire\ItemForm;
use App\Domain\Master\Livewire\ItemList;
use App\Domain\Master\Livewire\ProjectList;
use App\Domain\Master\Models\Project;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-MST-50–TC-MST-50b — `<x-pilih>` di modul Master (A-389): PIC proyek
 * dicari ke server (pengguna internal aktif saja, izin layar diulang, id di
 * luar daftar ditolak simpan, PIC tersimpan yang tidak diubah tetap boleh),
 * dan pilihan master kecil (kategori, satuan, klien) memakai kotak yang bisa
 * dicari. Nilai tersimpan tidak berubah.
 */
class PilihanModulMasterTest extends TenantTestCase
{
    /** @return array<int, array<string, mixed>> */
    private function hasil($komponen): array
    {
        return $komponen->effects['returns'][0] ?? [];
    }

    #[Test]
    public function tc_mst_50_pic_proyek_dicari_ke_server_dengan_cakupan_yang_sama(): void
    {
        $admin = $this->makeUser('company_admin');
        $pic = $this->makeUser('warehouse_staff', attributes: ['name' => 'Pipit Gudang']);
        $this->makeUser('warehouse_staff', attributes: ['name' => 'Pipin Nonaktif', 'is_active' => false]);
        $klien = $this->makeUser('', attributes: ['name' => 'Pipo Klien', 'client_id' => $this->makeClient()->id]);

        $form = Livewire::actingAs($admin)->test(ProjectList::class)->call('buat')
            ->assertSeeHtml('data-server="1"')->call('cariPilihan', 'form.pic_user_id', 'pip');
        $this->assertSame(['Pipit Gudang'], array_column($this->hasil($form), 'text'));

        // Model lain tidak bisa dicari.
        $form->call('cariPilihan', 'form.client_id', 'pip');
        $this->assertSame([], $this->hasil($form));

        // Akun klien dikirim langsung dari browser → ditolak; PIC internal aktif → tersimpan.
        $form->set('form.code', 'PRPIC1')->set('form.name', 'Proyek PIC')->set('form.is_internal', true)
            ->set('form.pic_user_id', (string) $klien->id)->call('simpan')->assertHasErrors('form.pic_user_id')
            ->set('form.pic_user_id', $pic->id.'abc')->call('simpan')->assertHasErrors('form.pic_user_id')
            ->set('form.pic_user_id', (string) $pic->id)->call('simpan')->assertHasNoErrors();
        $this->assertSame($pic->id, Project::query()->where('name', 'Proyek PIC')->value('pic_user_id'));

        // Tanpa `project.create`: method cari menolak (izin layar diulang).
        $this->actingAs($this->makeUser('warehouse_staff'));
        $form->call('cariPilihan', 'form.pic_user_id', 'pip');
        $this->assertSame([], $this->hasil($form));
    }

    #[Test]
    public function tc_mst_50b_pic_tersimpan_tidak_diubah_tetap_boleh_dan_master_kecil_bisa_dicari(): void
    {
        $admin = $this->makeUser('company_admin');
        $lama = $this->makeUser('warehouse_staff', attributes: ['name' => 'Pak Lama']);
        $proyek = $this->makeProject(['pic_user_id' => $lama->id]);
        $lama->update(['is_active' => false]);

        // PIC lama kini nonaktif: tidak diberi label, tetapi menyimpan tanpa mengubahnya tetap boleh.
        $ubah = Livewire::actingAs($admin)->test(ProjectList::class)->call('ubah', $proyek->id)
            ->assertDontSeeHtml('>Pak Lama</option>')
            ->set('form.name', 'Nama baru')->call('simpan')->assertHasNoErrors();
        $this->assertSame($lama->id, $proyek->refresh()->pic_user_id);
        $ubah->call('ubah', $proyek->id)->set('form.pic_user_id', '')->call('simpan')->assertHasNoErrors();
        $this->assertNull($proyek->refresh()->pic_user_id);

        Livewire::actingAs($admin)->test(ProjectList::class)
            ->assertSeeHtml('id="filter-klien"')->assertSeeHtml('class="nx-pilih"');
        Livewire::actingAs($admin)->test(ItemList::class)->assertSeeHtml('id="filter-kategori-item"');
        Livewire::actingAs($admin)->test(ItemForm::class)
            ->assertSeeHtml('id="item-kategori"')->assertSeeHtml('id="item-satuan"')->assertSeeHtml('id="item-berat-satuan"');
        Livewire::actingAs($admin)->test(ItemCategoryList::class)->call('buat')
            ->assertSeeHtml('id="kategori-induk"')->assertSeeHtml('id="kategori-penyimpanan"');
    }
}

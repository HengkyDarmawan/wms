<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Master\Actions\SaveClientContact;
use App\Domain\Master\Livewire\ClientDetail;
use App\Domain\Master\Livewire\ClientList;
use App\Domain\Master\Livewire\ProjectList;
use App\Domain\Master\Models\ClientContact;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-MST-42 — halaman detail klien `/clients/{id}` ([A-327]): tab Proyek, PIC,
 * dan Akun portal, tautan dari daftar, serta tombol Tambah proyek yang membawa
 * kliennya.
 */
class ClientDetailTest extends TenantTestCase
{
    #[Test]
    public function tc_mst_42_halaman_detail_klien_menampilkan_proyek_pic_dan_akun(): void
    {
        $admin = $this->makeUser('company_admin');
        $klien = $this->makeClient(['name' => 'PT Klien Detail']);
        $proyek = $this->makeProject(['client_id' => $klien->id, 'name' => 'Proyek Detail Satu']);

        app(SaveClientContact::class)->handle($klien, null, [
            'name' => 'Sari Wulan',
            'position' => 'Admin Site',
            'projects' => [$proyek->id],
        ]);

        $portal = $this->makeUser('client_user', ScopeType::Project, $proyek->id, [
            'name' => 'Lina Portal',
            'client_id' => $klien->id,
        ]);

        Livewire::actingAs($admin)
            ->test(ClientDetail::class, ['client' => $klien])
            ->assertSet('tab', 'proyek')
            ->assertSee('Proyek Detail Satu')
            ->call('pilihTab', 'pic')
            ->assertSet('tab', 'pic')
            ->assertSee('Sari Wulan')
            ->assertSee('Admin Site')
            ->call('pilihTab', 'akun')
            ->assertSet('tab', 'akun')
            ->assertSee('Lina Portal')
            ->assertSee($portal->email)
            ->call('pilihTab', 'ngawur')
            ->assertSet('tab', 'proyek');

        $this->actingAs($admin)->get($this->tenantUrl('clients/'.$klien->id))
            ->assertOk()
            ->assertSee('PT Klien Detail');

        // Nama klien di daftar Klien menaut ke halaman ini.
        $this->actingAs($admin)->get($this->tenantUrl('clients'))
            ->assertOk()
            ->assertSee(route('clients.show', $klien->id));

        // Begitu juga di daftar Proyek.
        $this->actingAs($admin)->get($this->tenantUrl('projects'))
            ->assertOk()
            ->assertSee(route('clients.show', $klien->id));
    }

    #[Test]
    public function tc_mst_42b_izin_dan_tautan_tambah_proyek(): void
    {
        $admin = $this->makeUser('company_admin');
        $klien = $this->makeClient();

        // Staf gudang tidak punya `client.view`.
        $staf = $this->makeUser('warehouse_staff');

        $this->actingAs($staf)->get($this->tenantUrl('clients/'.$klien->id))->assertForbidden();

        // Tombol "Tambah proyek" di halaman klien membuka form dengan klien terisi.
        Livewire::actingAs($admin)
            ->withQueryParams(['klien' => (string) $klien->id])
            ->test(ProjectList::class)
            ->assertSet('showForm', true)
            ->assertSet('form.client_id', (string) $klien->id);

        // Tombol "Ubah" di halaman klien membuka form ubah di daftar klien.
        Livewire::actingAs($admin)
            ->withQueryParams(['ubah' => (string) $klien->id])
            ->test(ClientList::class)
            ->assertSet('showForm', true)
            ->assertSet('form.code', $klien->code);
    }

    #[Test]
    public function tc_mst_42c_pic_dikelola_dari_halaman_klien(): void
    {
        $admin = $this->makeUser('company_admin');
        $klien = $this->makeClient();
        $proyek = $this->makeProject(['client_id' => $klien->id]);

        Livewire::actingAs($admin)
            ->test(ClientDetail::class, ['client' => $klien])
            ->call('buatPic')
            ->assertSet('showForm', true)
            ->set('formPic.name', 'Budi Hartono')
            ->set('formPic.position', 'Project Manager')
            ->set('formPic.phone', '0812-0000-0001')
            ->set('formPic.projects', [(string) $proyek->id])
            ->call('simpanPic')
            ->assertSet('showForm', false)
            ->assertHasNoErrors();

        $pic = ClientContact::query()->where('client_id', $klien->id)->firstOrFail();

        $this->assertSame('Budi Hartono', $pic->name);
        $this->assertSame('6281200000001', $pic->phone);

        // Nomor tidak sah menempel di kolomnya, bukan jadi pesan umum.
        Livewire::actingAs($admin)
            ->test(ClientDetail::class, ['client' => $klien])
            ->call('buatPic')
            ->set('formPic.name', 'Tono')
            ->set('formPic.phone', '123')
            ->call('simpanPic')
            ->assertHasErrors('formPic.phone');
    }
}

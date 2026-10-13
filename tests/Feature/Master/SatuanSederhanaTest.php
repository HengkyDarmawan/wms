<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Domain\Master\Livewire\UomList;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Models\UomCategory;
use App\Domain\Master\Support\DefaultUoms;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-MST-51 s.d. TC-MST-53 — satuan sederhana (A-411): satuan bawaan lengkap,
 * daftar ditulis sebagai kalimat ("1 M = 100 CM"), form kalimat dua arah &
 * kemasan, dan melengkapi company lama tanpa menimpa.
 */
class SatuanSederhanaTest extends TenantTestCase
{
    private function kategori(string $kode): UomCategory
    {
        return UomCategory::query()->where('code', $kode)->firstOrFail();
    }

    private function uom(string $kode): Uom
    {
        return Uom::query()->where('code', $kode)->firstOrFail();
    }

    #[Test]
    public function tc_mst_51_satuan_bawaan_dan_kalimat_setara(): void
    {
        foreach (['UNIT', 'BH', 'LBR', 'KODI', 'GROSS', 'RIM', 'KARTON', 'IKAT', 'KALENG', 'PAIL', 'KM', 'YARD', 'ONS', 'KW', 'MG', 'HA', 'MM2'] as $kode) {
            $this->assertTrue(Uom::query()->where('code', $kode)->exists(), 'Satuan bawaan '.$kode.' ada.');
        }

        $m = $this->uom('M');
        $this->assertSame('1 KM = 1.000 M', $this->uom('KM')->kalimatSetara($m));
        $this->assertSame('1 M = 100 CM', $this->uom('CM')->kalimatSetara($m));
        $this->assertSame('1 INCH = 0,0254 M', $this->uom('INCH')->kalimatSetara($m), 'Bukan bilangan bulat → ditulis dari satuan ini.');
        $this->assertSame('1 KG = 10 ONS', $this->uom('ONS')->kalimatSetara($this->uom('KG')));
        $this->assertNull($m->kalimatSetara($m), 'Satuan acuan tanpa kalimat.');
        $this->assertTrue($this->uom('DUS')->is_packaging);
        $this->assertNull($this->uom('DUS')->kalimatSetara($this->uom('PCS')), 'Kemasan: isi per barang.');
        $this->assertFalse($this->uom('UNIT')->is_packaging);

        $this->actingAs($this->makeUser('company_admin'));
        Livewire::test(UomList::class)->call('pilihKategori', $this->kategori('LENGTH')->id)
            ->assertSee('1 KM = 1.000 M')->assertSee('1 M = 100 CM')->assertSee(__('satuan acuan'))
            ->assertDontSee(__('Faktor ke acuan'))->assertDontSee('0.01');
        Livewire::test(UomList::class)->call('pilihKategori', $this->kategori('COUNT')->id)
            ->assertSee('1 LSN = 12 PCS')->assertSee(__('isi ditentukan per barang'));
    }

    #[Test]
    public function tc_mst_52_form_kalimat_dua_arah_dan_kemasan(): void
    {
        $this->actingAs($this->makeUser('company_admin'));
        $panjang = $this->kategori('LENGTH');

        // "1 DM = 10 CM" (lebih besar dari pembanding CM).
        Livewire::test(UomList::class)->call('pilihKategori', $panjang->id)->call('buatSatuan')
            ->assertSet('form.pembanding', (string) $panjang->reference_uom_id)
            ->set('form.code', 'DM')->set('form.name', 'Desimeter')->set('form.mode', 'besar')
            ->set('form.pembanding', (string) $this->uom('CM')->id)->set('form.angka', '10')->call('simpanSatuan')->assertHasNoErrors();
        $this->assertEqualsWithDelta(0.1, (float) $this->uom('DM')->factor_to_reference, 1e-9);

        // Arah kecil: "1 MM = 1000 UM".
        Livewire::test(UomList::class)->call('pilihKategori', $panjang->id)->call('buatSatuan')
            ->set('form.code', 'UM')->set('form.name', 'Mikrometer')->set('form.mode', 'kecil')
            ->set('form.pembanding', (string) $this->uom('MM')->id)->set('form.angka', '1000')->call('simpanSatuan')->assertHasNoErrors();
        $this->assertEqualsWithDelta(0.000001, (float) $this->uom('UM')->factor_to_reference, 1e-12);
        $this->assertSame('1 M = 1.000.000 UM', $this->uom('UM')->kalimatSetara($this->uom('M')));

        // Kemasan → faktor 1 & bertanda; angka tidak wajib.
        Livewire::test(UomList::class)->call('pilihKategori', $this->kategori('COUNT')->id)->call('buatSatuan')
            ->set('form.code', 'KRG')->set('form.name', 'Karung')->set('form.mode', 'kemasan')->call('simpanSatuan')->assertHasNoErrors();
        $this->assertTrue($this->uom('KRG')->is_packaging);
        $this->assertSame(1.0, (float) $this->uom('KRG')->factor_to_reference);

        // Ubah: isian kalimat terisi dari faktor tersimpan; angka wajib & pembanding sekategori.
        Livewire::test(UomList::class)->call('pilihKategori', $panjang->id)->call('ubahSatuan', $this->uom('CM')->id)
            ->assertSet('form.mode', 'kecil')->assertSet('form.angka', '100')
            ->set('form.angka', '')->call('simpanSatuan')->assertHasErrors('form.angka')
            ->set('form.angka', '100')->set('form.pembanding', (string) $this->uom('KG')->id)->call('simpanSatuan')->assertHasErrors('form.pembanding');
        Livewire::test(UomList::class)->call('pilihKategori', $panjang->id)->call('ubahSatuan', $this->uom('KM')->id)
            ->assertSet('form.mode', 'besar')->assertSet('form.angka', '1000');

        // Satuan acuan: tanpa isian setara, tetap berfaktor 1.
        Livewire::test(UomList::class)->call('pilihKategori', $panjang->id)->call('ubahSatuan', $this->uom('M')->id)
            ->assertViewHas('formDasar', true)->set('form.name', 'Meter (dasar)')->call('simpanSatuan')->assertHasNoErrors();
        $this->assertSame(1.0, (float) $this->uom('M')->factor_to_reference);
    }

    #[Test]
    public function tc_mst_53_melengkapi_company_lama_tanpa_menimpa(): void
    {
        $km = $this->uom('KM');
        $km->forceFill(['name' => 'Km ubahan company', 'factor_to_reference' => 999])->save();
        $this->uom('HA')->delete();
        $this->uom('BOX')->forceFill(['is_packaging' => false])->save();

        $dibuat = DefaultUoms::pasang(timpa: false);

        $this->assertSame(1, $dibuat, 'Hanya HA yang dibuat ulang.');
        $this->assertSame(['Km ubahan company', 999.0], [$km->refresh()->name, (float) $km->factor_to_reference], 'Ubahan company tidak ditimpa.');
        $this->assertTrue($this->uom('BOX')->is_packaging, 'Kemasan bawaan berfaktor 1 diberi tanda.');
        $this->assertSame(0, DefaultUoms::pasang(timpa: false), 'Idempoten.');
    }
}

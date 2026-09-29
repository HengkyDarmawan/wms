<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\ProjectStatus;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Uom;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Livewire\BinList;
use App\Domain\Warehouse\Livewire\ItemStorageLocations;
use App\Domain\Warehouse\Livewire\WarehouseDetail;
use App\Domain\Warehouse\Livewire\WarehouseList;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-WH-63 — `<x-pilih>` di menu Gudang (A-397): proyek Gudang Site & kepala
 * gudang dicari ke server (daftar lama: proyek aktif, pengguna internal
 * aktif); id di luar daftar ditolak di isiannya kecuali nilai yang sudah
 * tersimpan; hanya pemegang izin buat/ubah gudang yang bisa mencari, akun
 * Klien tidak pernah. Gudang induk, gudang mode Denah, kategori penyimpanan,
 * saringan gudang bin, dan gudang tempat simpan item dimuat sekaligus.
 */
class PilihanGudangTest extends TenantTestCase
{
    /** @return list<int> */
    private function hasil($komponen): array
    {
        return array_map('intval', array_column($komponen->effects['returns'][0] ?? [], 'value'));
    }

    private function gudang(string $kode, string $tipe = WarehouseType::MAIN, array $lain = []): Warehouse
    {
        return app(SaveWarehouse::class)->handle(null, [
            'code' => $kode,
            'name' => 'Gudang '.$kode,
            'warehouse_type_id' => WarehouseType::query()->where('code', $tipe)->value('id'),
        ] + $lain);
    }

    #[Test]
    public function tc_wh_63_proyek_dan_kepala_gudang_dicari_ke_server(): void
    {
        $aktif = $this->makeProject();
        $tutup = $this->makeProject(['status' => ProjectStatus::Closed]);
        $kepala = $this->makeUser('warehouse_head');
        $nonaktif = $this->makeUser('warehouse_head', ScopeType::All, null, ['is_active' => false]);
        $klien = $this->makeUser('client_user', ScopeType::All, null, ['client_id' => $aktif->client_id]);
        $admin = $this->makeUser('company_admin');

        $daftar = Livewire::actingAs($admin)->test(WarehouseList::class);

        // Form belum dibuka: tidak ada yang bisa dicari.
        $daftar->call('cariPilihan', 'form.project_id', 'PRU');
        $this->assertSame([], $this->hasil($daftar));

        $daftar->call('buat')->assertSeeHtml('id="gudang-induk"')->assertSeeHtml('id="gudang-kepala"');
        $daftar->call('cariPilihan', 'form.project_id', 'PRU');
        $this->assertContains((int) $aktif->id, $this->hasil($daftar));
        $this->assertNotContains((int) $tutup->id, $this->hasil($daftar), 'Hanya proyek aktif (daftar lama).');

        $daftar->call('cariPilihan', 'form.head_user_id', mb_substr($kepala->name, 0, 6));
        $this->assertContains((int) $kepala->id, $this->hasil($daftar));
        $daftar->call('cariPilihan', 'form.head_user_id', mb_substr($klien->name, 0, 6));
        $this->assertNotContains((int) $klien->id, $this->hasil($daftar), 'Akun Klien bukan kepala gudang.');
        $this->assertNotContains((int) $nonaktif->id, $this->hasil($daftar));
        $daftar->call('cariPilihan', 'form.code', 'PRU');
        $this->assertSame([], $this->hasil($daftar));

        // Id di luar daftar dari browser ditolak di isiannya.
        $site = WarehouseType::query()->where('code', 'SITE')->value('id');
        $daftar->set('form.code', 'STX')->set('form.name', 'Site X')->set('form.warehouse_type_id', (string) $site)
            ->set('form.project_id', (string) $tutup->id)->set('form.head_user_id', (string) $klien->id)
            ->call('simpan')->assertHasErrors(['form.project_id', 'form.head_user_id']);
        $daftar->set('form.project_id', (string) $aktif->id)->set('form.head_user_id', (string) $kepala->id)
            ->call('simpan')->assertHasNoErrors(['form.project_id', 'form.head_user_id']);
        $gudang = Warehouse::query()->where('code', 'STX')->firstOrFail();
        $this->assertSame((int) $aktif->id, (int) $gudang->project_id);

        // Nilai yang sudah tersimpan tetap boleh walau kini di luar daftar (proyek ditutup, kepala nonaktif).
        $gudang->forceFill(['head_user_id' => $nonaktif->id])->save();
        $daftar->call('ubah', $gudang->id)->set('form.name', 'Site X baru')->call('simpan')
            ->assertHasNoErrors(['form.project_id', 'form.head_user_id']);
        $this->assertSame('Site X baru', $gudang->refresh()->name);

        // Tanpa izin buat/ubah gudang dan akun Klien: kosong.
        foreach ([$this->makeUser('warehouse_staff'), $klien] as $orang) {
            $this->actingAs($orang);
            $daftar->call('cariPilihan', 'form.head_user_id', mb_substr($kepala->name, 0, 6));
            $this->assertSame([], $this->hasil($daftar));
        }
    }

    #[Test]
    public function tc_wh_63b_pilihan_gudang_dimuat_sekaligus(): void
    {
        $ckg = $this->gudang('CKG');
        $this->gudang('BKS');
        $admin = $this->makeUser('company_admin');

        // Mode Denah: gudang bawaan ditulis ke properti supaya tampil terpilih.
        $denah = Livewire::actingAs($admin)->test(WarehouseList::class)->set('tampilan', 'denah')->assertSeeHtml('id="denah-gudang"');
        $this->assertSame((string) $denah->viewData('denahGudang')->id, $denah->get('gudangDenah'));
        $denah->set('gudangDenah', (string) $ckg->id)->assertViewHas('denahGudang', fn ($g) => (int) $g->id === (int) $ckg->id);
        $bin = Bin::create(['warehouse_id' => $ckg->id, 'code' => 'CKG-A-R01-L1-B01', 'bin_type' => BinType::Storage]);
        Livewire::actingAs($admin)->test(BinList::class)->assertSeeHtml('id="filter-gudang-bin"')
            ->call('mintaUbah', $bin->id)->assertSeeHtml('id="ubah-kategori"');

        Livewire::actingAs($admin)->test(WarehouseDetail::class, ['warehouse' => $ckg])
            ->call('mintaBuatBin', 1)->assertSeeHtml('id="gen-kategori"');

        $item = Item::create([
            'code' => 'BAUT-M12', 'name' => 'Baut M12', 'status' => ItemStatus::Active, 'tracking_mode' => TrackingMode::None,
            'base_uom_id' => Uom::query()->where('code', 'PCS')->value('id'),
        ]);
        Livewire::actingAs($admin)->test(ItemStorageLocations::class, ['item' => $item])->assertSeeHtml('id="tempat-gudang-baru"');
    }
}

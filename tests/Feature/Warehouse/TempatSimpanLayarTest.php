<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Uom;
use App\Domain\Warehouse\Actions\ImportItemStorageLocations;
use App\Domain\Warehouse\Actions\SaveItemStorageLocations;
use App\Domain\Warehouse\Actions\SaveLocation;
use App\Domain\Warehouse\Actions\SaveWarehouseLayout;
use App\Domain\Warehouse\Livewire\ItemStorageLocations;
use App\Domain\Warehouse\Livewire\WarehouseLayout;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\ItemStorageLocation;
use App\Domain\Warehouse\Models\Rack;
use App\Domain\Warehouse\Models\Zone;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Receipt\Concerns\ReceiptFixtures;
use Tests\TenantTestCase;

/**
 * TC-WH-57 s.d. TC-WH-60 — layar Tempat Simpan (A-365, A-369–A-371): panel
 * *Barang belum punya tempat* mode Tata letak, kartu di detail item, impor
 * Excel, dan cetak denah.
 */
class TempatSimpanLayarTest extends TenantTestCase
{
    use ReceiptFixtures;

    private Zone $zona;

    private Rack $rak;

    private Rack $area;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanPenerimaan();
        $this->zona = app(SaveLocation::class)->saveZone($this->gudang, null, ['code' => 'D', 'name' => 'Zona D']);
        $this->rak = app(SaveWarehouseLayout::class)->newRack($this->zona, ['code' => 'R01', 'levels' => '2', 'bins_per_level' => '2']);
        $this->area = Rack::query()->findOrFail((int) app(SaveWarehouseLayout::class)->areaRack($this->zona, ['code' => 'AB1'])->rackLevel()->value('rack_id'));
    }

    private function kepala()
    {
        $u = $this->makeUser('warehouse_head', ScopeType::Warehouse, $this->gudang->id);
        $u->forgetPermissionCache();

        return $u;
    }

    private function b(string $level, string $petak): Bin
    {
        return Bin::query()->withoutGlobalScopes()->where('code', 'CKG-D-R01-'.$level.'-'.$petak)->firstOrFail();
    }

    /** @param  array<int, array<int, mixed>>  $baris */
    private function berkas(array $baris): UploadedFile
    {
        $buku = new Spreadsheet;
        $buku->getActiveSheet()->fromArray(array_merge([array_values(ImportItemStorageLocations::COLUMNS)], $baris));
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($buku))->save($path);

        return new UploadedFile($path, 'tempat.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    #[Test]
    public function tc_wh_57_panel_barang_belum_punya_tempat_di_mode_tata_letak(): void
    {
        $kepala = $this->kepala();
        app(SaveItemStorageLocations::class)->replace($this->baut, $this->gudang, [['tempat' => 'rak:'.$this->rak->id]], $kepala);
        $this->actingAs($kepala);

        $this->get($this->tenantUrl('warehouses/'.$this->gudang->id.'/layout'))->assertOk()
            ->assertSee(__('Tata letak barang'))->assertSee(__('Barang belum punya tempat'))->assertSee(__('Cetak denah'));

        $layar = Livewire::test(WarehouseLayout::class, ['warehouse' => $this->gudang]);
        $hasil = data_get($layar->call('daftarBarang', '', true)->effects, 'returns.0');
        $kode = array_column($hasil['barang'], 'code');
        $this->assertNotContains('BAUT-M12', $kode, 'Sudah punya tempat di gudang ini.');
        $this->assertContains('SEMEN-PCC', $kode);
        $this->assertFalse($hasil['lebih']);

        $cari = data_get($layar->call('daftarBarang', 'kabel', true)->effects, 'returns.0');
        $this->assertSame(['KABEL-NYM'], array_column($cari['barang'], 'code'));
        $semua = data_get($layar->call('daftarBarang', 'baut', false)->effects, 'returns.0');
        $this->assertSame(['BAUT-M12'], array_column($semua['barang'], 'code'), 'Tambah barang… menawarkan semua barang aktif.');

        // Batas 50 per permintaan (aman di HP).
        $pcs = Uom::query()->where('code', 'PCS')->value('id');
        foreach (range(1, 55) as $n) {
            $this->buatItem('MASSAL-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT), TrackingMode::None, $pcs);
        }
        $banyak = data_get($layar->call('daftarBarang', 'massal', true)->effects, 'returns.0');
        $this->assertCount(50, $banyak['barang']);
        $this->assertTrue($banyak['lebih']);

        // Hanya-lihat: tanpa mode Tata letak.
        $this->actingAs($this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->gudang->id));
        Livewire::test(WarehouseLayout::class, ['warehouse' => $this->gudang])->call('daftarBarang', '', true)->assertForbidden();
    }

    #[Test]
    public function tc_wh_58_impor_tempat_simpan_semua_atau_tidak(): void
    {
        $kepala = $this->kepala();
        $this->actingAs($kepala)->get($this->tenantUrl('imports'))->assertOk()->assertSee(__('Tempat simpan barang (tata letak)'));
        $this->actingAs($kepala)->get($this->tenantUrl('imports/storage-locations/template'))->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->actingAs($this->makeUser('warehouse_staff'))->get($this->tenantUrl('imports/storage-locations/template'))->assertForbidden();

        $salah = $this->berkas([
            ['BAUT-M12', 'CKG', 'R01 · L1 · 01', 'Ya'],
            ['TIDAK-ADA', 'CKG', 'R01', ''],
            ['SEMEN-PCC', 'CKG', 'R99', ''],
            ['KABEL-NYM', 'CKG', 'R01', 'mungkin'],
        ]);
        $this->actingAs($kepala)->post($this->tenantUrl('imports/storage-locations'), ['file' => $salah])
            ->assertSessionHasErrors('file')
            ->assertSessionHas('rowErrors', fn ($t) => str_contains($t, 'Baris 3') && str_contains($t, 'Baris 4') && str_contains($t, 'Baris 5') && ! str_contains($t, 'Baris 2'));
        $this->assertSame(0, ItemStorageLocation::query()->count(), 'Satu baris salah = tidak ada yang tersimpan.');

        $benar = $this->berkas([
            ['BAUT-M12', 'CKG', 'R01 · L1 · 01', 'Ya'],
            ['baut-m12', 'ckg', 'r01', 'tidak'],
            ['SEMEN-PCC', 'CKG', 'CKG-D-R01-L2-B02', ''],
            ['KABEL-NYM', 'CKG', 'AB1', ''],
            ['GENSET-5K', 'CKG', 'D · AB1 · Area', 'Tidak'],
        ]);
        $this->actingAs($kepala)->post($this->tenantUrl('imports/storage-locations'), ['file' => $benar])
            ->assertRedirect(route('warehouses.index'))->assertSessionHas('status');

        $baut = ItemStorageLocation::query()->where('item_id', $this->baut->id)->orderBy('sequence')->get();
        $this->assertSame([(int) $this->b('L1', 'B01')->id, null], $baut->pluck('bin_id')->map(fn ($v) => $v === null ? null : (int) $v)->all());
        $this->assertSame([true, false], $baut->pluck('is_dedicated')->all());
        $this->assertSame('bin', ItemStorageLocation::query()->where('item_id', $this->semen->id)->sole()->jenis());
        $this->assertSame(['area', 'area'], ItemStorageLocation::query()->whereIn('item_id', [$this->kabel->id, $this->genset->id])->get()->map->jenis()->all());
    }

    #[Test]
    public function tc_wh_59_kartu_tempat_simpan_di_detail_item(): void
    {
        $kepala = $this->kepala();
        $this->actingAs($kepala);

        $this->get($this->tenantUrl('items/'.$this->baut->id))->assertOk()->assertSee(__('Tempat simpan'))->assertSee(__('Belum punya tempat simpan.'), false);

        Livewire::test(ItemStorageLocations::class, ['item' => $this->baut])
            ->set('gudangBaru', (string) $this->gudang->id)->call('tambahGudang')
            ->assertSet('gudangUbah', (int) $this->gudang->id)
            ->set('zonaBaru', (string) $this->zona->id)->set('rakBaru', (string) $this->rak->id)->call('tambah')
            ->set('zonaBaru', (string) $this->zona->id)->set('rakBaru', (string) $this->rak->id)
            ->set('binBaru', [(string) $this->b('L2', 'B01')->id])->set('khususBaru', true)->call('tambah')
            ->call('geser', 1, -1)
            ->call('simpan')
            ->assertSet('gudangUbah', 0)
            ->assertSee('R01 · L2 · 01')->assertSee(__('Khusus'));

        $daftar = ItemStorageLocation::query()->where('item_id', $this->baut->id)->orderBy('sequence')->get();
        $this->assertSame(['bin', 'rak'], $daftar->map->jenis()->all());
        $this->assertTrue($daftar[0]->is_dedicated);

        // Galat aturan tampil di kartu, tidak ada yang berubah.
        Livewire::test(ItemStorageLocations::class, ['item' => $this->semen])
            ->call('ubah', $this->gudang->id)
            ->set('zonaBaru', (string) $this->zona->id)->set('rakBaru', (string) $this->rak->id)
            ->set('binBaru', [(string) $this->b('L2', 'B01')->id])->call('tambah')
            ->call('simpan')
            ->assertSet('galat', fn ($g) => str_contains($g, 'khusus untuk barang BAUT-M12'));

        // Pengguna gudang lain: tidak melihat & tidak bisa mengubah.
        $lain = $this->buatGudang('SBY', 'Gudang Surabaya');
        $orangLain = $this->makeUser('warehouse_head', ScopeType::Warehouse, $lain->id);
        $orangLain->forgetPermissionCache();
        $this->actingAs($orangLain);
        Livewire::test(ItemStorageLocations::class, ['item' => $this->baut])
            ->assertDontSee('R01 · L2 · 01')
            // BR-ACC-05: gudang di luar cakupan tidak ditemukan (sama dengan halaman gudang).
            ->call('ubah', $this->gudang->id)->assertNotFound();
    }

    /**
     * TC-WH-65 — Tambah tempat bertahap: Zona → Rak/area → Bin (banyak sekaligus, tombol per tingkat);
     * kosong = seluruh rak; area tanpa kotak bin; tempat ganda dilewati; daftar rak ikut zona.
     */
    #[Test]
    public function tc_wh_65_tambah_tempat_bertahap_zona_rak_bin_banyak(): void
    {
        $this->actingAs($this->kepala());
        $l1 = (int) $this->b('L1', 'B01')->rack_level_id;

        $kartu = Livewire::test(ItemStorageLocations::class, ['item' => $this->baut])->call('ubah', $this->gudang->id)
            ->assertSeeHtml('data-tambah-tempat')->assertDontSeeHtml('data-pilih-bin');

        // Rak baru tampil setelah zona dipilih; kotak bin muncul untuk rak biasa, dengan tombol per tingkat.
        $kartu->set('zonaBaru', (string) $this->zona->id)
            ->assertViewHas('opsiRak', fn ($o) => collect($o)->pluck('value')->sort()->values()->all() === collect([$this->rak->id, $this->area->id])->sort()->values()->all())
            ->set('rakBaru', (string) $this->rak->id)
            ->assertSeeHtml('data-pilih-bin')->assertSee('L1 · B01')->assertSee('Akan ditambah: seluruh rak ini.')
            ->call('pilihTingkat', $l1)
            ->assertSet('binBaru', fn ($v) => collect($v)->sort()->values()->all() === collect([(string) $this->b('L1', 'B01')->id, (string) $this->b('L1', 'B02')->id])->sort()->values()->all())
            ->assertSee('Akan ditambah: 2 bin.')
            ->call('tambah')
            ->assertSet('rakBaru', '')->assertSet('binBaru', [])->assertSet('galat', '')
            ->assertSet('baris', fn ($b) => count($b) === 2 && collect($b)->every(fn ($r) => str_starts_with($r['tempat'], 'bin:')));

        // Area lantai: tanpa kotak bin → satu baris area.
        $kartu->set('zonaBaru', (string) $this->zona->id)->set('rakBaru', (string) $this->area->id)
            ->assertDontSeeHtml('data-pilih-bin')->assertSee('Akan ditambah: seluruh area lantai ini.')
            ->call('tambah')->assertSet('baris', fn ($b) => count($b) === 3 && $b[2]['tempat'] === 'rak:'.$this->area->id);

        // Tempat yang sudah ada dilewati; bin dari rak lain (dikirim dari browser) diabaikan → seluruh rak.
        $kartu->set('zonaBaru', (string) $this->zona->id)->set('rakBaru', (string) $this->rak->id)
            ->set('binBaru', [(string) $this->b('L1', 'B01')->id, (string) $this->b('L2', 'B02')->id])->call('tambah')
            ->assertSet('galat', fn ($g) => str_contains($g, '1 tempat sudah ada'))
            ->assertSet('baris', fn ($b) => count($b) === 4);
        $kartu->set('zonaBaru', (string) $this->zona->id)->set('rakBaru', (string) $this->rak->id)
            ->set('binBaru', ['999999'])->call('tambah')
            ->assertSet('baris', fn ($b) => count($b) === 5 && $b[4]['tempat'] === 'rak:'.$this->rak->id);

        // Tanpa rak → galat, daftar tidak berubah; simpan menyimpan urutan yang sama.
        $kartu->call('tambah')->assertSet('galat', __('Pilih rak atau area dulu.'))
            ->call('simpan')->assertSet('gudangUbah', 0);
        $this->assertSame(5, ItemStorageLocation::query()->where('item_id', $this->baut->id)->count());
    }

    #[Test]
    public function tc_wh_60_cetak_denah_berisi_barang_per_rak_dan_tips(): void
    {
        $kepala = $this->kepala();
        app(SaveItemStorageLocations::class)->replace($this->baut, $this->gudang, [['tempat' => 'bin:'.$this->b('L1', 'B02')->id, 'khusus' => true]], $kepala);
        app(SaveItemStorageLocations::class)->replace($this->kabel, $this->gudang, [['tempat' => 'rak:'.$this->area->id]], $kepala);

        $staf = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->gudang->id);
        $this->actingAs($staf)->get($this->tenantUrl('warehouses/'.$this->gudang->id.'/layout/print'))->assertOk()
            ->assertSee('Denah gudang')->assertSee('BAUT-M12')->assertSee('R01 · L1 · 02')->assertSee(__('Khusus barang ini'))
            ->assertSee('KABEL-NYM')->assertSee(__('Tips tata letak (saran)'))
            ->assertSee('Barang berat dan besar di tingkat paling bawah')
            ->assertDontSee('Rp');

        $orangLain = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->buatGudang('SBY', 'Gudang Surabaya')->id);
        $this->actingAs($orangLain)->get($this->tenantUrl('warehouses/'.$this->gudang->id.'/layout/print'))->assertNotFound();
    }
}

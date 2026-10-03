<?php

declare(strict_types=1);

namespace Tests\Feature\Count;

use App\Domain\Adjustment\Actions\ApproveStockAdjustment;
use App\Domain\Adjustment\Actions\ImportOpeningStock;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Master\Support\SetupWizard;
use App\Domain\Stock\Livewire\BalanceList;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Actions\MergeBins;
use App\Domain\Warehouse\Actions\SaveItemStorageLocations;
use App\Domain\Warehouse\Actions\SaveLocation;
use App\Domain\Warehouse\Actions\SaveWarehouseLayout;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Count\Concerns\CountFixtures;
use Tests\TenantTestCase;

/**
 * TC-MST-21b, TC-ADJ-12b, TC-ADJ-12c, TC-STK-38 — Tata letak gudang Bagian 5
 * (K-L, K-M; A-380–A-382): urutan setup awal dengan langkah *Tata letak
 * barang*, impor saldo awal tanpa kode bin (bin dari tempat simpan), dan
 * kolom **Lokasi** di Saldo stok.
 */
class SetupLokasiTest extends TenantTestCase
{
    use CountFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanOpname();
        $zona = app(SaveLocation::class)->saveZone($this->gudang, null, ['code' => 'D', 'name' => 'Zona D']);
        app(SaveWarehouseLayout::class)->newRack($zona, ['code' => 'R01', 'levels' => '1', 'bins_per_level' => '3']);
    }

    private function b(string $petak): Bin
    {
        return Bin::query()->withoutGlobalScopes()->where('code', 'CKG-D-R01-L1-'.$petak)->firstOrFail();
    }

    /**
     * @param  array<int, array<int, mixed>>  $baris
     */
    private function berkas(array $baris, ?array $judul = null): UploadedFile
    {
        $buku = new Spreadsheet;
        $buku->getActiveSheet()->fromArray(array_merge([$judul ?? array_values(ImportOpeningStock::COLUMNS)], $baris));
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($buku))->save($path);

        return new UploadedFile($path, 'saldo.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    #[Test]
    public function tc_mst_21b_urutan_setup_awal_dengan_tata_letak_barang(): void
    {
        $langkah = collect(app(SetupWizard::class)->steps());
        $this->assertSame(['terms', 'warehouse', 'bins', 'items', 'layout', 'opening', 'projects', 'users', 'approval'], $langkah->pluck('key')->all());

        $tata = $langkah->firstWhere('key', 'layout');
        $this->assertTrue($tata['optional'], 'Tata letak barang opsional tetapi tampil.');
        $this->assertFalse($tata['done']);
        // A-402: Kerjakan → daftar item (kartu Tempat simpan → Pilih di denah); mode Tata letak Denah jadi tautan kedua.
        $this->assertStringEndsWith('/items', (string) $tata['url']);
        $this->assertTrue(collect($tata['links'])->contains(fn ($l) => str_contains($l['url'], 'mode=tata')));
        $this->assertSame('minimal satu barang punya tempat simpan.', $tata['selesai_bila']);
        $this->assertStringContainsString('0 dari 4 barang aktif', $tata['catatan']['teks']);

        app(SaveItemStorageLocations::class)->replace($this->baut, $this->gudang, [['tempat' => 'bin:'.$this->b('B01')->id]], $this->kepala);
        $tata = collect(app(SetupWizard::class)->steps())->firstWhere('key', 'layout');
        $this->assertTrue($tata['done']);
        $this->assertStringContainsString('1 dari 4 barang aktif', $tata['catatan']['teks']);

        $admin = $this->makeUser('company_admin');
        $this->actingAs($admin)->get($this->tenantUrl('setup'))->assertOk()
            ->assertSeeInOrder([__('Buat gudang'), __('Susun lokasi rak & bin'), __('Daftarkan item'), __('Atur tata letak barang'), __('Masukkan saldo awal')])
            ->assertSee(__('Impor tempat simpan dari Excel'))
            ->assertSee(__('Dianggap selesai bila'))
            ->assertSee('1 dari 4 barang aktif');
        $this->actingAs($this->kepala)->get($this->tenantUrl('warehouses/'.$this->gudang->id.'/layout?mode=tata'))->assertOk()
            ->assertSee('"mulaiTata":true', false);
    }

    /**
     * TC-MST-21c (A-402) — langkah rak & bin menaut ke Denah (bukan /bins); langkah saldo awal
     * menaut ke form Penyesuaian beralasan Saldo awal, menjelaskan "menunggu approval" selama
     * ADJ belum disetujui, dan tercentang setelah disetujui.
     */
    #[Test]
    public function tc_mst_21c_langkah_saldo_awal_menaut_form_penyesuaian_dan_menjelaskan_approval(): void
    {
        $langkah = collect(app(SetupWizard::class)->steps());
        $bins = $langkah->firstWhere('key', 'bins');
        $this->assertStringContainsString('/warehouses/'.$this->gudang->id.'/layout', (string) $bins['url']);
        $this->assertTrue(collect($bins['links'])->contains(fn ($l) => str_ends_with($l['url'], '#impor-bins')));

        $saldo = $langkah->firstWhere('key', 'opening');
        $this->assertStringContainsString('/adjustments/create?reason=OPENING', (string) $saldo['url']);
        $this->assertTrue(collect($saldo['links'])->contains(fn ($l) => str_ends_with($l['url'], '#impor-opening-stock')));
        $this->assertTrue($saldo['done'], 'Fixture opname sudah punya saldo.');

        // Saldo dikosongkan: langkah belum selesai dan belum ada catatan.
        \App\Domain\Stock\Models\StockBalance::query()->update(['qty_base' => 0]);
        $saldo = collect(app(SetupWizard::class)->steps())->firstWhere('key', 'opening');
        $this->assertFalse($saldo['done']);
        $this->assertNull($saldo['catatan']);

        // Impor saldo awal → ADJ OPENING menunggu approval → catatan menjelaskan kenapa belum tercentang.
        $admin = $this->makeUser('company_admin');
        $this->actingAs($admin)->post($this->tenantUrl('imports/opening-stock'), ['file' => $this->berkas([
            ['CKG', $this->binA->code, 'BAUT-OPN', 5, '', '', '', '', '', ''],
        ])])->assertSessionHasNoErrors();
        $adj = StockAdjustment::query()->sole();

        $saldo = collect(app(SetupWizard::class)->steps())->firstWhere('key', 'opening');
        $this->assertFalse($saldo['done']);
        $this->assertStringContainsString('1 penyesuaian saldo awal menunggu approval', $saldo['catatan']['teks']);
        $this->assertStringContainsString($adj->number, $saldo['catatan']['teks']);
        $this->assertStringEndsWith('/adjustments/'.$adj->id, (string) $saldo['catatan']['url']);
        $this->actingAs($admin)->get($this->tenantUrl('setup'))->assertOk()
            ->assertSee('menunggu approval Kepala Gudang')->assertSee('reason=OPENING', false);

        app(ApproveStockAdjustment::class)->approve($adj, $this->kepala);
        $saldo = collect(app(SetupWizard::class)->steps())->firstWhere('key', 'opening');
        $this->assertTrue($saldo['done']);
        $this->assertNull($saldo['catatan']);
    }

    #[Test]
    public function tc_adj_12b_impor_saldo_awal_tanpa_kode_bin_memakai_tempat_simpan(): void
    {
        $admin = $this->makeUser('company_admin');
        $this->b('B01')->forceFill(['capacity_qty' => 50])->save();
        app(SaveItemStorageLocations::class)->replace($this->baut, $this->gudang, [
            ['tempat' => 'bin:'.$this->b('B01')->id],
            ['tempat' => 'bin:'.$this->b('B02')->id],
        ], $this->kepala);

        // Dua baris tanpa bin: 40 muat di B01, 30 berikutnya pindah ke B02 (kapasitas B01 50).
        $this->actingAs($admin)->post($this->tenantUrl('imports/opening-stock'), ['file' => $this->berkas([
            ['CKG', '', 'BAUT-OPN', 40, '', '', '', '', '', ''],
            ['CKG', '', 'BAUT-OPN', 30, '', '', '', '', '', ''],
        ])])->assertSessionHasNoErrors();

        $adj = StockAdjustment::query()->sole();
        $this->assertSame([(int) $this->b('B01')->id, (int) $this->b('B02')->id], $adj->lines()->orderBy('id')->pluck('bin_id')->map(fn ($v) => (int) $v)->all());
        app(ApproveStockAdjustment::class)->approve($adj, $this->kepala);
        $this->assertSame(40.0, $this->saldoBin($this->b('B01'), $this->baut));
        $this->assertSame(30.0, $this->saldoBin($this->b('B02'), $this->baut));

        // Templat lama berjudul "Kode bin *" tetap terbaca.
        $judulLama = array_values(ImportOpeningStock::COLUMNS);
        $judulLama[1] = 'Kode bin *';
        $this->actingAs($admin)->post($this->tenantUrl('imports/opening-stock'), ['file' => $this->berkas([
            ['CKG', $this->binA->code, 'BAUT-OPN', 1, '', '', '', '', '', ''],
        ], $judulLama)])->assertSessionHasNoErrors();
        $this->assertSame((int) $this->binA->id, (int) StockAdjustment::query()->latest('id')->first()->lines()->sole()->bin_id);
    }

    #[Test]
    public function tc_adj_12c_impor_tanpa_kode_bin_galat_bila_tanpa_tempat_atau_penuh(): void
    {
        $admin = $this->makeUser('company_admin');
        $this->b('B01')->forceFill(['capacity_qty' => 10])->save();
        app(SaveItemStorageLocations::class)->replace($this->baut, $this->gudang, [['tempat' => 'bin:'.$this->b('B01')->id]], $this->kepala);
        // Bin khusus semen: baut tanpa bin tidak pernah diarahkan ke sana.
        app(SaveItemStorageLocations::class)->replace($this->semen, $this->gudang, [['tempat' => 'bin:'.$this->b('B03')->id, 'khusus' => true]], $this->kepala);

        $this->actingAs($admin)->post($this->tenantUrl('imports/opening-stock'), ['file' => $this->berkas([
            ['CKG', '', 'BAUT-OPN', 8, '', '', '', '', '', ''],
            ['CKG', '', 'BAUT-OPN', 5, '', '', '', '', '', ''],
            ['CKG', '', 'PIPA-OPN', '', '', '', '', '', 3, ''],
            ['CKG', $this->b('B03')->code, 'BAUT-OPN', 1, '', '', '', '', '', ''],
        ])])
            ->assertSessionHasErrors('file')
            ->assertSessionHas('rowErrors', fn ($t) => str_contains($t, 'Baris 3:') && str_contains($t, 'penuh')
                && str_contains($t, 'Baris 4:') && str_contains($t, 'belum punya tempat simpan')
                && str_contains($t, 'Baris 5:') && ! str_contains($t, 'Baris 2:'));
        $this->assertSame(0, StockAdjustment::query()->count());

        // Bin tergabung tidak dipakai: gabung B02 ke B01 → kapasitas gabungan.
        $this->b('B02')->forceFill(['capacity_qty' => 10])->save();
        app(MergeBins::class)->merge($this->b('B01'), [$this->b('B02')->id], 'side', 'temporary', 'Palet panjang', $this->kepala);
        $this->actingAs($admin)->post($this->tenantUrl('imports/opening-stock'), ['file' => $this->berkas([
            ['CKG', '', 'BAUT-OPN', 8, '', '', '', '', '', ''],
            ['CKG', '', 'BAUT-OPN', 5, '', '', '', '', '', ''],
        ])])->assertSessionHasNoErrors();
        $this->assertSame([(int) $this->b('B01')->id], StockAdjustment::query()->sole()->lines()->pluck('bin_id')->map(fn ($v) => (int) $v)->unique()->values()->all());
    }

    #[Test]
    public function tc_stk_38_kolom_lokasi_di_saldo_stok(): void
    {
        $masuk = fn (Bin $bin, float $qty) => app(StockLedger::class)->post(new MovementRequest(item: $this->baut, qtyBase: $qty, toBinId: $bin->id));
        $masuk($this->b('B01'), 5);
        $masuk($this->b('B02'), 30);
        $masuk($this->b('B03'), 1);

        // binA sudah 100 (fixture) → urut terbanyak: binA, B02, lalu +2 bin lain.
        Livewire::actingAs($this->kepala)->test(BalanceList::class)
            ->assertSee('Lokasi')
            ->assertSeeInOrder(['BAUT-OPN', 'R01 · L1 · 01', 'R01 · L1 · 02'])
            ->assertSee('+2 bin lain')
            ->assertSee('binFilter='.$this->binA->id, false);

        // Item dengan satu bin: tanpa "+n".
        Livewire::actingAs($this->kepala)->test(BalanceList::class)->set('search', 'SEMEN-OPN')
            ->assertSee('R01 · L1 · 02')
            ->assertDontSee('bin lain');
    }
}

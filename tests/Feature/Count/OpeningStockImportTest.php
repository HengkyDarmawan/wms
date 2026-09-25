<?php

declare(strict_types=1);

namespace Tests\Feature\Count;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Adjustment\Actions\ApproveStockAdjustment;
use App\Domain\Adjustment\Actions\ImportOpeningStock;
use App\Domain\Adjustment\Enums\StockAdjustmentStatus;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Master\Actions\ImportVendors;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Enums\VendorType;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Vendor;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\WarehouseType;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Count\Concerns\CountFixtures;
use Tests\TenantTestCase;

/**
 * TC-MST-24 impor vendor dan TC-ADJ-12 impor saldo awal dari Excel (A-192,
 * A-207): semua-atau-tidak, aturan sama dengan form, saldo awal lewat ADJ
 * manual yang menunggu approval (P-01, A-09).
 */
class OpeningStockImportTest extends TenantTestCase
{
    use CountFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanOpname();
    }

    /**
     * @param  array<string, string>  $kolom
     * @param  array<int, array<int, mixed>>  $baris
     */
    private function berkas(array $kolom, array $baris): UploadedFile
    {
        $buku = new Spreadsheet;
        $buku->getActiveSheet()->fromArray(array_merge([array_values($kolom)], $baris));
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($buku))->save($path);

        return new UploadedFile($path, 'impor.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    #[Test]
    public function tc_mst_24_impor_vendor_semua_atau_tidak(): void
    {
        $admin = $this->makeUser('company_admin');

        $this->actingAs($admin)->get($this->tenantUrl('imports'))->assertOk()->assertSee(__('Vendor'))->assertSee(__('Saldo awal stok'));
        $this->actingAs($admin)->get($this->tenantUrl('imports/vendors/template'))->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->actingAs($this->makeUser('warehouse_staff'))->get($this->tenantUrl('imports/vendors/template'))->assertForbidden();

        // Vendor aktif tanpa kontak (A-53), jenis asing, dan email salah: tidak ada yang tersimpan.
        $salah = $this->berkas(ImportVendors::COLUMNS, [
            ['VND-IMP-1', 'CV Baja Impor', 'company', 'active', '', '', '0811', '', '', '30 hari'],
            ['VND-IMP-2', 'Toko Tanpa Kontak', 'shop', 'active', '', '', '', '', '', ''],
            ['VND-IMP-3', 'Toko Aneh', 'grosir', '', '', '', '0812', '', '', ''],
            ['VND-IMP-4', 'Toko Email', 'shop', '', '', '', '', 'bukan-email', '', ''],
        ]);
        $this->actingAs($admin)->post($this->tenantUrl('imports/vendors'), ['file' => $salah])
            ->assertSessionHasErrors('file')
            ->assertSessionHas('rowErrors', fn ($t) => str_contains($t, 'Baris 3') && str_contains($t, 'Baris 4') && str_contains($t, 'Baris 5') && ! str_contains($t, 'Baris 2'));
        $this->assertSame(0, Vendor::query()->where('code', 'like', 'VND-IMP-%')->count());

        $benar = $this->berkas(ImportVendors::COLUMNS, [
            ['VND-IMP-1', 'CV Baja Impor', 'company', 'active', '01.234', 'Budi', '0811', '', 'Bekasi', '30 hari'],
            ['', 'Toko Online Sumber', 'Toko online', 'Sementara', '', '', '', '', '', ''],
        ]);
        $this->actingAs($admin)->post($this->tenantUrl('imports/vendors'), ['file' => $benar])
            ->assertSessionHasNoErrors()->assertRedirect(route('vendors.index'));

        $baja = Vendor::query()->where('code', 'VND-IMP-1')->sole();
        $this->assertSame('30 hari', $baja->payment_terms);
        $toko = Vendor::query()->where('name', 'Toko Online Sumber')->sole();
        $this->assertSame(VendorType::OnlineMarketplace, $toko->vendor_type);
        $this->assertSame(VendorStatus::Provisional, $toko->status);
        $this->assertSame('TOKO-ONLINE-SUMBER', $toko->code, 'Kode kosong dibuat dari nama.');

        // Kode yang sudah ada ditolak (impor hanya menambah).
        $this->actingAs($admin)->post($this->tenantUrl('imports/vendors'), ['file' => $this->berkas(ImportVendors::COLUMNS, [['VND-IMP-1', 'Ganda', '', '', '', '', '0811']])])
            ->assertSessionHas('rowErrors', fn ($t) => str_contains($t, 'VND-IMP-1'));
    }

    #[Test]
    public function tc_adj_12_impor_saldo_awal_menjadi_adj_per_gudang_menunggu_approval(): void
    {
        $admin = $this->makeUser('company_admin');
        $bks = app(SaveWarehouse::class)->handle(null, [
            'code' => 'BKS',
            'name' => 'Gudang Bekasi',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);
        $binBks = Bin::create(['warehouse_id' => $bks->id, 'code' => 'BKS-A-01', 'bin_type' => BinType::Storage]);
        $kepalaBks = $this->makeUser('warehouse_head', ScopeType::Warehouse, $bks->id);

        $this->actingAs($admin)->get($this->tenantUrl('imports/opening-stock/template'))->assertOk();
        $this->actingAs($this->makeUser('driver'))->get($this->tenantUrl('imports/opening-stock/template'))->assertForbidden();

        // Bin salah gudang, lot baru tanpa kedaluwarsa (BR-STK-12), serial ganda di berkas (BR-LED-04),
        // item tak dikenal, dan kondisi asing — semua dilaporkan, tidak ada ADJ.
        $salah = $this->berkas(ImportOpeningStock::COLUMNS, [
            ['CKG', 'CKG-A-R01-L1-B01', 'BAUT-OPN', 40, '', '', '', '', '', ''],
            ['CKG', 'BKS-A-01', 'BAUT-OPN', 5, '', '', '', '', '', ''],
            ['CKG', 'CKG-A-R01-L1-B02', 'SEMEN-OPN', 10, '', 'LOT-BARU', '', '', '', ''],
            ['CKG', 'CKG-A-R01-L1-B02', 'GENSET-OPN', 1, '', '', '', 'GNS-IMP-1', '', ''],
            ['CKG', 'CKG-A-R01-L1-B02', 'GENSET-OPN', 1, '', '', '', 'gns-imp-1', '', ''],
            ['CKG', 'CKG-A-R01-L1-B01', 'TIDAK-ADA', 1, '', '', '', '', '', ''],
            ['CKG', 'CKG-A-R01-L1-B01', 'BAUT-OPN', 1, 'hilang', '', '', '', '', ''],
        ]);
        $this->actingAs($admin)->post($this->tenantUrl('imports/opening-stock'), ['file' => $salah])
            ->assertSessionHasErrors('file')
            ->assertSessionHas('rowErrors', fn ($t) => collect([3, 4, 6, 7, 8])->every(fn ($n) => str_contains($t, 'Baris '.$n.':'))
                && ! str_contains($t, 'Baris 2:') && ! str_contains($t, 'Baris 5:'));
        $this->assertSame(0, StockAdjustment::query()->count());

        $benar = $this->berkas(ImportOpeningStock::COLUMNS, [
            ['CKG', 'ckg-a-r01-l1-b01', 'BAUT-OPN', 40, '', '', '', '', '', 'Hitung pembukaan'],
            ['CKG', 'CKG-A-R01-L1-B01', 'BAUT-OPN', 2, 'rusak', '', '', '', '', ''],
            ['CKG', 'CKG-A-R01-L1-B02', 'SEMEN-OPN', 10, 'Tersedia', 'LOT-IMP-1', now()->addYear()->toDateString(), '', '', ''],
            ['CKG', 'CKG-A-R01-L1-B02', 'GENSET-OPN', 1, '', '', '', 'GNS-IMP-1', '', ''],
            ['CKG', 'CKG-A-R01-L1-B01', 'PIPA-OPN', '', '', '', '', '', 4.5, ''],
            ['BKS', 'BKS-A-01', 'BAUT-OPN', 15, '', '', '', '', '', ''],
        ]);
        $this->actingAs($admin)->post($this->tenantUrl('imports/opening-stock'), ['file' => $benar])
            ->assertSessionHasNoErrors()->assertRedirect(route('adjustments.index'));

        $adj = StockAdjustment::query()->orderBy('id')->get();
        $this->assertCount(2, $adj, 'Satu ADJ per gudang.');
        $this->assertTrue($adj->every(fn (StockAdjustment $a) => $a->status === StockAdjustmentStatus::PendingApproval), 'Saldo awal tetap lewat approval (A-09).');
        $this->assertSame('OPENING', $adj->first()->reason->code);
        $this->assertSame(100.0, $this->saldoBin($this->binA, $this->baut), 'Belum ada stok bergerak sebelum disetujui (P-01).');

        $ckg = $adj->firstWhere('warehouse_id', $this->gudang->id);
        app(ApproveStockAdjustment::class)->approve($ckg, $this->kepala);
        app(ApproveStockAdjustment::class)->approve($adj->firstWhere('warehouse_id', $bks->id), $kepalaBks);

        $this->assertSame(StockAdjustmentStatus::Posted, $ckg->refresh()->status);
        $this->assertSame(140.0, $this->saldoBin($this->binA, $this->baut));
        $this->assertSame(2.0, $this->saldoBin($this->binA, $this->baut, StockStatus::Damaged));
        $this->assertSame(60.0, $this->saldoBin($this->binB, $this->semen));
        $this->assertNotNull(Lot::query()->where('item_id', $this->semen->id)->where('lot_no', 'LOT-IMP-1')->first());
        $this->assertSame(2.0, $this->saldoBin($this->binB, $this->genset));
        $this->assertSame(10.5, $this->saldoBin($this->binA, $this->pipa));
        $this->assertSame(15.0, $this->saldoBin($binBks, $this->baut));
    }
}

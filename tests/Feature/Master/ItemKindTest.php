<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Domain\Master\Actions\ImportItems;
use App\Domain\Master\Actions\SaveItem;
use App\Domain\Master\Enums\ItemKind;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\LineOwnership;
use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\RemovalStrategy;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Enums\VendorType;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Livewire\ItemDetail;
use App\Domain\Master\Livewire\ItemForm;
use App\Domain\Master\Livewire\ItemList;
use App\Domain\Master\Models\FeatureSetting;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\ItemVendor;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Models\Vendor;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\WarehouseType;
use Database\Seeders\Tenant\MasterReferenceSeeder;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-MST-30 s.d. TC-MST-35 — tiga jenis barang dan saklar fitur company
 * (A-283, A-284, BR-GEN-12, BR-MST-06).
 */
class ItemKindTest extends TenantTestCase
{
    private function uomId(string $code): int
    {
        return (int) Uom::query()->where('code', $code)->value('id');
    }

    /** @param  array<string, mixed>  $override */
    private function simpanJenis(ItemKind $jenis, array $override = [], ?Item $item = null): Item
    {
        return app(SaveItem::class)->handle($item, array_merge([
            'code' => $item?->code ?? 'JNS-'.strtoupper(substr(uniqid(), -6)),
            'name' => $item?->name ?? 'Item jenis',
            'base_uom_id' => $item?->base_uom_id ?? $this->uomId('PCS'),
            'item_kind' => $jenis->value,
        ], $override));
    }

    private function tolak(callable $aksi, string $aturan): MasterRuleException
    {
        try {
            $aksi();
        } catch (MasterRuleException $e) {
            $this->assertSame($aturan, $e->rule, $e->getMessage());

            return $e;
        }

        $this->fail('Seharusnya ditolak '.$aturan.'.');
    }

    private function beriStok(Item $item): void
    {
        $gudang = app(SaveWarehouse::class)->handle(null, [
            'code' => 'JNS',
            'name' => 'Gudang jenis',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);
        $bin = Bin::create(['warehouse_id' => $gudang->id, 'code' => 'JNS-A-R01-L1-B01', 'bin_type' => BinType::Storage]);

        app(StockLedger::class)->post(new MovementRequest(item: $item, qtyBase: 5, toBinId: $bin->id));
    }

    /** @param  array<int, array<int, mixed>>  $baris */
    private function berkas(array $judul, array $baris): UploadedFile
    {
        $buku = new Spreadsheet;
        $buku->getActiveSheet()->fromArray(array_merge([$judul], $baris));
        $path = tempnam(sys_get_temp_dir(), 'jns').'.xlsx';
        (new Xlsx($buku))->save($path);

        return new UploadedFile($path, 'item.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    #[Test]
    public function tc_mst_30_jenis_barang_diturunkan_dari_kolom_teknis(): void
    {
        $this->assertSame(ItemKind::Standard, ItemKind::classify(TrackingMode::None, OwnershipModel::Consumable, false));
        $this->assertSame(ItemKind::Expiring, ItemKind::classify(TrackingMode::Lot, OwnershipModel::Consumable, true));
        $this->assertSame(ItemKind::SerialTool, ItemKind::classify(TrackingMode::Serial, OwnershipModel::Asset, false));

        // Kombinasi lain = Jenis khusus.
        $this->assertNull(ItemKind::classify(TrackingMode::Piece, OwnershipModel::Consumable, false));
        $this->assertNull(ItemKind::classify(TrackingMode::Serial, OwnershipModel::Both, false));
        $this->assertNull(ItemKind::classify(TrackingMode::Serial, OwnershipModel::Consumable, false));
        $this->assertNull(ItemKind::classify(TrackingMode::Serial, OwnershipModel::Asset, true));
        $this->assertNull(ItemKind::classify(TrackingMode::Lot, OwnershipModel::Consumable, false));

        $this->assertSame(RemovalStrategy::Fefo, ItemKind::Expiring->defaultStrategy(true));
        $this->assertSame(RemovalStrategy::Fifo, ItemKind::Expiring->defaultStrategy(false));
        $this->assertSame(RemovalStrategy::Manual, ItemKind::SerialTool->defaultStrategy(true));

        $this->assertSame(ItemKind::Standard, ItemKind::fromImport(' Biasa '));
        $this->assertSame(ItemKind::Expiring, ItemKind::fromImport('kedaluwarsa'));
        $this->assertSame(ItemKind::SerialTool, ItemKind::fromImport('ALAT'));
        $this->assertNull(ItemKind::fromImport('mesin'));
    }

    #[Test]
    public function tc_mst_31_simpan_item_lewat_jenis_barang_dan_saklar(): void
    {
        $biasa = $this->simpanJenis(ItemKind::Standard);
        $this->assertSame([TrackingMode::None, OwnershipModel::Consumable, false, RemovalStrategy::Fifo, null],
            [$biasa->tracking_mode, $biasa->ownership_model, $biasa->has_expiry, $biasa->removal_strategy, $biasa->default_line_ownership]);

        $semen = $this->simpanJenis(ItemKind::Expiring);
        $this->assertSame([TrackingMode::Lot, OwnershipModel::Consumable, true, RemovalStrategy::Fefo],
            [$semen->tracking_mode, $semen->ownership_model, $semen->has_expiry, $semen->removal_strategy]);

        $genset = $this->simpanJenis(ItemKind::SerialTool);
        $this->assertSame([TrackingMode::Serial, OwnershipModel::Asset, RemovalStrategy::Manual],
            [$genset->tracking_mode, $genset->ownership_model, $genset->removal_strategy]);
        $this->assertSame('loan', $genset->defaultLineOwnershipValue());

        // Strategi lama yang masih sah dipertahankan saat disimpan ulang.
        $semen->forceFill(['removal_strategy' => RemovalStrategy::Fifo])->save();
        $this->assertSame(RemovalStrategy::Fifo, $this->simpanJenis(ItemKind::Expiring, ['name' => 'Semen ulang'], $semen->refresh())->removal_strategy);

        // Saklar mati: jenisnya tidak bisa dipilih untuk item baru (BR-GEN-12).
        FeatureSetting::toggle('serial', false);
        $this->tolak(fn () => $this->simpanJenis(ItemKind::SerialTool), 'BR-GEN-12');
        $this->simpanJenis(ItemKind::SerialTool, ['name' => 'Genset ulang'], $genset->refresh()); // item lama tetap bisa disimpan
        FeatureSetting::toggle('serial', true);

        FeatureSetting::toggle('expiry', false);
        $this->tolak(fn () => $this->simpanJenis(ItemKind::Expiring), 'BR-GEN-12');
        FeatureSetting::toggle('expiry', true);

        FeatureSetting::toggle('fefo', false);
        $this->assertSame(RemovalStrategy::Fifo, $this->simpanJenis(ItemKind::Expiring)->removal_strategy);
        FeatureSetting::toggle('fefo', true);

        // Jalur kolom teknis: item per potong baru ditolak selama saklar per potong mati (bawaan).
        $e = $this->tolak(fn () => app(SaveItem::class)->handle(null, [
            'code' => 'PIPA-TEK', 'name' => 'Pipa teknis', 'base_uom_id' => $this->uomId('M'),
            'tracking_mode' => 'piece', 'ownership_model' => 'consumable',
        ]), 'BR-GEN-12');
        $this->assertStringContainsString('piece', $e->fieldErrors['tracking_mode']);

        // BR-MST-06: jenis terkunci setelah ada pergerakan stok.
        $this->beriStok($biasa);
        $this->tolak(fn () => $this->simpanJenis(ItemKind::Expiring, [], $biasa->refresh()), 'BR-MST-06');
        $this->assertSame('Nama baru', $this->simpanJenis(ItemKind::Standard, ['name' => 'Nama baru'], $biasa->refresh())->name);
    }

    #[Test]
    public function tc_mst_32_form_item_hanya_menampilkan_jenis_barang(): void
    {
        $this->actingAs($this->makeUser('company_admin'));

        Livewire::test(ItemForm::class)
            ->assertSee('Barang biasa')->assertSee('Barang berkedaluwarsa')->assertSee('Alat bernomor seri')
            ->assertSee('Contoh: genset')
            ->assertDontSee(__('Mode pelacakan'))->assertDontSee(__('Model kepemilikan'))
            ->assertDontSee(__('Bisa dipotong'))->assertDontSee(__('Vendor tetap'))
            ->assertDontSee(__('Wajib melewati QC saat diterima'))
            ->set('form.code', 'FRM-ALAT')->set('form.name', 'Mesin las')->set('form.base_uom_id', (string) $this->uomId('PCS'))
            ->set('form.item_kind', 'serial_tool')
            ->call('simpan')->assertHasNoErrors();

        $las = Item::query()->where('code', 'FRM-ALAT')->sole();
        $this->assertSame(ItemKind::SerialTool, ItemKind::fromItem($las));

        // Saklar serial mati → kartu Alat hilang dan tidak bisa dikirim.
        FeatureSetting::toggle('serial', false);
        Livewire::test(ItemForm::class)->assertDontSee('Alat bernomor seri')
            ->set('form.code', 'FRM-X')->set('form.name', 'X')->set('form.base_uom_id', (string) $this->uomId('PCS'))
            ->set('form.item_kind', 'serial_tool')->call('simpan')->assertHasErrors('form.item_kind');
        FeatureSetting::toggle('serial', true);

        // Saklar QC menyala → centang Wajib QC tampil.
        FeatureSetting::toggle('qc', true);
        Livewire::test(ItemForm::class)->assertSee(__('Wajib melewati QC saat diterima'));

        // Item lama Keduanya = Jenis khusus: teknis read-only, disimpan tanpa berubah; vendor tetap tidak disentuh.
        $vendor = Vendor::create(['code' => 'V-JNS', 'name' => 'Vendor jenis', 'vendor_type' => VendorType::Company, 'status' => VendorStatus::Active, 'is_active' => true]);
        $bor = Item::create([
            'code' => 'BOR-LAMA', 'name' => 'Bor lama', 'status' => ItemStatus::Active, 'tracking_mode' => TrackingMode::Serial,
            'ownership_model' => OwnershipModel::Both, 'default_line_ownership' => LineOwnership::Loan,
            'removal_strategy' => RemovalStrategy::Manual, 'base_uom_id' => $this->uomId('PCS'),
        ]);
        ItemVendor::create(['item_id' => $bor->id, 'vendor_id' => $vendor->id, 'priority' => 1, 'is_preferred' => true]);

        Livewire::test(ItemForm::class, ['item' => $bor])
            ->assertSee('Jenis khusus')->assertSee(OwnershipModel::Both->label())
            ->assertDontSee('Barang berkedaluwarsa')
            ->set('form.name', 'Bor lama diubah')
            ->call('simpan')->assertHasNoErrors();

        $bor->refresh();
        $this->assertSame('Bor lama diubah', $bor->name);
        $this->assertSame([TrackingMode::Serial, OwnershipModel::Both, LineOwnership::Loan, RemovalStrategy::Manual],
            [$bor->tracking_mode, $bor->ownership_model, $bor->default_line_ownership, $bor->removal_strategy]);
        $this->assertSame(1, $bor->itemVendors()->count(), 'Vendor tetap tidak dihapus saat form disimpan.');

        // Item bersaldo: kartu jenis terkunci.
        $paku = $this->simpanJenis(ItemKind::Standard, ['code' => 'FRM-PAKU', 'name' => 'Paku']);
        $this->beriStok($paku);
        Livewire::test(ItemForm::class, ['item' => $paku])
            ->assertSet('jenisTerkunci', true)
            ->assertSee(__('Terkunci karena item ini sudah punya pergerakan stok.'));
    }

    #[Test]
    public function tc_mst_33_saklar_bawaan_company_baru_dan_seed_ulang(): void
    {
        FeatureSetting::query()->delete();
        (new MasterReferenceSeeder)->run();

        foreach (['lot' => true, 'serial' => true, 'expiry' => true, 'fefo' => true, 'piece' => false, 'qc' => false] as $kunci => $nyala) {
            $this->assertSame($nyala, FeatureSetting::enabled($kunci), 'Bawaan '.$kunci);
        }

        // Seed ulang tidak mengembalikan pilihan company ke bawaan.
        FeatureSetting::toggle('piece', true);
        (new MasterReferenceSeeder)->run();
        $this->assertTrue(FeatureSetting::enabled('piece'));
    }

    #[Test]
    public function tc_mst_34_impor_item_memakai_kolom_jenis_barang(): void
    {
        $this->assertArrayHasKey('jenis_barang', ImportItems::COLUMNS);
        $this->assertArrayNotHasKey('pelacakan', ImportItems::COLUMNS);

        $admin = $this->makeUser('company_admin');
        $judul = array_values(ImportItems::COLUMNS);

        app(ImportItems::class)->handle($this->berkas($judul, [
            ['IMP-SMN', 'Semen impor', '', 'KG', 'kedaluwarsa', '', '', 'tidak', ''],
            ['IMP-GNS', 'Genset impor', '', 'PCS', 'alat', '', '', 'tidak', ''],
            ['IMP-BAUT', 'Baut impor', '', 'PCS', '', '', '', 'tidak', ''],
        ]), $admin);

        $this->assertSame(ItemKind::Expiring, ItemKind::fromItem(Item::query()->where('code', 'IMP-SMN')->sole()));
        $this->assertSame(ItemKind::SerialTool, ItemKind::fromItem(Item::query()->where('code', 'IMP-GNS')->sole()));
        $this->assertSame(ItemKind::Standard, ItemKind::fromItem(Item::query()->where('code', 'IMP-BAUT')->sole()), 'Kosong = Barang biasa.');

        // Berkas templat lama (kolom teknis) tetap diterima.
        app(ImportItems::class)->handle($this->berkas(
            ['Kode *', 'Nama *', 'Kode kategori', 'Kode satuan dasar *', 'Pelacakan: none/lot/serial/piece', 'Kedaluwarsa: ya/tidak', 'Kepemilikan: consumable/asset/both', 'Strategi: fifo/fefo/manual/offcut_first'],
            [['IMP-LAMA', 'Cat lama', '', 'PCS', 'lot', 'ya', 'consumable', 'fefo']],
        ), $admin);
        $this->assertSame(ItemKind::Expiring, ItemKind::fromItem(Item::query()->where('code', 'IMP-LAMA')->sole()));
    }

    #[Test]
    public function tc_mst_35_daftar_dan_detail_item_mengikuti_jenis_dan_saklar(): void
    {
        $this->actingAs($this->makeUser('company_admin'));

        $baut = $this->simpanJenis(ItemKind::Standard, ['code' => 'LST-BAUT', 'name' => 'Baut daftar']);
        $this->simpanJenis(ItemKind::Expiring, ['code' => 'LST-SEMEN', 'name' => 'Semen daftar']);
        $pipa = Item::create([
            'code' => 'LST-PIPA', 'name' => 'Pipa lama', 'status' => ItemStatus::Active, 'tracking_mode' => TrackingMode::Piece,
            'ownership_model' => OwnershipModel::Consumable, 'base_uom_id' => $this->uomId('M'),
        ]);

        Livewire::test(ItemList::class)
            ->assertSee(__('Daftar barang beserta jenis dan satuan dasarnya.'))->assertDontSee(__('mode pelacakan'))
            ->assertSee('Jenis khusus')
            ->set('kindFilter', 'expiring')->assertSee('Semen daftar')->assertDontSee('Baut daftar')
            ->set('kindFilter', 'khusus')->assertSee('Pipa lama')->assertDontSee('Semen daftar');

        // Saklar per potong mati (bawaan): tab Potongan tidak ada dan tidak bisa dibuka.
        Livewire::test(ItemDetail::class, ['item' => $pipa])
            ->assertSee('Jenis khusus')->assertDontSee(__('Potongan'))
            ->call('pilihTab', 'potongan')->assertSet('tab', 'ringkasan');

        Livewire::test(ItemDetail::class, ['item' => $baut])->assertSee('Barang biasa')->assertDontSee(__('Mode pelacakan'));

        FeatureSetting::toggle('piece', true);
        Livewire::test(ItemDetail::class, ['item' => $pipa])
            ->assertSee(__('Potongan'))
            ->call('pilihTab', 'potongan')->assertSet('tab', 'potongan');
    }
}

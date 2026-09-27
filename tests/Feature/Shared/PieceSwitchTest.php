<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\FeatureSetting;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\Uom;
use App\Domain\Shared\Reports\ReportRegistry;
use App\Domain\Stock\Livewire\BalanceList;
use App\Domain\Stock\Livewire\StockCard;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Livewire\LabelPrint;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\WarehouseType;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-TPL-26, TC-STK-36, TC-RPT-11 — layar khusus potongan mengikuti saklar
 * per potong; stok item lama per potong tetap berjalan (A-284, P-03).
 */
class PieceSwitchTest extends TenantTestCase
{
    private Item $pipa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->makeUser('company_admin'));

        $gudang = app(SaveWarehouse::class)->handle(null, [
            'code' => 'PTG',
            'name' => 'Gudang potong',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);
        $bin = Bin::create(['warehouse_id' => $gudang->id, 'code' => 'PTG-A-R01-L1-B01', 'bin_type' => BinType::Storage]);

        // Item lama per potong di company yang saklar per potongnya mati (bawaan).
        $this->pipa = Item::create([
            'code' => 'PIPA-LAMA', 'name' => 'Pipa lama', 'status' => ItemStatus::Active, 'tracking_mode' => TrackingMode::Piece,
            'ownership_model' => OwnershipModel::Consumable, 'base_uom_id' => Uom::query()->where('code', 'M')->value('id'),
        ]);
        $potongan = Piece::create(['item_id' => $this->pipa->id, 'piece_no' => 'P-LAMA-1', 'length' => 6, 'is_offcut' => false]);

        // P-03: pergerakan stok item lama tetap diterima walau saklarnya mati.
        app(StockLedger::class)->post(new MovementRequest(item: $this->pipa, qtyBase: 6, toBinId: $bin->id, pieceId: $potongan->id));
    }

    #[Test]
    public function tc_tpl_26_label_potongan_mengikuti_saklar(): void
    {
        $this->assertNotContains(DocumentTemplateType::LabelPiece, DocumentTemplateType::labels(false));

        Livewire::withQueryParams(['type' => 'label_piece'])->test(LabelPrint::class)
            ->assertSet('type', 'label_bin')
            ->assertDontSee(__('Label potongan'));

        FeatureSetting::toggle('piece', true);

        Livewire::withQueryParams(['type' => 'label_piece'])->test(LabelPrint::class)
            ->assertSet('type', 'label_piece')
            ->assertSee('P-LAMA-1');
    }

    #[Test]
    public function tc_stk_36_kolom_potongan_mengikuti_saklar_dan_stok_lama_tetap_jalan(): void
    {
        Livewire::test(BalanceList::class)
            ->assertSee('Pipa lama')->assertDontSee(__('Potongan'));
        Livewire::test(StockCard::class, ['item' => $this->pipa])
            ->assertDontSee(__('Potongan'));

        FeatureSetting::toggle('piece', true);

        Livewire::test(BalanceList::class)->assertSee(__('Potongan'));
        Livewire::test(StockCard::class, ['item' => $this->pipa])->assertSee(__('Potongan'));
    }

    #[Test]
    public function tc_rpt_11_kolom_potong_di_laporan_mengikuti_saklar(): void
    {
        $laporan = app(ReportRegistry::class);

        $this->assertArrayNotHasKey('potong', $laporan->find('saldo-stok')->columns());
        $this->assertSame('Lot / serial', $laporan->find('saldo-stok')->columns()['pelacakan']);
        $this->assertArrayNotHasKey('offcut', $laporan->find('konversi-waste')->columns());
        $this->assertArrayNotHasKey('kerf', $laporan->find('konversi-waste')->columns());
        $this->assertArrayNotHasKey('offcut', $laporan->find('retur-per-proyek')->columns());

        // Saldo item lama per potong tetap terbaca di laporan.
        $this->assertTrue($laporan->find('saldo-stok')->rows([])->contains(fn (array $r) => $r['kode_item'] === 'PIPA-LAMA'));

        FeatureSetting::toggle('piece', true);

        $this->assertArrayHasKey('potong', $laporan->find('saldo-stok')->columns());
        $this->assertArrayHasKey('kerf', $laporan->find('konversi-waste')->columns());
        $this->assertArrayHasKey('offcut', $laporan->find('retur-per-proyek')->columns());
    }
}

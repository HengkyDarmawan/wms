<?php

declare(strict_types=1);

namespace Tests\Feature\Shipment;

use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\RemovalStrategy;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Uom;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Shipment\Actions\CreatePickTask;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Shipment\Livewire\PickDetail;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * TC-PCK-12 s.d. TC-PCK-15 — alokasi keras PCK mengikuti strategi pengambilan
 * item: FEFO, FIFO, sisa potongan dulu, manual; lot kedaluwarsa dilewati
 * (BR-STK-04, BR-STK-10, BR-STK-12, A-185).
 */
class RemovalStrategyTest extends TenantTestCase
{
    private Project $proyek;

    private Warehouse $gudang;

    /** @var array<string, Bin> */
    private array $bin = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->proyek = $this->makeProject();
        $this->gudang = app(SaveWarehouse::class)->handle(null, [
            'code' => 'CKG', 'name' => 'Gudang Utama Cakung',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);

        foreach (['A', 'B', 'C'] as $k) {
            $this->bin[$k] = Bin::create(['warehouse_id' => $this->gudang->id, 'code' => 'CKG-'.$k, 'bin_type' => BinType::Storage]);
        }
    }

    private function item(string $kode, TrackingMode $mode, ?RemovalStrategy $strategi, array $extra = []): Item
    {
        return Item::create($extra + [
            'code' => $kode, 'name' => $kode, 'status' => ItemStatus::Active, 'tracking_mode' => $mode,
            'removal_strategy' => $strategi,
            'base_uom_id' => Uom::query()->where('code', $mode === TrackingMode::Piece ? 'M' : 'PCS')->value('id'),
        ]);
    }

    private function masuk(Item $item, string $bin, float $qty, array $turunan = []): void
    {
        app(StockLedger::class)->post(new MovementRequest(
            item: $item, qtyBase: $qty, toBinId: $this->bin[$bin]->id,
            lotId: $turunan['lot_id'] ?? null, pieceId: $turunan['piece_id'] ?? null,
        ));
    }

    private function pck(Item $item, float $qty): PickTask
    {
        $pemohon = $this->makeUser('internal_requester');
        $req = app(SaveRequest::class)->handle(null,
            ['project_id' => $this->proyek->id, 'required_date' => now()->addDays(3)->toDateString()],
            [['item_id' => $item->id, 'qty_base' => $qty]], $pemohon);
        $req->openLines()->first()->forceFill(['source_warehouse_id' => $this->gudang->id, 'fulfillment_source' => 'stock'])->save();
        $req = app(SubmitRequest::class)->handle($req->refresh(), $pemohon);

        return app(CreatePickTask::class)->handle($req, $this->makeUser('warehouse_head'))[0];
    }

    /** @return array<int, array{0: string, 1: float}> [kode bin, jumlah] urut baris */
    private function alokasi(PickTask $pck): array
    {
        return $pck->lines()->with('bin:id,code')->orderBy('id')->get()
            ->map(fn ($l) => [$l->bin->code, (float) $l->qty_allocated])->all();
    }

    #[Test]
    public function tc_pck_12_fefo_kedaluwarsa_terdekat_dulu_dan_yang_lewat_dilewati(): void
    {
        $semen = $this->item('SEMEN', TrackingMode::Lot, RemovalStrategy::Fefo, ['has_expiry' => true]);
        $lot = fn (string $no, ?string $exp, string $terima) => Lot::create(['item_id' => $semen->id, 'lot_no' => $no, 'expiry_date' => $exp, 'received_at' => $terima]);

        $this->masuk($semen, 'A', 10, ['lot_id' => $lot('L-JAUH', now()->addMonths(6)->toDateString(), now()->subMonths(3)->toDateString())->id]);
        $this->masuk($semen, 'B', 10, ['lot_id' => $lot('L-DEKAT', now()->addMonth()->toDateString(), now()->subMonth()->toDateString())->id]);
        $this->masuk($semen, 'C', 10, ['lot_id' => $lot('L-LEWAT', now()->subDay()->toDateString(), now()->subMonths(5)->toDateString())->id]);

        $this->assertSame([['CKG-B', 10.0], ['CKG-A', 5.0]], $this->alokasi($this->pck($semen, 15)));

        // Stok tidak kedaluwarsa tinggal 5: permintaan 10 ditolak walau lot lewat masih ada.
        $this->expectException(ShipmentRuleException::class);
        $this->pck($semen, 10);
    }

    #[Test]
    public function tc_pck_18_pindai_item_berlacak_wajib_nomor_lot(): void
    {
        $semen = $this->item('SEMEN', TrackingMode::Lot, RemovalStrategy::Fifo);
        $lot = Lot::create(['item_id' => $semen->id, 'lot_no' => 'L-0925', 'received_at' => now()->subWeek()->toDateString()]);
        $this->masuk($semen, 'A', 10, ['lot_id' => $lot->id]);
        $pck = $this->pck($semen, 4);
        $staf = $this->makeUser('warehouse_staff');
        $staf->forgetPermissionCache();

        // A-203: kode item saja ditolak untuk item ber-lot; nomor lot mencatat barisnya.
        Livewire::actingAs($staf)->test(PickDetail::class, ['pickTask' => $pck])
            ->call('mulai')
            ->set('kodePindai', 'SEMEN')->call('pindai')
            ->assertHasErrors('kodePindai')
            ->set('kodePindai', 'l-0925')->call('pindai')
            ->assertHasNoErrors()
            ->assertSet('ruleError', '');

        $this->assertNotNull($pck->lines()->sole()->scanned_at);
    }

    #[Test]
    public function tc_pck_13_fifo_yang_paling_lama_masuk_dulu(): void
    {
        $baut = $this->item('BAUT', TrackingMode::None, RemovalStrategy::Fifo);
        $this->masuk($baut, 'A', 10);
        $this->masuk($baut, 'C', 10);

        // Saldo di bin C tercatat lebih dulu: FIFO mendahulukannya walau kode bin lebih besar.
        DB::connection('tenant')->table('stock_balances')->where('item_id', $baut->id)->where('bin_id', $this->bin['C']->id)
            ->update(['created_at' => now()->subWeek()]);

        $this->assertSame([['CKG-C', 10.0], ['CKG-A', 2.0]], $this->alokasi($this->pck($baut, 12)));
    }

    #[Test]
    public function tc_pck_14_sisa_potongan_didahulukan(): void
    {
        $pipa = $this->item('PIPA', TrackingMode::Piece, null);
        $this->assertSame(RemovalStrategy::OffcutFirst, $pipa->effectiveRemovalStrategy());

        $utuh = Piece::create(['item_id' => $pipa->id, 'piece_no' => Piece::nextPieceNo(), 'length' => 6, 'origin_type' => 'grn']);
        $sisa = Piece::create(['item_id' => $pipa->id, 'piece_no' => Piece::nextPieceNo(), 'length' => 2, 'is_offcut' => true, 'parent_piece_id' => $utuh->id, 'origin_type' => 'conversion']);
        $this->masuk($pipa, 'A', 6, ['piece_id' => $utuh->id]);
        $this->masuk($pipa, 'B', 2, ['piece_id' => $sisa->id]);

        $baris = $this->pck($pipa, 2)->lines()->sole();
        $this->assertSame((int) $sisa->id, (int) $baris->piece_id);
    }

    #[Test]
    public function tc_pck_15_manual_urut_kode_bin(): void
    {
        $kabel = $this->item('KABEL', TrackingMode::None, RemovalStrategy::Manual);
        $this->masuk($kabel, 'C', 10);
        $this->masuk($kabel, 'A', 10);
        DB::connection('tenant')->table('stock_balances')->where('item_id', $kabel->id)->where('bin_id', $this->bin['C']->id)
            ->update(['created_at' => now()->subWeek()]);

        $this->assertSame([['CKG-A', 10.0], ['CKG-C', 5.0]], $this->alokasi($this->pck($kabel, 15)));
    }
}

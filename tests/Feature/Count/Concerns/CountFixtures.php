<?php

declare(strict_types=1);

namespace Tests\Feature\Count\Concerns;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\User;
use App\Domain\Adjustment\Actions\CreateStockAdjustment;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Models\ApprovalTask;
use App\Domain\Count\Actions\CreateStockCount;
use App\Domain\Count\Actions\RecordCount;
use App\Domain\Count\Actions\StartStockCount;
use App\Domain\Count\Models\CountAssignment;
use App\Domain\Count\Models\CountLine;
use App\Domain\Count\Models\StockCount;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Lot;
use App\Domain\Master\Models\Piece;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Serial;
use App\Domain\Master\Models\Uom;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;

/**
 * Bahan uji modul Count/Adjustment: gudang CKG (dua bin penyimpanan + bin
 * sistem), empat item (tiap mode pelacakan) bersaldo, Kepala Gudang CKG,
 * dua staf penghitung, Auditor Internal, dan Manajemen sebagai atasan.
 */
trait CountFixtures
{
    protected Warehouse $gudang;

    protected Bin $binA;

    protected Bin $binB;

    protected Item $baut;

    protected Item $semen;

    protected Item $genset;

    protected Item $pipa;

    protected Lot $lot;

    protected Serial $serial;

    protected Piece $potongan;

    protected User $kepala;

    protected User $staf1;

    protected User $staf2;

    protected User $auditor;

    protected User $manajemen;

    protected function siapkanOpname(): void
    {
        $this->gudang = app(SaveWarehouse::class)->handle(null, [
            'code' => 'CKG',
            'name' => 'Gudang Utama Cakung',
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);

        $this->binA = Bin::create(['warehouse_id' => $this->gudang->id, 'code' => 'CKG-A-R01-L1-B01', 'bin_type' => BinType::Storage]);
        $this->binB = Bin::create(['warehouse_id' => $this->gudang->id, 'code' => 'CKG-A-R01-L1-B02', 'bin_type' => BinType::Storage]);

        $pcs = Uom::query()->where('code', 'PCS')->value('id');
        $m = Uom::query()->where('code', 'M')->value('id');

        $this->baut = $this->itemUji('BAUT-OPN', TrackingMode::None, $pcs);
        $this->semen = $this->itemUji('SEMEN-OPN', TrackingMode::Lot, $pcs, ['has_expiry' => true]);
        $this->genset = $this->itemUji('GENSET-OPN', TrackingMode::Serial, $pcs, ['ownership_model' => OwnershipModel::Asset]);
        $this->pipa = $this->itemUji('PIPA-OPN', TrackingMode::Piece, $m);

        $this->lot = Lot::create(['item_id' => $this->semen->id, 'lot_no' => 'LOT-OPN-1', 'expiry_date' => now()->addYear()->toDateString(), 'received_at' => now()->toDateString()]);
        $this->serial = Serial::create(['item_id' => $this->genset->id, 'serial_no' => 'GNS-OPN-1', 'acquired_at' => now()->toDateString()]);
        $this->potongan = Piece::create(['item_id' => $this->pipa->id, 'piece_no' => 'P-OPN-1', 'length' => 6, 'is_offcut' => false]);

        $this->masuk($this->baut, 100, $this->binA);
        $this->masuk($this->semen, 50, $this->binB, lotId: $this->lot->id);
        $this->masuk($this->genset, 1, $this->binB, serialId: $this->serial->id);
        $this->masuk($this->pipa, 6, $this->binA, pieceId: $this->potongan->id);

        $this->manajemen = $this->makeUser('management');
        $this->kepala = $this->makeUser('warehouse_head', ScopeType::Warehouse, $this->gudang->id, ['manager_id' => $this->manajemen->id]);
        $this->staf1 = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->gudang->id, ['manager_id' => $this->kepala->id]);
        $this->staf2 = $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->gudang->id, ['manager_id' => $this->kepala->id]);
        $this->auditor = $this->makeUser('internal_auditor', ScopeType::All, null, ['manager_id' => $this->manajemen->id]);
    }

    /** @param  array<string, mixed>  $extra */
    protected function itemUji(string $kode, TrackingMode $mode, int|string|null $uom, array $extra = []): Item
    {
        return Item::create(array_merge([
            'code' => $kode,
            'name' => $kode,
            'status' => ItemStatus::Active,
            'tracking_mode' => $mode,
            'ownership_model' => OwnershipModel::Consumable,
            'base_uom_id' => $uom,
        ], $extra));
    }

    protected function masuk(Item $item, float $qty, Bin $bin, ?int $lotId = null, ?int $serialId = null, ?int $pieceId = null): void
    {
        app(StockLedger::class)->post(new MovementRequest(
            item: $item, qtyBase: $qty, toBinId: $bin->id, lotId: $lotId, serialId: $serialId, pieceId: $pieceId,
        ));
    }

    /** @param  array<string, mixed>  $data */
    protected function sesi(array $data = [], ?User $pembuat = null): StockCount
    {
        return app(CreateStockCount::class)->handle(array_merge([
            'count_type' => 'monthly',
            'warehouse_ids' => [$this->gudang->id],
            'bin_ids' => [$this->binA->id, $this->binB->id],
            'freeze_bins' => true,
            'team_user_ids' => [$this->staf1->id, $this->staf2->id],
        ], $data), $pembuat ?? $this->kepala);
    }

    /** @param  array<string, mixed>  $data */
    protected function sesiBerjalan(array $data = [], ?User $pembuat = null): StockCount
    {
        return app(StartStockCount::class)->handle($this->sesi($data, $pembuat), $pembuat ?? $this->kepala);
    }

    /**
     * Semua penugasan terbuka pada satu putaran dihitung oleh penghitungnya.
     * Tanpa isian khusus, jumlah hitung = angka sistem (tidak ada selisih).
     *
     * @param  array<int, float>  $isian  count_line_id => jumlah
     */
    protected function hitungPutaran(StockCount $count, int $round, array $isian = []): StockCount
    {
        $aksi = app(RecordCount::class);

        $tugas = CountAssignment::query()->where('stock_count_id', $count->id)
            ->where('round', $round)->pending()->orderBy('id')->get();

        foreach ($tugas as $t) {
            $penghitung = User::query()->findOrFail($t->counter_user_id);
            $qty = [];

            foreach ($t->linesQuery()->get() as $l) {
                $qty[$l->id] = $isian[$l->id] ?? (float) $l->system_qty;
            }

            $aksi->save($t, $qty, $penghitung);
            $aksi->finish($t->refresh(), $penghitung);
        }

        return $count->refresh();
    }

    protected function baris(StockCount $count, Item $item, ?Bin $bin = null): CountLine
    {
        return CountLine::query()->where('stock_count_id', $count->id)->where('item_id', $item->id)
            ->when($bin !== null, fn ($q) => $q->where('bin_id', $bin->id))
            ->firstOrFail();
    }

    protected function tugasTerbukaSesi(StockCount $count): array
    {
        $snapshot = ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::StockCount, (int) $count->id)->latest('id')->firstOrFail();

        return ApprovalTask::query()->open()->where('approval_snapshot_id', $snapshot->id)
            ->orderBy('id')->pluck('approver_user_id')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    protected function adjManual(array $lines, ?User $pengaju = null, ?string $alasan = 'FOUND'): StockAdjustment
    {
        return app(CreateStockAdjustment::class)->handle([
            'warehouse_id' => $this->gudang->id,
            'reason_code_id' => $alasan === null ? null : ReasonCode::query()->where('context', ReasonContext::Adjustment->value)->where('code', $alasan)->value('id'),
            'notes' => 'uji',
        ], $lines, $pengaju ?? $this->staf1);
    }

    protected function tugasTerbukaAdj(StockAdjustment $adj): array
    {
        $snapshot = ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::StockAdjustment, (int) $adj->id)->latest('id')->firstOrFail();

        return ApprovalTask::query()->open()->where('approval_snapshot_id', $snapshot->id)
            ->orderBy('id')->pluck('approver_user_id')->map(fn ($v) => (int) $v)->all();
    }

    protected function alasanId(ReasonContext $context, ?string $code = null): int
    {
        return (int) ReasonCode::query()->where('context', $context->value)
            ->when($code !== null, fn ($q) => $q->where('code', $code))->value('id');
    }

    protected function saldoBin(Bin $bin, Item $item, StockStatus $status = StockStatus::Available): float
    {
        return round((float) StockBalance::query()->where('bin_id', $bin->id)->where('item_id', $item->id)
            ->where('stock_status', $status->value)->sum('qty_base'), 4);
    }
}

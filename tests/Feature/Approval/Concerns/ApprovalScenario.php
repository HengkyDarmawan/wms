<?php

declare(strict_types=1);

namespace Tests\Feature\Approval\Concerns;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Enums\ApprovalTaskStatus;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Approval\Models\ApprovalTask;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\ItemCategory;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Actions\SaveWarehouse;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;

/**
 * Skenario uji approval: dua gudang bersaldo, satu item di kategori anak
 * (Pipa di bawah Material), proyek, dan pembuat REQ yang sudah diajukan.
 */
trait ApprovalScenario
{
    use ApprovalFixtures;

    protected Project $proyek;

    protected Warehouse $ckg;

    protected Warehouse $bks;

    protected Item $baut;

    protected ItemCategory $material;

    protected ItemCategory $pipa;

    protected function siapkanSkenario(): void
    {
        $this->proyek = $this->makeProject();
        $this->ckg = $this->gudang('CKG', 'Gudang Utama Cakung');
        $this->bks = $this->gudang('BKS', 'Gudang Cabang Bekasi');

        $this->material = ItemCategory::create(['code' => 'MATUJI', 'name' => 'Material uji', 'is_active' => true]);
        $this->pipa = ItemCategory::create(['code' => 'PIPAUJI', 'name' => 'Pipa uji', 'parent_id' => $this->material->id, 'is_active' => true]);

        $this->baut = Item::create([
            'code' => 'BAUT-APR',
            'name' => 'Baut uji approval',
            'status' => ItemStatus::Active,
            'tracking_mode' => TrackingMode::None,
            'ownership_model' => OwnershipModel::Consumable,
            'item_category_id' => $this->pipa->id,
            'base_uom_id' => Uom::query()->where('code', 'PCS')->value('id'),
        ]);

        foreach ([$this->ckg, $this->bks] as $g) {
            $bin = Bin::create(['warehouse_id' => $g->id, 'code' => $g->code.'-A-R01-L1-B01', 'bin_type' => BinType::Storage]);
            app(StockLedger::class)->post(new MovementRequest(item: $this->baut, qtyBase: 500, toBinId: $bin->id));
        }
    }

    protected function gudang(string $kode, string $nama): Warehouse
    {
        return app(SaveWarehouse::class)->handle(null, [
            'code' => $kode,
            'name' => $nama,
            'warehouse_type_id' => WarehouseType::query()->where('code', WarehouseType::MAIN)->value('id'),
        ]);
    }

    /**
     * REQ internal lengkap yang langsung diajukan (masuk mesin approval).
     *
     * @param  array<int, array{qty: float, gudang?: Warehouse}>|null  $baris
     */
    protected function ajukanReq(User $pemohon, ?array $baris = null): MaterialRequest
    {
        $baris ??= [['qty' => 10]];

        $req = app(SaveRequest::class)->handle(
            null,
            ['project_id' => $this->proyek->id, 'required_date' => now()->addDays(3)->toDateString()],
            array_map(fn (array $b) => ['item_id' => $this->baut->id, 'qty_base' => $b['qty']], $baris),
            $pemohon,
        );

        foreach ($req->openLines()->orderBy('id')->get() as $i => $l) {
            $l->forceFill([
                'source_warehouse_id' => ($baris[$i]['gudang'] ?? $this->ckg)->id,
                'fulfillment_source' => 'stock',
            ])->save();
        }

        return app(SubmitRequest::class)->handle($req->refresh(), $pemohon);
    }

    protected function pemohon(array $attributes = []): User
    {
        return $this->makeUser('internal_requester', \App\Domain\Access\Enums\ScopeType::All, null, $attributes);
    }

    protected function snapshotReq(MaterialRequest $req): ApprovalSnapshot
    {
        return ApprovalSnapshot::query()->forDocument(ApprovalDocumentType::MaterialRequest, (int) $req->id)->latest('id')->firstOrFail();
    }

    /** @return array<int, int> id approver tugas terbuka */
    protected function approverTerbuka(MaterialRequest $req): array
    {
        return ApprovalTask::query()->open()
            ->where('approval_snapshot_id', $this->snapshotReq($req)->id)
            ->orderBy('id')->pluck('approver_user_id')->map(fn ($v) => (int) $v)->all();
    }

    protected function tugasTerbuka(MaterialRequest $req, User $user): ApprovalTask
    {
        return ApprovalTask::query()->where('status', ApprovalTaskStatus::Open->value)
            ->where('approval_snapshot_id', $this->snapshotReq($req)->id)
            ->where('approver_user_id', $user->id)->firstOrFail();
    }

    protected function alasanTolak(): int
    {
        return (int) ReasonCode::query()->where('context', ReasonContext::Reject->value)->value('id');
    }
}

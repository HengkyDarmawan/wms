<?php

declare(strict_types=1);

namespace Tests\Feature\Issue\Concerns;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Models\User;
use App\Domain\Approval\Exceptions\ApprovalRuleException;
use App\Domain\Issue\Actions\ConfirmMaterialIssue;
use App\Domain\Issue\Actions\CreateMaterialIssue;
use App\Domain\Issue\Exceptions\IssueRuleException;
use App\Domain\Issue\Models\MaterialIssue;
use App\Domain\Issue\Support\IssuableStock;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Vehicle;
use App\Domain\Request\Actions\SaveRequest;
use App\Domain\Request\Actions\SubmitRequest;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Shipment\Actions\CreatePickTask;
use App\Domain\Shipment\Actions\CreateShipment;
use App\Domain\Shipment\Actions\ShipShipment;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Warehouse\Models\Bin;
use Tests\Feature\Return\Concerns\ReturnFixtures;

/**
 * Bahan uji modul Issue: gudang & proyek TransferFixtures (CKG, BKS, proyek
 * dengan Gudang Site KRW1/KRW2), rantai REQ → SJ ke Gudang Site → GRN → PUT,
 * dan staf site bercakupan KRW1.
 */
trait IssueFixtures
{
    use ReturnFixtures;

    protected function siapkanPemakaian(): void
    {
        $this->siapkanTransfer();
    }

    protected function stafSite(): User
    {
        return $this->makeUser('warehouse_staff', ScopeType::Warehouse, $this->krw1->id);
    }

    /** @param  array<string, int|null>  $turunan */
    protected function kunciIsu(Bin $bin, Item $item, array $turunan = []): string
    {
        return IssuableStock::key((int) $bin->id, (int) $item->id, $turunan['lot_id'] ?? null, $turunan['serial_id'] ?? null, $turunan['piece_id'] ?? null);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines  key, qty_base, work_note
     * @param  array<string, mixed>  $header
     */
    protected function isu(array $lines, array $header = [], ?User $actor = null): MaterialIssue
    {
        return app(CreateMaterialIssue::class)->handle($header + [
            'project_id' => $this->proyek->id,
            'warehouse_id' => $this->krw1->id,
            'notes' => 'Pemakaian uji',
        ], $lines, $actor ?? $this->stafSite());
    }

    protected function konfirmasi(MaterialIssue $isu, ?User $actor = null): MaterialIssue
    {
        return app(ConfirmMaterialIssue::class)->handle($isu->refresh(), $actor ?? $this->stafSite());
    }

    protected function gagalIsu(callable $aksi, string $aturan): void
    {
        try {
            $aksi();
            $this->fail('Seharusnya ditolak '.$aturan.'.');
        } catch (IssueRuleException|LedgerException|ApprovalRuleException $e) {
            $this->assertSame($aturan, $e->rule, $e->getMessage());
        }
    }

    /**
     * REQ stok dari CKG yang dikirim ke Gudang Site KRW1, diterima GRN, lalu
     * di-put-away ke bin penyimpanan KRW1 (alur 1 → alur 2 di site).
     */
    protected function kirimKeSite(Item $item, float $qty): MaterialRequest
    {
        $pemohon = $this->makeUser('internal_requester');

        $req = app(SaveRequest::class)->handle(
            null,
            ['project_id' => $this->proyek->id, 'required_date' => now()->addDays(3)->toDateString()],
            [['item_id' => $item->id, 'qty_base' => $qty]],
            $pemohon,
        );

        $req->openLines()->first()->forceFill([
            'source_warehouse_id' => $this->gudang->id,
            'fulfillment_source' => 'stock',
        ])->save();

        /** @var MaterialRequest $req */
        $req = app(SubmitRequest::class)->handle($req->refresh(), $pemohon);
        $pck = $this->jalankanPck(app(CreatePickTask::class)->handle($req, $this->makeUser('warehouse_head'))[0]);

        $sj = app(CreateShipment::class)->handle([$pck->id], [
            'destination_type' => 'site_warehouse',
            'destination_warehouse_id' => $this->krw1->id,
            'destination_project_id' => $this->proyek->id,
            'shipment_method' => 'own_fleet',
            'vehicle_id' => Vehicle::create(['plate_no' => 'B'.random_int(1000, 9999).'IS'])->id,
            'driver_id' => $this->makeUser('driver')->id,
        ], $this->makeUser('warehouse_staff'));

        /** @var Shipment $sj */
        $sj = app(ShipShipment::class)->handle($sj, null, $this->makeUser('driver'));
        $sj = $this->terimaSj($sj);
        $grn = $this->grnTransferSelesai($sj, $this->krw1);
        $this->putSelesai($grn, $this->binKrw1);

        return $req->refresh();
    }
}

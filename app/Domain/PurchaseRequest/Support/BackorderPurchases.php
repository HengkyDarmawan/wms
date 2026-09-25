<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Support;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\PurchaseRequest\Actions\CancelPurchaseRequest;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestOrigin;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestStatus;
use App\Domain\PurchaseRequest\Exceptions\PurchaseRequestRuleException;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\PurchaseRequest\Models\PurchaseRequestLine;
use App\Domain\PurchaseRequest\Models\PurchaseRequestOrderLine;
use App\Domain\Receipt\Models\PutawayTask;
use App\Domain\Request\Enums\FulfillmentSource;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Enums\RequestLineStatus;
use App\Domain\Request\Exceptions\RequestRuleException;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Request\Models\MaterialRequestLine;
use App\Domain\Stock\Actions\ManageReservation;
use App\Domain\Stock\Enums\ReservationLevel;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Stock\Support\DocumentNumber;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Models\Warehouse;

/**
 * Sambungan REQ ↔ PRQ (BR-REQ-05, BR-REQ-08, BR-REQ-09, BR-REQ-15; A-171),
 * pasangan `BackorderTransfers` untuk baris bersumber pembelian:
 *
 * - **REQ disetujui**: baris bersumber pembelian melahirkan PRQ `backorder`
 *   per gudang pemenuh, baris PRQ menunjuk baris REQ penunggu, lalu diajukan.
 * - **Barang PRQ ditaruh** di bin penyimpanan gudang itu: direservasi lunak
 *   ke baris REQ penunggu (urut tanggal dibutuhkan) sehingga bisa dipetik.
 * - **REQ/baris dibatalkan atau ditutup**: PRQ yang belum diteruskan ikut
 *   dibatalkan; yang sudah dipesan dibiarkan dan barangnya menjadi stok biasa.
 */
class BackorderPurchases
{
    public function __construct(
        private readonly PurchaseRequestFlow $flow,
        private readonly DocumentNumber $nomor,
        private readonly StockLedger $ledger,
        private readonly ManageReservation $reservasi,
    ) {}

    /** @return array<int, PurchaseRequest> */
    public function createFor(MaterialRequest $request, ?User $actor = null): array
    {
        $baris = $request->lines()->open()
            ->where('fulfillment_source', FulfillmentSource::Purchase->value)
            ->whereNotIn('id', PurchaseRequestLine::query()->whereNotNull('material_request_line_id')
                ->whereHas('purchaseRequest', fn ($q) => $q->withoutGlobalScopes()->whereIn('status', PurchaseRequestStatus::openValues()))
                ->select('material_request_line_id'))
            ->orderBy('required_date')->orderBy('id')
            ->get();

        $hasil = [];

        foreach ($baris->groupBy('source_warehouse_id') as $gudangId => $kelompok) {
            $gudang = Warehouse::query()->withoutGlobalScopes()->find((int) $gudangId);

            if ($gudang === null) {
                continue;
            }

            foreach ($kelompok as $l) {
                if ($l->item_id === null) {
                    throw RequestRuleException::rule('BR-REQ-03', 'Baris '.$l->displayName().' belum dipetakan ke item katalog; PRQ tidak bisa dibuat.');
                }
            }

            try {
                $prq = PurchaseRequest::create([
                    'number' => $this->nomor->next('PRQ', 'ALL'),
                    'warehouse_id' => $gudang->id,
                    'project_id' => $request->project_id,
                    'origin' => PurchaseRequestOrigin::Backorder,
                    'material_request_id' => $request->id,
                    'status' => PurchaseRequestStatus::Draft,
                    'created_by' => $actor?->id,
                    'notes' => 'Backorder '.$request->number,
                ]);

                foreach ($kelompok as $l) {
                    PurchaseRequestLine::create([
                        'purchase_request_id' => $prq->id,
                        'item_id' => $l->item_id,
                        'material_request_line_id' => $l->id,
                        'required_date' => $l->required_date?->toDateString() ?? $request->required_date?->toDateString(),
                        'qty_base' => round((float) $l->qty_base - (float) $l->qty_shipped, 4),
                    ]);
                }

                $hasil[] = $this->flow->submit($prq, $actor);
            } catch (PurchaseRequestRuleException $e) {
                throw RequestRuleException::rule($e->rule, 'PRQ backorder gagal dibuat: '.$e->getMessage());
            }
        }

        return $hasil;
    }

    /** BR-REQ-08: barang PRQ yang sudah di bin penyimpanan direservasi ke baris REQ penunggunya. */
    public function reserveArrivals(PutawayTask $task, ?User $actor = null): void
    {
        $task->loadMissing('lines.receiptLine');

        foreach ($task->lines as $putLine) {
            $ref = $putLine->receiptLine?->purchase_request_order_line_id;
            $prqLine = $ref !== null ? PurchaseRequestOrderLine::query()->with('line')->find($ref)?->line : null;

            if ($prqLine === null || $prqLine->material_request_line_id === null) {
                continue;
            }

            $reqLine = MaterialRequestLine::query()->with('request', 'item')->find($prqLine->material_request_line_id);

            if ($reqLine === null || ! $this->menunggu($reqLine) || (int) $reqLine->source_warehouse_id !== (int) $task->warehouse_id) {
                continue;
            }

            $gudang = Warehouse::query()->withoutGlobalScopes()->findOrFail($task->warehouse_id);
            $butuh = (float) $reqLine->qty_base - (float) $reqLine->qty_shipped - $this->reservasiAktif($reqLine);
            $qty = round(min((float) $putLine->qty_base, $butuh, $this->ledger->availableQty((int) $reqLine->item_id, (int) $gudang->id)), 4);

            if ($qty <= 0) {
                continue;
            }

            try {
                $this->reservasi->reserveSoft($reqLine->item, $gudang, $qty, 'material_request', (int) $reqLine->material_request_id, (int) $reqLine->id, $actor);
            } catch (LedgerException) {
                continue;
            }

            $reqLine->forceFill(['qty_reserved' => (float) $reqLine->qty_reserved + $qty])->save();

            activity('request')->performedOn($reqLine->request)->causedBy($actor)
                ->withProperties(['baris' => $reqLine->id, 'qty' => $qty, 'prq' => $prqLine->purchaseRequest?->number])
                ->log('Barang pembelian tiba dan direservasi ke REQ');
        }
    }

    /**
     * BR-REQ-09/15: PRQ backorder yang belum diteruskan dan semua baris REQ-nya
     * tidak lagi terbuka dibatalkan.
     *
     * @param  array<int, int>  $requestLineIds
     */
    public function releaseForRequestLines(array $requestLineIds, ?int $reasonCodeId, ?User $actor = null): int
    {
        if ($requestLineIds === []) {
            return 0;
        }

        $alasan = $this->alasanBatal($reasonCodeId);
        $prqIds = PurchaseRequestLine::query()->whereIn('material_request_line_id', $requestLineIds)->pluck('purchase_request_id')->unique();
        $dibatalkan = 0;

        foreach (PurchaseRequest::withoutGlobalScopes()->whereIn('id', $prqIds)->get() as $prq) {
            if (! in_array($prq->status, [PurchaseRequestStatus::Draft, PurchaseRequestStatus::Submitted, PurchaseRequestStatus::PendingApproval, PurchaseRequestStatus::Approved], true)) {
                continue;
            }

            $masihDibutuhkan = MaterialRequestLine::query()
                ->whereIn('id', $prq->lines()->pluck('material_request_line_id')->filter())
                ->where('status', RequestLineStatus::Open->value)
                ->exists();

            if ($masihDibutuhkan || $alasan === null) {
                continue;
            }

            try {
                app(CancelPurchaseRequest::class)->handle($prq, $alasan, 'REQ asal dibatalkan/ditutup', $actor);
                $dibatalkan++;
            } catch (PurchaseRequestRuleException) {
                continue;
            }
        }

        return $dibatalkan;
    }

    private function alasanBatal(?int $reasonCodeId): ?int
    {
        if ($reasonCodeId !== null && ReasonCode::query()->whereKey($reasonCodeId)->where('context', ReasonContext::Cancel->value)->exists()) {
            return $reasonCodeId;
        }

        $id = ReasonCode::query()->where('context', ReasonContext::Cancel->value)
            ->orderByRaw("case when code = 'NOT_NEEDED' then 0 else 1 end")->orderBy('id')->value('id');

        return $id === null ? null : (int) $id;
    }

    private function menunggu(MaterialRequestLine $line): bool
    {
        return $line->status === RequestLineStatus::Open
            && $line->fulfillment_source === FulfillmentSource::Purchase
            && in_array($line->request?->status, [
                MaterialRequestStatus::Approved,
                MaterialRequestStatus::InProgress,
                MaterialRequestStatus::PartiallyFulfilled,
            ], true);
    }

    private function reservasiAktif(MaterialRequestLine $line): float
    {
        return (float) StockReservation::query()->active()
            ->where('level', ReservationLevel::Soft->value)
            ->forDocument('material_request', (int) $line->material_request_id)
            ->where('document_line_id', $line->id)
            ->sum('qty_base');
    }
}

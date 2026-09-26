<?php

declare(strict_types=1);

namespace App\Domain\Shared\Support;

use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Asset\Models\AssetHandover;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Issue\Models\MaterialIssue;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\PurchaseRequest\Models\PurchaseRequestOrderLine;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\GoodsReceiptLine;
use App\Domain\Receipt\Models\PutawayTask;
use App\Domain\Receipt\Models\VendorReturn;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Shipment\Models\DeliveryDiscrepancy;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Shipment\Models\PickTaskLine;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Models\ShipmentLine;
use App\Domain\Transfer\Models\Transfer;
use App\Domain\Waste\Models\WasteDisposal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Dokumen terkait (A-252): dari satu dokumen, telusuri dokumen **asal** (hulu)
 * dan **turunan** (hilir) lewat tautan yang sudah ada di skema — FK, pasangan
 * `source_type`/`source_id`, dan baris — tanpa tabel linimasa baru
 * (BR-GEN-05 `document_timelines` tetap belum dibangun).
 *
 * Hanya dokumen yang boleh dilihat pengguna (policy `view`, BR-ACC-05) yang
 * ditampilkan; PO hanya untuk pemegang `po.view` (harga, D-07).
 */
class DocumentLineage
{
    /** @var array<class-string, array{0: string, 1: string}> kelas => [label jenis, nama rute detail] */
    public const JENIS = [
        MaterialRequest::class => ['REQ', 'requests.show'],
        PickTask::class => ['PCK', 'picks.show'],
        Shipment::class => ['SJ', 'shipments.show'],
        DeliveryDiscrepancy::class => ['DSC', 'shipments.show'],
        GoodsReceipt::class => ['GRN', 'receipts.show'],
        PutawayTask::class => ['PUT', 'putaways.show'],
        VendorReturn::class => ['RTV', 'vendor-returns.show'],
        GoodsReturn::class => ['RET', 'returns.show'],
        Transfer::class => ['TRF', 'transfers.show'],
        MaterialIssue::class => ['ISU', 'issues.show'],
        Conversion::class => ['CNV', 'conversions.show'],
        WasteDisposal::class => ['WST', 'waste-disposals.show'],
        PurchaseRequest::class => ['PRQ', 'purchase-requests.show'],
        PurchaseOrder::class => ['PO', 'purchase-orders.show'],
        AssetHandover::class => ['AST', 'asset-handovers.show'],
        StockAdjustment::class => ['ADJ', 'adjustments.show'],
    ];

    /**
     * @return array{asal: Collection<int, array<string, mixed>>, turunan: Collection<int, array<string, mixed>>}
     */
    public function for(Model $doc): array
    {
        [$asal, $turunan] = match (true) {
            $doc instanceof MaterialRequest => $this->req($doc),
            $doc instanceof PickTask => $this->pck($doc),
            $doc instanceof Shipment => $this->sj($doc),
            $doc instanceof GoodsReceipt => $this->grn($doc),
            $doc instanceof PutawayTask => [[$this->cari(GoodsReceipt::class, $doc->goods_receipt_id)], []],
            $doc instanceof VendorReturn => [[$this->cari(GoodsReceipt::class, $doc->goods_receipt_id)], [$this->cari(GoodsReceipt::class, $doc->replacement_receipt_id)]],
            $doc instanceof GoodsReturn => $this->ret($doc),
            $doc instanceof Transfer => $this->trf($doc),
            $doc instanceof PurchaseRequest => $this->prq($doc),
            $doc instanceof PurchaseOrder => $this->po($doc),
            $doc instanceof MaterialIssue, $doc instanceof Conversion, $doc instanceof StockAdjustment => $this->pembalik($doc),
            $doc instanceof AssetHandover => $this->ast($doc),
            default => [[], []],
        };

        return ['asal' => $this->rapikan($asal), 'turunan' => $this->rapikan($turunan)];
    }

    /** Satu baris siap tampil, atau null bila tidak boleh dilihat. */
    public function entry(?Model $m): ?array
    {
        if ($m === null || ! isset(self::JENIS[$m::class])) {
            return null;
        }

        if ($m instanceof PurchaseOrder && ! (auth()->user()?->hasPermission('po.view') ?? false)) {
            return null;
        }

        $cek = $m instanceof DeliveryDiscrepancy ? $m->shipment()->withoutGlobalScopes()->first() : $m;

        // Policy `view` memeriksa izin; cakupan gudang/proyek (BR-ACC-05) ditegakkan
        // global scope model — dokumen di luar cakupan tidak ditemukan (404).
        if ($cek === null || (auth()->check() && (! Gate::allows('view', $cek) || ! $cek::query()->whereKey($cek->getKey())->exists()))) {
            return null;
        }

        [$jenis, $rute] = self::JENIS[$m::class];
        $status = $m->getAttribute('status');

        return [
            'key' => $m::class.':'.$m->getKey(),
            'jenis' => $jenis,
            'number' => (string) $m->getAttribute('number'),
            'url' => route($rute, $m instanceof DeliveryDiscrepancy ? $m->shipment_id : $m->getKey()),
            'status' => is_object($status) && method_exists($status, 'label') ? $status->label() : null,
            'badge' => is_object($status) && method_exists($status, 'badge') ? $status->badge() : 'text-bg-light',
            'tanggal' => $m->getAttribute('created_at'),
        ];
    }

    /** @return array{0: array<int, ?Model>, 1: array<int, ?Model>} */
    private function req(MaterialRequest $r): array
    {
        $pck = PickTask::query()->withoutGlobalScopes()->where('source_type', 'material_request')->where('source_id', $r->id)->get();

        return [
            [$this->cari(MaterialRequest::class, $r->parent_request_id)],
            array_merge(
                MaterialRequest::query()->withoutGlobalScopes()->where('parent_request_id', $r->id)->get()->all(),
                $pck->all(),
                $this->sjDariPck($pck)->all(),
                Transfer::query()->withoutGlobalScopes()->where('source_type', 'material_request')->where('source_id', $r->id)->get()->all(),
                PurchaseRequest::query()->withoutGlobalScopes()->where('material_request_id', $r->id)->get()->all(),
            ),
        ];
    }

    /** @return array{0: array<int, ?Model>, 1: array<int, ?Model>} */
    private function pck(PickTask $p): array
    {
        return [[$this->sumber($p->source_type, $p->source_id)], $this->sjDariPck(collect([$p]))->all()];
    }

    /** @return array{0: array<int, ?Model>, 1: array<int, ?Model>} */
    private function sj(Shipment $s): array
    {
        $pck = PickTask::query()->withoutGlobalScopes()->whereIn('id', PickTaskLine::query()
            ->whereIn('id', ShipmentLine::query()->where('shipment_id', $s->id)->whereNotNull('pick_task_line_id')->pluck('pick_task_line_id'))
            ->pluck('pick_task_id'))->get();

        $asal = array_merge($pck->all(), $pck->map(fn (PickTask $p) => $this->sumber($p->source_type, $p->source_id))->all());

        if ($s->isWithoutPicking()) {
            $asal[] = $this->sumber($s->source_type, $s->source_id);
        }

        return [
            $asal,
            array_merge(
                DeliveryDiscrepancy::query()->withoutGlobalScopes()->where('shipment_id', $s->id)->get()->all(),
                GoodsReceipt::query()->withoutGlobalScopes()->where('shipment_id', $s->id)->get()->all(),
                GoodsReturn::query()->withoutGlobalScopes()->where('origin_shipment_id', $s->id)->get()->all(),
                AssetHandover::query()->withoutGlobalScopes()->where('shipment_id', $s->id)->get()->all(),
            ),
        ];
    }

    /** @return array{0: array<int, ?Model>, 1: array<int, ?Model>} */
    private function grn(GoodsReceipt $g): array
    {
        $catatan = PurchaseRequestOrderLine::query()->with('order')
            ->whereIn('id', GoodsReceiptLine::query()->where('goods_receipt_id', $g->id)->whereNotNull('purchase_request_order_line_id')->pluck('purchase_request_order_line_id'))
            ->get();

        return [
            array_merge(
                [$this->cari(Shipment::class, $g->shipment_id), $this->cari(GoodsReturn::class, $g->goods_return_id)],
                [$g->source_type === 'vendor_return' ? $this->cari(VendorReturn::class, $g->source_id) : null],
                $catatan->map(fn ($c) => $this->cari(PurchaseRequest::class, $c->order?->purchase_request_id))->all(),
                $catatan->map(fn ($c) => $this->cari(PurchaseOrder::class, $c->order?->purchase_order_id))->all(),
            ),
            array_merge(
                PutawayTask::query()->withoutGlobalScopes()->where('goods_receipt_id', $g->id)->get()->all(),
                VendorReturn::query()->withoutGlobalScopes()->where('goods_receipt_id', $g->id)->get()->all(),
                StockAdjustment::query()->withoutGlobalScopes()->where('goods_receipt_id', $g->id)->get()->all(),
            ),
        ];
    }

    /** @return array{0: array<int, ?Model>, 1: array<int, ?Model>} */
    private function ret(GoodsReturn $r): array
    {
        $pck = PickTask::query()->withoutGlobalScopes()->where('source_type', 'goods_return')->where('source_id', $r->id)->get();

        return [
            [$this->cari(Shipment::class, $r->origin_shipment_id)],
            array_merge(
                $pck->all(),
                [$this->cari(Shipment::class, $r->return_shipment_id)],
                GoodsReceipt::query()->withoutGlobalScopes()->where('goods_return_id', $r->id)->get()->all(),
                AssetHandover::query()->withoutGlobalScopes()->where('goods_return_id', $r->id)->get()->all(),
            ),
        ];
    }

    /** @return array{0: array<int, ?Model>, 1: array<int, ?Model>} */
    private function trf(Transfer $t): array
    {
        $pck = PickTask::query()->withoutGlobalScopes()->where('source_type', 'transfer')->where('source_id', $t->id)->get();
        $sj = $this->sjDariPck($pck)->merge(Shipment::query()->withoutGlobalScopes()->where('source_type', 'transfer')->where('source_id', $t->id)->get());

        return [
            [$this->sumber($t->source_type, $t->source_id)],
            array_merge(
                $pck->all(),
                $sj->all(),
                GoodsReceipt::query()->withoutGlobalScopes()->whereIn('shipment_id', $sj->pluck('id'))->get()->all(),
                AssetHandover::query()->withoutGlobalScopes()->where('transfer_id', $t->id)->whereNotNull('previous_handover_id')->get()->all(),
            ),
        ];
    }

    /** @return array{0: array<int, ?Model>, 1: array<int, ?Model>} */
    private function prq(PurchaseRequest $p): array
    {
        $poIds = $p->orders()->whereNotNull('purchase_order_id')->pluck('purchase_order_id');
        $olIds = PurchaseRequestOrderLine::query()->whereHas('order', fn ($q) => $q->where('purchase_request_id', $p->id))->pluck('id');
        $grnIds = GoodsReceiptLine::query()->whereIn('purchase_request_order_line_id', $olIds)->pluck('goods_receipt_id');

        return [
            [$this->cari(MaterialRequest::class, $p->material_request_id)],
            array_merge(
                PurchaseOrder::query()->withoutGlobalScopes()->whereIn('id', $poIds)->get()->all(),
                GoodsReceipt::query()->withoutGlobalScopes()->whereIn('id', $grnIds)->get()->all(),
            ),
        ];
    }

    /** @return array{0: array<int, ?Model>, 1: array<int, ?Model>} */
    private function po(PurchaseOrder $po): array
    {
        $prqIds = $po->lines()->with('requestLine:id,purchase_request_id')->get()->pluck('requestLine.purchase_request_id')->filter()->unique();
        $olIds = PurchaseRequestOrderLine::query()->whereNotNull('purchase_order_line_id')
            ->whereIn('purchase_order_line_id', $po->lines()->pluck('id'))->pluck('id');

        return [
            PurchaseRequest::query()->withoutGlobalScopes()->whereIn('id', $prqIds)->get()->all(),
            GoodsReceipt::query()->withoutGlobalScopes()->whereIn('id', GoodsReceiptLine::query()->whereIn('purchase_request_order_line_id', $olIds)->pluck('goods_receipt_id'))->get()->all(),
        ];
    }

    /** ISU/CNV/ADJ pembalik (A-150, A-157, A-102). @return array{0: array<int, ?Model>, 1: array<int, ?Model>} */
    private function pembalik(Model $m): array
    {
        return [
            [$m->getAttribute('reversal_of_id') !== null ? $m::query()->withoutGlobalScopes()->find($m->getAttribute('reversal_of_id')) : null],
            $m::query()->withoutGlobalScopes()->where('reversal_of_id', $m->getKey())->get()->all(),
        ];
    }

    /** @return array{0: array<int, ?Model>, 1: array<int, ?Model>} */
    private function ast(AssetHandover $a): array
    {
        return [
            [$this->cari(Shipment::class, $a->shipment_id), $this->cari(AssetHandover::class, $a->previous_handover_id), $this->cari(Transfer::class, $a->transfer_id)],
            [$this->cari(GoodsReturn::class, $a->goods_return_id), $this->cari(AssetHandover::class, $a->next_handover_id)],
        ];
    }

    /** @param  Collection<int, PickTask>  $pck @return Collection<int, Shipment> */
    private function sjDariPck(Collection $pck): Collection
    {
        if ($pck->isEmpty()) {
            return collect();
        }

        $ids = ShipmentLine::query()->whereIn('pick_task_line_id', PickTaskLine::query()->whereIn('pick_task_id', $pck->pluck('id'))->pluck('id'))
            ->pluck('shipment_id')->unique();

        return Shipment::query()->withoutGlobalScopes()->whereIn('id', $ids)->get();
    }

    private function sumber(?string $type, mixed $id): ?Model
    {
        return match ($type) {
            'material_request' => $this->cari(MaterialRequest::class, $id),
            'transfer' => $this->cari(Transfer::class, $id),
            'goods_return' => $this->cari(GoodsReturn::class, $id),
            default => null,
        };
    }

    /** @param  class-string<Model>  $kelas */
    private function cari(string $kelas, mixed $id): ?Model
    {
        return $id === null ? null : $kelas::query()->withoutGlobalScopes()->find($id);
    }

    /**
     * @param  array<int, ?Model>  $daftar
     * @return Collection<int, array<string, mixed>>
     */
    private function rapikan(array $daftar): Collection
    {
        return collect($daftar)->map(fn (?Model $m) => $this->entry($m))->filter()->unique('key')->values();
    }
}

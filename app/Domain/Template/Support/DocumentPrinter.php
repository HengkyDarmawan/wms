<?php

declare(strict_types=1);

namespace App\Domain\Template\Support;

use App\Domain\Access\Models\User;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Asset\Models\AssetHandover;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Issue\Models\MaterialIssue;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\VendorReturn;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Shipment\Models\DeliveryDiscrepancy;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Support\ShipmentLineOrigins;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Models\DocumentLayout;
use App\Domain\Template\Models\DocumentTemplate;
use App\Domain\Transfer\Models\Transfer;
use App\Domain\Waste\Models\WasteDisposal;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Mencetak dokumen bawaan F1 (18 §5.1) memakai layout induk.
 *
 * Tidak ada permission cetak tersendiri: yang boleh melihat dokumen boleh
 * mencetaknya (A-124). Model dicari lewat query biasa sehingga cakupan gudang
 * pengguna (`ScopedToUser`, BR-ACC-05) tetap berlaku. Tidak ada kolom harga
 * di template mana pun (D-07).
 */
class DocumentPrinter
{
    public function __construct(
        private readonly PrintAssets $assets,
        private readonly PrintHistory $history,
        private readonly SignatureSeals $seals,
    ) {}

    /** Cari dokumen, periksa izin lihat, lalu kirim PDF inline. */
    public function stream(DocumentTemplateType $type, int $id, User $actor): Response
    {
        $model = $this->find($type, $id);
        $this->authorize($type, $model, $actor);

        // A-263: setiap cetak dicatat; cetakan ke-n ikut tercetak.
        $log = $this->history->record($type, $model, $this->number($type, $model), $actor, request()?->ip());

        return PdfRenderer::make($this->view($type, $model, $log->copy_no)->render(), DocumentTemplate::forType($type)->paper)
            ->stream(str_replace('/', '-', $this->number($type, $model)).'.pdf');
    }

    public function find(DocumentTemplateType $type, int $id): Model
    {
        if ($type->isStub()) {
            // BR-GEN-10: modul pemiliknya belum ada.
            throw new HttpException(501, __(':dokumen belum tersedia di Fase 1.', ['dokumen' => $type->label()]));
        }

        $query = match ($type) {
            DocumentTemplateType::Shipment, DocumentTemplateType::ProofOfDelivery => Shipment::query(),
            DocumentTemplateType::PickTask => PickTask::query(),
            DocumentTemplateType::DeliveryDiscrepancy => DeliveryDiscrepancy::query(),
            DocumentTemplateType::VendorReturn => VendorReturn::query(),
            DocumentTemplateType::StockAdjustment => StockAdjustment::query(),
            DocumentTemplateType::MaterialIssue => MaterialIssue::query(),
            DocumentTemplateType::Conversion => Conversion::query(),
            DocumentTemplateType::WasteDisposal => WasteDisposal::query(),
            DocumentTemplateType::AssetHandover => AssetHandover::query(),
            DocumentTemplateType::PurchaseOrder => PurchaseOrder::query(),
            DocumentTemplateType::Transfer => Transfer::query(),
            DocumentTemplateType::GoodsReturn => GoodsReturn::query(),
            DocumentTemplateType::PurchaseRequest => PurchaseRequest::query(),
            DocumentTemplateType::GoodsReceipt => GoodsReceipt::query(),
            default => throw new NotFoundHttpException,
        };

        $model = $query->find($id) ?? throw new NotFoundHttpException;

        // Bukti terima baru ada setelah driver/penerima mengisinya.
        if ($type === DocumentTemplateType::ProofOfDelivery && $model->proof()->doesntExist()) {
            throw new NotFoundHttpException;
        }

        return $model;
    }

    public function authorize(DocumentTemplateType $type, Model $model, User $actor): void
    {
        $boleh = Gate::forUser($actor)->allows('view', $model);

        // DSC hanya memeriksa permission; cakupan gudang diambil dari SJ-nya.
        if ($boleh && $model instanceof DeliveryDiscrepancy) {
            $boleh = Shipment::query()->whereKey($model->shipment_id)->exists();
        }

        if (! $boleh) {
            throw new HttpException(403);
        }
    }

    /** HTML cetak; dipakai PDF dan uji (teks PDF dompdf terkompresi). */
    public function view(DocumentTemplateType $type, Model $model, ?int $copyNo = null): View
    {
        $layout = DocumentLayout::current();

        $data = match ($type) {
            DocumentTemplateType::Shipment => $this->shipment($model),
            DocumentTemplateType::ProofOfDelivery => $this->proof($model),
            DocumentTemplateType::PickTask => $this->pickTask($model),
            DocumentTemplateType::DeliveryDiscrepancy => $this->discrepancy($model),
            DocumentTemplateType::VendorReturn => $this->vendorReturn($model),
            DocumentTemplateType::StockAdjustment => $this->adjustment($model),
            DocumentTemplateType::MaterialIssue => $this->materialIssue($model),
            DocumentTemplateType::Conversion => $this->conversion($model),
            DocumentTemplateType::WasteDisposal => $this->wasteDisposal($model),
            DocumentTemplateType::AssetHandover => $this->assetHandover($model),
            DocumentTemplateType::PurchaseOrder => $this->purchaseOrder($model),
            DocumentTemplateType::Transfer => $this->transfer($model),
            DocumentTemplateType::GoodsReturn => $this->goodsReturn($model),
            DocumentTemplateType::PurchaseRequest => $this->purchaseRequest($model),
            DocumentTemplateType::GoodsReceipt => $this->goodsReceipt($model),
            default => throw new NotFoundHttpException,
        };

        $pelaku = $data['pelaku'];
        unset($data['pelaku']);
        $nomor = $this->number($type, $model);

        return view('print.documents.'.$type->value, $data + [
            'type' => $type,
            'kop' => $this->assets->kop($layout),
            'nomor' => $nomor,
            'status' => $model->status?->label(),
            'batal' => ($model->status?->value ?? null) === 'cancelled',
            'qr' => $this->assets->qr($this->url($type, $model)),
            'tandaTangan' => $this->signatures($type, $model, $nomor, $layout->signatureBlocksFor($type), $pelaku),
            'bernilai' => false,
            'cetakKe' => $copyNo,
            'cetakUlang' => $copyNo !== null && $copyNo > 1 && PrintHistory::marked($type),
        ]);
    }

    public function number(DocumentTemplateType $type, Model $model): string
    {
        return (string) $model->number;
    }

    /** Halaman detail internal yang dituju QR dokumen (A-121). */
    public function url(DocumentTemplateType $type, Model $model): string
    {
        return match ($type) {
            DocumentTemplateType::Shipment, DocumentTemplateType::ProofOfDelivery => route('shipments.show', $model),
            DocumentTemplateType::DeliveryDiscrepancy => route('shipments.show', $model->shipment_id),
            DocumentTemplateType::PickTask => route('picks.show', $model),
            DocumentTemplateType::VendorReturn => route('vendor-returns.show', $model),
            DocumentTemplateType::StockAdjustment => route('adjustments.show', $model),
            DocumentTemplateType::MaterialIssue => route('issues.show', $model),
            DocumentTemplateType::Conversion => route('conversions.show', $model),
            DocumentTemplateType::WasteDisposal => route('waste-disposals.show', $model),
            DocumentTemplateType::AssetHandover => route('asset-handovers.show', $model),
            DocumentTemplateType::PurchaseOrder => route('purchase-orders.show', $model),
            DocumentTemplateType::Transfer => route('transfers.show', $model),
            DocumentTemplateType::GoodsReturn => route('returns.show', $model),
            DocumentTemplateType::PurchaseRequest => route('purchase-requests.show', $model),
            DocumentTemplateType::GoodsReceipt => route('receipts.show', $model),
            default => url('/'),
        };
    }

    /** @return array<string, mixed> */
    private function transfer(Transfer $trf): array
    {
        $trf->loadMissing('fromWarehouse', 'toWarehouse', 'fromProject', 'toProject', 'submitter', 'approver');
        $lines = $trf->lines()->with('item.baseUom', 'item.activeConversions.uom')->orderBy('id')->get();
        $req = $trf->sourceRequest();

        return ['trf' => $trf, 'lines' => $lines, 'rujukan' => $req?->number ?? '', 'pelaku' => [$this->at($trf->submitter, $trf->created_at), $this->at($trf->approver, $trf->approved_at), null]];
    }

    /** @return array<string, mixed> */
    private function goodsReturn(GoodsReturn $ret): array
    {
        $ret->loadMissing('project', 'requester', 'fromWarehouse', 'toWarehouse', 'originShipment', 'returnShipment', 'approver', 'sorter');
        $lines = $ret->requestedLines()->with('item.baseUom', 'item.activeConversions.uom', 'uom', 'lot', 'serial', 'piece', 'fromBin', 'reason')->orderBy('id')->get();

        return ['ret' => $ret, 'lines' => $lines, 'pelaku' => [$this->at($ret->requester, $ret->created_at), null, $this->at($ret->sorter, $ret->sorted_at)]];
    }

    /** @return array<string, mixed> */
    private function purchaseRequest(PurchaseRequest $prq): array
    {
        $prq->loadMissing('warehouse', 'project', 'materialRequest', 'creator', 'submitter', 'approver', 'forwarder');
        $lines = $prq->lines()->with('item.baseUom', 'item.activeConversions.uom', 'requestLine.request')->orderBy('id')->get();
        $orders = $prq->orders()->with('vendor', 'lines.line.item')->orderBy('id')->get();

        return ['prq' => $prq, 'lines' => $lines, 'orders' => $orders, 'pelaku' => [$this->at($prq->creator, $prq->created_at), $this->at($prq->approver, $prq->approved_at), $this->at($prq->forwarder, $prq->forwarded_at)]];
    }

    /** @return array<string, mixed> */
    private function goodsReceipt(GoodsReceipt $grn): array
    {
        $grn->loadMissing('warehouse', 'vendor', 'shipment', 'goodsReturn', 'receiver');
        $lines = $grn->lines()->with('item.baseUom', 'item.activeConversions.uom', 'uom', 'damageReason', 'damagedBin', 'lot', 'serial', 'piece', 'receivingBin')->orderBy('id')->get();

        return ['grn' => $grn, 'lines' => $lines, 'pelaku' => [$this->at($grn->receiver, $grn->received_at), null, null]];
    }

    /** @return array<string, mixed> */
    private function shipment(Shipment $sj): array
    {
        $sj->loadMissing('warehouse', 'originProject', 'destinationProject', 'destinationWarehouse', 'destinationVendor', 'vehicle', 'driver', 'carrier', 'proof');

        $lines = $sj->lines()
            ->with('item.baseUom', 'item.activeConversions.uom', 'lot', 'serial', 'piece', 'pickTaskLine.pickTask')
            ->orderBy('id')->get();

        $pickIds = $lines->pluck('pickTaskLine.pick_task_id')->filter()->unique();
        $reqIds = PickTask::query()->whereIn('id', $pickIds)->where('source_type', 'material_request')->pluck('source_id');

        $proof = $sj->proof;

        return [
            'sj' => $sj,
            'lines' => $lines,
            'asal' => app(ShipmentLineOrigins::class)->for($sj, $lines),
            'rujukan' => [
                'dokumen' => app(ShipmentLineOrigins::class)->sourceDocument($sj),
                'pck' => PickTask::query()->whereIn('id', $pickIds)->orderBy('id')->pluck('number')->all(),
                'req' => MaterialRequest::query()->withoutGlobalScopes()->whereIn('id', $reqIds)->orderBy('id')->pluck('number')->all(),
                // A-313: No. PO klien dari REQ — referensi bagi admin site saat membuat GR di sistemnya.
                'po_klien' => $sj->clientPoNumbers(),
            ],
            'pelaku' => [
                null,
                // A-311: driver tanpa akun — nama teks (+ HP di kepala SJ).
                $this->at($sj->driverName() ?? ($sj->carried_by_name ?: $sj->carrier?->name), $sj->shipped_at),
                $proof ? ['name' => $proof->received_by_name, 'signature' => $proof->signature_path, 'at' => $proof->confirmed_at] : null,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function proof(Shipment $sj): array
    {
        $sj->loadMissing('warehouse', 'destinationProject', 'destinationWarehouse', 'destinationVendor', 'driver', 'carrier', 'vehicle');
        $proof = $sj->proof()->with('receivedByUser', 'lines.shipmentLine.item.baseUom', 'lines.shipmentLine.item.activeConversions.uom', 'lines.shipmentLine.lot', 'lines.shipmentLine.serial', 'lines.shipmentLine.piece')->firstOrFail();

        return [
            'sj' => $sj,
            'proof' => $proof,
            'lines' => $proof->lines->sortBy('id')->values(),
            'pelaku' => [
                $this->at($sj->driverName() ?? ($sj->carried_by_name ?: $sj->carrier?->name), $sj->shipped_at),
                ['name' => $proof->received_by_name ?: $proof->receivedByUser?->name, 'signature' => $proof->signature_path, 'at' => $proof->confirmed_at, 'user' => $proof->receivedByUser],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function pickTask(PickTask $pck): array
    {
        $pck->loadMissing('warehouse', 'assignee');

        $lines = $pck->lines()->with('bin', 'item.baseUom', 'item.activeConversions.uom', 'lot', 'serial', 'piece')->get()
            ->sortBy(fn ($l) => [$l->bin?->code ?? '', $l->id])->values();

        $req = $pck->source_type === 'material_request'
            ? MaterialRequest::query()->withoutGlobalScopes()->whereKey($pck->source_id)->value('number')
            : null;

        return ['pck' => $pck, 'lines' => $lines, 'req' => $req, 'pelaku' => [$this->at($pck->assignee, $pck->completed_at), null]];
    }

    /** @return array<string, mixed> */
    private function discrepancy(DeliveryDiscrepancy $dsc): array
    {
        $dsc->loadMissing('resolver');
        $sj = Shipment::query()->with('warehouse', 'driver', 'destinationProject', 'destinationWarehouse', 'destinationVendor')->findOrFail($dsc->shipment_id);

        $lines = $dsc->lines()->with('reasonCode', 'shipmentLine.item.baseUom', 'shipmentLine.item.activeConversions.uom', 'shipmentLine.lot', 'shipmentLine.serial', 'shipmentLine.piece')
            ->orderBy('id')->get();

        return ['dsc' => $dsc, 'sj' => $sj, 'lines' => $lines, 'pelaku' => [$this->at($dsc->resolver, $dsc->resolved_at), $this->at($sj->driverName(), $sj->shipped_at)]];
    }

    /** @return array<string, mixed> */
    private function vendorReturn(VendorReturn $rtv): array
    {
        $rtv->loadMissing('warehouse', 'vendor', 'receipt', 'submitter', 'approver');
        $lines = $rtv->lines()->with('item.baseUom', 'item.activeConversions.uom', 'lot', 'serial', 'piece', 'bin', 'reason')->orderBy('id')->get();

        return ['rtv' => $rtv, 'lines' => $lines, 'pelaku' => [$this->at($rtv->submitter, $rtv->created_at), $this->at($rtv->approver, $rtv->approved_at), $this->at($rtv->vendor?->name, $rtv->vendor_confirmed_at)]];
    }

    /** @return array<string, mixed> */
    private function adjustment(StockAdjustment $adj): array
    {
        $adj->loadMissing('warehouse', 'reason', 'submitter', 'approver', 'stockCount', 'reversalOf');
        $lines = $adj->lines()->with('bin', 'item.baseUom', 'item.activeConversions.uom', 'lot', 'serial', 'piece', 'reason')->orderBy('id')->get();

        return ['adj' => $adj, 'lines' => $lines, 'pelaku' => [$this->at($adj->submitter, $adj->created_at), $this->at($adj->approver, $adj->approved_at)]];
    }

    /** @return array<string, mixed> */
    private function materialIssue(MaterialIssue $isu): array
    {
        $isu->loadMissing('project.pic', 'warehouse', 'issuer', 'confirmer', 'reason', 'reversalOf');
        $lines = $isu->lines()->with('bin', 'item.baseUom', 'item.activeConversions.uom', 'lot', 'serial', 'piece')->orderBy('id')->get();

        return ['isu' => $isu, 'lines' => $lines, 'pelaku' => [$this->at($isu->issuer, $isu->created_at), $this->at($isu->confirmer, $isu->confirmed_at), $isu->project?->pic]];
    }

    /** @return array<string, mixed> */
    private function conversion(Conversion $cnv): array
    {
        $cnv->loadMissing('project.pic', 'warehouse', 'preparer', 'completer', 'approver', 'reason', 'reversalOf');
        $inputs = $cnv->inputs()->with('bin', 'item.baseUom', 'item.activeConversions.uom', 'lot', 'piece')->orderBy('id')->get();
        $outputs = $cnv->outputs()->with('bin', 'item.baseUom', 'item.activeConversions.uom', 'lot', 'newPiece', 'parentInput.piece', 'reason')->orderBy('id')->get();

        return ['cnv' => $cnv, 'inputs' => $inputs, 'outputs' => $outputs, 'pelaku' => [$this->at($cnv->completer ?? $cnv->preparer, $cnv->completed_at ?? $cnv->created_at), $this->at($cnv->approver, $cnv->approved_at), $cnv->project?->pic]];
    }

    /** @return array<string, mixed> */
    private function wasteDisposal(WasteDisposal $wst): array
    {
        $wst->loadMissing('project', 'warehouse', 'targetBin', 'submitter', 'approver');
        $lines = $wst->lines()->with('bin', 'item.baseUom', 'item.activeConversions.uom', 'lot', 'serial', 'piece', 'reason')->orderBy('id')->get();

        return ['wst' => $wst, 'lines' => $lines, 'pelaku' => [$this->at($wst->submitter, $wst->created_at), $this->at($wst->approver, $wst->approved_at), null]];
    }

    /** @return array<string, mixed> */
    private function assetHandover(AssetHandover $ast): array
    {
        $ast->loadMissing('serial', 'item.baseUom', 'item.activeConversions.uom', 'project.pic', 'warehouse', 'shipment.driver', 'goodsReturn', 'updater');
        $periksa = $ast->inspections()->with('inspector')->latest('id')->first();

        return ['ast' => $ast, 'periksa' => $periksa, 'pelaku' => [$this->at($ast->updater ?? $ast->shipment?->driverName(), $ast->checked_out_at), $ast->project?->pic, $this->at($periksa?->inspector, $periksa?->created_at)]];
    }

    /**
     * PO modul Purchasing (A-217): satu-satunya cetakan bernilai uang; izin
     * lihatnya `po.view`.
     *
     * @return array<string, mixed>
     */
    private function purchaseOrder(PurchaseOrder $po): array
    {
        $po->loadMissing('vendor', 'warehouse', 'creator', 'approver');

        return [
            'po' => $po,
            'lines' => $po->lines()->with('item.baseUom', 'item.activeConversions.uom', 'requestLine.purchaseRequest:id,number')->orderBy('id')->get(),
            'bernilai' => true,
            'pelaku' => [$this->at($po->creator, $po->created_at), $this->at($po->approver, $po->approved_at), null],
        ];
    }

    /**
     * Pelaku beserta waktu tindakannya, untuk segel tanda tangan (A-264).
     *
     * @return array{who: mixed, at: ?CarbonInterface}|null
     */
    private function at(mixed $who, mixed $at): ?array
    {
        return $who === null || $who === '' ? null : ['who' => $who, 'at' => $at instanceof CarbonInterface ? $at : null];
    }

    /**
     * Kotak ke-n memuat pelaku ke-n dari daftar bawaan jenis dokumen (A-125).
     * Kotak yang pelakunya diketahui mendapat segel tanda tangan ber-QR yang
     * bisa diverifikasi publik (A-264).
     *
     * @param  array<int, string>  $blok
     * @param  array<int, mixed>  $pelaku  User, nama, ['who', 'at'], atau ['name', 'signature', 'at', 'user']
     * @return array<int, array{label: string, name: ?string, signature: ?string, qr: ?string, at: ?CarbonInterface, code: ?string}>
     */
    private function signatures(DocumentTemplateType $type, Model $model, string $nomor, array $blok, array $pelaku): array
    {
        $hasil = [];

        foreach (array_values($blok) as $i => $label) {
            $orang = $pelaku[$i] ?? null;
            $waktu = null;
            $user = null;

            if (is_array($orang) && array_key_exists('who', $orang)) {
                $waktu = $orang['at'];
                $orang = $orang['who'];
            }

            [$nama, $ttd] = match (true) {
                $orang instanceof User => [$orang->name, $orang->signature_path],
                is_array($orang) => [$orang['name'] ?? null, $orang['signature'] ?? null],
                is_string($orang) && $orang !== '' => [$orang, null],
                default => [null, null],
            };

            if ($orang instanceof User) {
                $user = $orang;
            } elseif (is_array($orang)) {
                $waktu = $orang['at'] ?? $waktu;
                $user = ($orang['user'] ?? null) instanceof User ? $orang['user'] : null;
            }

            $segel = $nama !== null && trim((string) $nama) !== ''
                ? $this->seals->issue($type, $model, $nomor, $i + 1, $label, $user, (string) $nama, $waktu)
                : null;

            $hasil[] = [
                'label' => $label,
                'name' => $nama,
                'signature' => $this->assets->image($ttd),
                'qr' => $segel !== null ? $this->assets->qr($this->seals->url($segel)) : null,
                'at' => $segel?->acted_at ?? $segel?->sealed_at,
                'code' => $segel?->shortCode(),
            ];
        }

        return $hasil;
    }
}

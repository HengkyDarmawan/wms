<?php

declare(strict_types=1);

namespace App\Domain\Template\Support;

use App\Domain\Access\Models\User;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Issue\Models\MaterialIssue;
use App\Domain\Receipt\Models\VendorReturn;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Shipment\Models\DeliveryDiscrepancy;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Template\Enums\DocumentTemplateType;
use App\Domain\Template\Models\DocumentLayout;
use App\Domain\Template\Models\DocumentTemplate;
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
    public function __construct(private readonly PrintAssets $assets) {}

    /** Cari dokumen, periksa izin lihat, lalu kirim PDF inline. */
    public function stream(DocumentTemplateType $type, int $id, User $actor): Response
    {
        $model = $this->find($type, $id);
        $this->authorize($type, $model, $actor);

        return PdfRenderer::make($this->view($type, $model)->render(), DocumentTemplate::forType($type)->paper)
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
    public function view(DocumentTemplateType $type, Model $model): View
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
            default => throw new NotFoundHttpException,
        };

        $pelaku = $data['pelaku'];
        unset($data['pelaku']);

        return view('print.documents.'.$type->value, $data + [
            'type' => $type,
            'kop' => $this->assets->kop($layout),
            'nomor' => $this->number($type, $model),
            'status' => $model->status?->label(),
            'batal' => ($model->status?->value ?? null) === 'cancelled',
            'qr' => $this->assets->qr($this->url($type, $model)),
            'tandaTangan' => $this->signatures($layout->signatureBlocksFor($type), $pelaku),
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
            default => url('/'),
        };
    }

    /** @return array<string, mixed> */
    private function shipment(Shipment $sj): array
    {
        $sj->loadMissing('warehouse', 'destinationProject', 'destinationWarehouse', 'destinationVendor', 'vehicle', 'driver', 'carrier', 'proof');

        $lines = $sj->lines()
            ->with('pickTaskLine.item.baseUom', 'pickTaskLine.lot', 'pickTaskLine.serial', 'pickTaskLine.piece', 'pickTaskLine.pickTask')
            ->orderBy('id')->get();

        $pickIds = $lines->pluck('pickTaskLine.pick_task_id')->filter()->unique();
        $reqIds = PickTask::query()->whereIn('id', $pickIds)->where('source_type', 'material_request')->pluck('source_id');

        $proof = $sj->proof;

        return [
            'sj' => $sj,
            'lines' => $lines,
            'rujukan' => [
                'pck' => PickTask::query()->whereIn('id', $pickIds)->orderBy('id')->pluck('number')->all(),
                'req' => MaterialRequest::query()->withoutGlobalScopes()->whereIn('id', $reqIds)->orderBy('id')->pluck('number')->all(),
            ],
            'pelaku' => [
                null,
                $sj->driver ?? ($sj->carried_by_name ?: $sj->carrier?->name),
                $proof ? ['name' => $proof->received_by_name, 'signature' => $proof->signature_path] : null,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function proof(Shipment $sj): array
    {
        $sj->loadMissing('warehouse', 'destinationProject', 'destinationWarehouse', 'destinationVendor', 'driver', 'carrier', 'vehicle');
        $proof = $sj->proof()->with('receivedByUser', 'lines.shipmentLine.pickTaskLine.item.baseUom', 'lines.shipmentLine.pickTaskLine.lot', 'lines.shipmentLine.pickTaskLine.serial', 'lines.shipmentLine.pickTaskLine.piece')->firstOrFail();

        return [
            'sj' => $sj,
            'proof' => $proof,
            'lines' => $proof->lines->sortBy('id')->values(),
            'pelaku' => [
                $sj->driver ?? ($sj->carried_by_name ?: $sj->carrier?->name),
                ['name' => $proof->received_by_name ?: $proof->receivedByUser?->name, 'signature' => $proof->signature_path],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function pickTask(PickTask $pck): array
    {
        $pck->loadMissing('warehouse', 'assignee');

        $lines = $pck->lines()->with('bin', 'item.baseUom', 'lot', 'serial', 'piece')->get()
            ->sortBy(fn ($l) => [$l->bin?->code ?? '', $l->id])->values();

        $req = $pck->source_type === 'material_request'
            ? MaterialRequest::query()->withoutGlobalScopes()->whereKey($pck->source_id)->value('number')
            : null;

        return ['pck' => $pck, 'lines' => $lines, 'req' => $req, 'pelaku' => [$pck->assignee, null]];
    }

    /** @return array<string, mixed> */
    private function discrepancy(DeliveryDiscrepancy $dsc): array
    {
        $dsc->loadMissing('resolver');
        $sj = Shipment::query()->with('warehouse', 'driver', 'destinationProject', 'destinationWarehouse', 'destinationVendor')->findOrFail($dsc->shipment_id);

        $lines = $dsc->lines()->with('reasonCode', 'shipmentLine.pickTaskLine.item.baseUom', 'shipmentLine.pickTaskLine.lot', 'shipmentLine.pickTaskLine.serial', 'shipmentLine.pickTaskLine.piece')
            ->orderBy('id')->get();

        return ['dsc' => $dsc, 'sj' => $sj, 'lines' => $lines, 'pelaku' => [$dsc->resolver, $sj->driver]];
    }

    /** @return array<string, mixed> */
    private function vendorReturn(VendorReturn $rtv): array
    {
        $rtv->loadMissing('warehouse', 'vendor', 'receipt', 'submitter', 'approver');
        $lines = $rtv->lines()->with('item.baseUom', 'lot', 'serial', 'piece', 'bin', 'reason')->orderBy('id')->get();

        return ['rtv' => $rtv, 'lines' => $lines, 'pelaku' => [$rtv->submitter, $rtv->approver, $rtv->vendor?->name]];
    }

    /** @return array<string, mixed> */
    private function adjustment(StockAdjustment $adj): array
    {
        $adj->loadMissing('warehouse', 'reason', 'submitter', 'approver', 'stockCount', 'reversalOf');
        $lines = $adj->lines()->with('bin', 'item.baseUom', 'lot', 'serial', 'piece', 'reason')->orderBy('id')->get();

        return ['adj' => $adj, 'lines' => $lines, 'pelaku' => [$adj->submitter, $adj->approver]];
    }

    /** @return array<string, mixed> */
    private function materialIssue(MaterialIssue $isu): array
    {
        $isu->loadMissing('project.pic', 'warehouse', 'issuer', 'confirmer', 'reason', 'reversalOf');
        $lines = $isu->lines()->with('bin', 'item.baseUom', 'lot', 'serial', 'piece')->orderBy('id')->get();

        return ['isu' => $isu, 'lines' => $lines, 'pelaku' => [$isu->issuer, $isu->confirmer, $isu->project?->pic]];
    }

    /**
     * Kotak ke-n memuat pelaku ke-n dari daftar bawaan jenis dokumen (A-125).
     *
     * @param  array<int, string>  $blok
     * @param  array<int, mixed>  $pelaku  User, nama, atau ['name', 'signature']
     * @return array<int, array{label: string, name: ?string, signature: ?string}>
     */
    private function signatures(array $blok, array $pelaku): array
    {
        $hasil = [];

        foreach (array_values($blok) as $i => $label) {
            $orang = $pelaku[$i] ?? null;

            [$nama, $ttd] = match (true) {
                $orang instanceof User => [$orang->name, $orang->signature_path],
                is_array($orang) => [$orang['name'] ?? null, $orang['signature'] ?? null],
                is_string($orang) && $orang !== '' => [$orang, null],
                default => [null, null],
            };

            $hasil[] = ['label' => $label, 'name' => $nama, 'signature' => $this->assets->image($ttd)];
        }

        return $hasil;
    }
}

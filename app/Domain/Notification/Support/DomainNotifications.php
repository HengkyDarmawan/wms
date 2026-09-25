<?php

declare(strict_types=1);

namespace App\Domain\Notification\Support;

use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Serial;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Shipment\Models\DeliveryDiscrepancy;
use App\Domain\Shipment\Models\ProofOfDelivery;

/**
 * Penerima dan isi notifikasi per kejadian modul (Blueprint §10, A-189).
 * Dipanggil setelah transisi berhasil; kegagalan kirim tidak membatalkan
 * transaksi dokumen.
 */
class DomainNotifications
{
    public function __construct(private readonly Notifier $notifier) {}

    /** REQ klien/internal belum lengkap masuk tinjauan staf (14-request §8). */
    public function requestUnderReview(MaterialRequest $req, ?User $actor = null): void
    {
        $this->aman(fn () => $this->notifier->send(
            $this->notifier->recipients('request.review', null, (int) $req->project_id),
            'request.under_review', 'REQ '.$req->number.' menunggu tinjauan',
            'Tentukan gudang sumber dan cara pemenuhan.', route('requests.show', $req->id, false),
            'material_request', (int) $req->id, [], $actor,
        ));
    }

    /** Bukti terima tercatat: pemohon REQ diminta konfirmasi atau keberatan (BR-REQ-10). */
    public function deliveryReceived(ProofOfDelivery $pod, ?User $actor = null): void
    {
        $this->aman(function () use ($pod, $actor) {
            $sj = $pod->shipment;
            $reqIds = $sj->lines()->with('pickTaskLine.pickTask')->get()
                ->map(fn ($l) => $l->pickTaskLine?->pickTask)
                ->filter(fn ($t) => $t?->source_type === 'material_request')
                ->pluck('source_id')->unique();

            foreach (MaterialRequest::query()->withoutGlobalScopes()->whereIn('id', $reqIds)->get() as $req) {
                $pemohon = User::query()->find($req->requester_id);

                if ($pemohon === null || ! $pemohon->hasPermission('request.confirm_receipt')) {
                    continue;
                }

                $this->notifier->send($pemohon, 'delivery.received',
                    'Barang '.$req->number.' diterima ('.$sj->number.')',
                    'Konfirmasi terima atau ajukan keberatan sebelum '.$pod->confirm_deadline_at?->timezone(tenant()?->timezone ?? 'Asia/Jakarta')->format('d/m/Y H:i').'.',
                    route($pemohon->isClient() ? 'portal.requests.show' : 'requests.show', $req->id, false),
                    'shipment', (int) $sj->id, [], $actor);
            }
        });
    }

    public function discrepancyOpened(DeliveryDiscrepancy $dsc, ?User $actor = null): void
    {
        $this->aman(fn () => $this->notifier->send(
            $this->notifier->recipients('discrepancy.resolve', (int) $dsc->shipment->warehouse_id),
            'discrepancy.opened', 'Selisih pengiriman '.$dsc->number.' ('.$dsc->origin->label().')',
            'SJ '.$dsc->shipment->number.' perlu diselesaikan.', route('discrepancies.index', [], false),
            'delivery_discrepancy', (int) $dsc->id, [], $actor,
        ));
    }

    public function purchaseRequestApproved(PurchaseRequest $prq, ?User $actor = null): void
    {
        $this->aman(fn () => $this->notifier->send(
            $this->notifier->recipients('pr.order', (int) $prq->warehouse_id),
            'purchase_request.approved', 'PRQ '.$prq->number.' disetujui',
            'Catat pemesanan ke vendor/toko.', route('purchase-requests.show', $prq->id, false),
            'purchase_request', (int) $prq->id, [], $actor,
        ));
    }

    public function reorderDraftCreated(PurchaseRequest $prq): void
    {
        $this->aman(fn () => $this->notifier->send(
            $this->notifier->recipients('pr.submit', (int) $prq->warehouse_id),
            'purchase_request.reorder_draft', 'Draf PRQ titik pesan ulang '.$prq->number,
            'Tinjau jumlahnya lalu ajukan atau batalkan.', route('purchase-requests.show', $prq->id, false),
            'purchase_request', (int) $prq->id,
        ));
    }

    /** Pengingat harian aset lewat jatuh tempo (BR-AST-06). */
    public function assetsOverdue(): int
    {
        $jumlah = 0;

        foreach (Serial::query()->overdue()->with('item:id,code')->get() as $aset) {
            $proyek = $aset->current_project_id !== null ? (int) $aset->current_project_id : null;
            $jumlah += $this->notifier->send(
                $this->notifier->recipients('asset.manage', null, $proyek),
                'asset.overdue', 'Aset '.$aset->item?->code.' '.$aset->serial_no.' lewat jatuh tempo',
                'Seharusnya kembali '.$aset->due_return_date?->format('d/m/Y').'.', route('assets.show', $aset->id, false),
                'serial', (int) $aset->id,
            );
        }

        return $jumlah;
    }

    private function aman(callable $kirim): void
    {
        try {
            $kirim();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

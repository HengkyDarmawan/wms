<?php

declare(strict_types=1);

namespace App\Domain\Notification\Support;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Models\ApprovalSnapshot;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Project;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Request\Models\MaterialRequestLine;
use App\Domain\Shipment\Models\DeliveryDiscrepancy;
use App\Domain\Shipment\Models\ProofOfDelivery;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

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

    /** Item `provisional` lahir dari baris non-katalog: Admin Company melengkapinya (11 §8, BR-REQ-03). */
    public function provisionalItemCreated(Item $item, MaterialRequest $req, ?User $actor = null): void
    {
        $this->aman(fn () => $this->notifier->send(
            $this->notifier->recipients('item.create'),
            'item.provisional_created', 'Item sementara '.$item->code.' dibuat dari '.$req->number,
            'Lengkapi data item sebelum penerimaan pertama.', route('items.show', $item->id, false),
            'item', (int) $item->id, [], $actor,
        ));
    }

    /** Proyek ditutup/dibatalkan: PIC dan Kepala Gudang yang mencakup proyek (11 §8, BR-PRJ-02). */
    public function projectClosed(Project $project, ?User $actor = null): void
    {
        $this->aman(function () use ($project, $actor) {
            $penerima = $this->notifier->recipients('warehouse.update', null, (int) $project->id);

            if ($project->pic_user_id !== null && ($pic = User::query()->find($project->pic_user_id)) !== null) {
                $penerima->push($pic);
            }

            $this->notifier->send($penerima, 'project.closed',
                'Proyek '.$project->code.' '.mb_strtolower($project->status->label()),
                $project->close_reason, route('projects.show', $project->id, false),
                'project', (int) $project->id, [], $actor);
        });
    }

    /** Tanggal kunci periode stok maju: Admin Company & semua Kepala Gudang (13 §8, BR-STK-15). */
    public function stockPeriodLocked(string $date, ?User $actor = null): void
    {
        $this->aman(fn () => $this->notifier->send(
            $this->notifier->recipients('warehouse.update'),
            'stock.period_locked', 'Periode stok dikunci sampai '.Carbon::parse($date)->format('d/m/Y'),
            'Mutasi bertanggal pada atau sebelum tanggal itu ditolak; koreksi diposting di periode berjalan.',
            route('stock.period', [], false), null, null, [], $actor,
        ));
    }

    /** Rekonsiliasi saldo terjadwal menemukan selisih (BR-STK-01, A-243): Admin Company. */
    public function balanceMismatch(int $count): void
    {
        $this->aman(fn () => $this->notifier->send(
            $this->notifier->recipients('stock.lock_period'),
            'stock.balance_mismatch', $count.' saldo stok tidak cocok dengan kartu stok',
            'Saldo tidak diubah otomatis; periksa kartu stok item terkait dan koreksi lewat ADJ bila perlu.',
            route('stock.index', [], false),
        ));
    }

    /** REQ diputus — ditolak saat tinjau atau keputusan akhir approval — ke pemohon (14 §8). */
    public function requestDecided(MaterialRequest $req, ?User $actor = null): void
    {
        $this->aman(function () use ($req, $actor) {
            $pemohon = User::query()->find($req->requester_id);

            // Pengaju approval sudah menerima `approval.decided`; jangan dobel.
            $pengaju = $req->approval_snapshot_id !== null
                ? ApprovalSnapshot::query()->whereKey($req->approval_snapshot_id)->value('submitted_by')
                : null;

            if ($pemohon === null || ($pengaju !== null && (int) $pengaju === (int) $pemohon->id)) {
                return;
            }

            $this->notifier->send($pemohon, 'request.decided',
                'REQ '.$req->number.' '.mb_strtolower($req->status->label()), null,
                $this->tautanReq($pemohon, $req), 'material_request', (int) $req->id, [], $actor);
        });
    }

    /** Baris dipetakan ke item lain: klien punya tenggat keberatan (14 §8, BR-REQ-13). */
    public function lineSubstituted(MaterialRequestLine $line, ?User $actor = null): void
    {
        $this->aman(function () use ($line, $actor) {
            $req = $line->request;
            $pemohon = User::query()->find($req->requester_id);

            if ($pemohon === null) {
                return;
            }

            $this->notifier->send($pemohon, 'request.line_substituted',
                'Barang di '.$req->number.' diganti '.$line->item?->code,
                'Semula: '.$line->original_item_text.'. Ajukan keberatan sebelum '.$this->jam($line->substitution_deadline_at).'.',
                $this->tautanReq($pemohon, $req), 'material_request_line', (int) $line->id, [], $actor);
        });
    }

    /** Tanggal janji baris ditetapkan atau berubah (14 §8, BR-REQ-14). */
    public function promiseChanged(MaterialRequestLine $line, ?User $actor = null): void
    {
        $this->aman(function () use ($line, $actor) {
            $req = $line->request;
            $pemohon = User::query()->find($req->requester_id);

            if ($pemohon === null) {
                return;
            }

            $line->loadMissing('item');
            $this->notifier->send($pemohon, 'request.promise_changed',
                'Tanggal janji '.$req->number.' berubah',
                $line->displayName().': '.($line->promised_date?->format('d/m/Y') ?? 'belum ditetapkan').'.',
                $this->tautanReq($pemohon, $req), 'material_request_line', (int) $line->id, [], $actor);
        });
    }

    /** Klien meminta pembatalan baris: staf/Kepala Gudang yang boleh mengonfirmasi (14 §8, BR-REQ-15). */
    public function lineCancelRequested(MaterialRequestLine $line, ?User $actor = null): void
    {
        $this->aman(function () use ($line, $actor) {
            $req = $line->request;
            $line->loadMissing('item');

            $this->notifier->send($this->notifier->recipients('request.confirm_cancel', null, (int) $req->project_id),
                'request.line_cancel_requested', 'Permintaan batal baris '.$req->number,
                $line->displayName().' — pastikan belum berjalan, lalu konfirmasi atau tolak.',
                route('requests.show', $req->id, false), 'material_request_line', (int) $line->id, [], $actor);
        });
    }

    /** Hasil permintaan pembatalan baris kembali ke pemohon (A-61). */
    public function lineCancelDecided(MaterialRequestLine $line, bool $confirmed, ?User $actor = null): void
    {
        $this->aman(function () use ($line, $confirmed, $actor) {
            $req = $line->request;
            $pemohon = User::query()->find($req->requester_id);

            if ($pemohon === null) {
                return;
            }

            $line->loadMissing('item');
            $this->notifier->send($pemohon, 'request.line_cancel_decided',
                'Pembatalan baris '.$req->number.' '.($confirmed ? 'dikonfirmasi' : 'ditolak'),
                $line->displayName().($confirmed ? ' dibatalkan.' : ' tetap diproses.'),
                $this->tautanReq($pemohon, $req), 'material_request_line', (int) $line->id, [], $actor);
        });
    }

    /** Klien membuka REQ lewat portal; pengguna internal lewat layar staf. */
    public function tautanReq(User $user, MaterialRequest $req): string
    {
        return route($user->isClient() ? 'portal.requests.show' : 'requests.show', $req->id, false);
    }

    private function jam(?CarbonInterface $waktu): string
    {
        return $waktu?->timezone(tenant()?->timezone ?? 'Asia/Jakarta')->format('d/m/Y H:i') ?? '-';
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

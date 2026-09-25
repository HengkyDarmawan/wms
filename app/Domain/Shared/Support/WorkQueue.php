<?php

declare(strict_types=1);

namespace App\Domain\Shared\Support;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalTaskStatus;
use App\Domain\Approval\Models\ApprovalTask;
use App\Domain\Count\Enums\StockCountStatus;
use App\Domain\Count\Models\StockCount;
use App\Domain\Master\Models\Serial;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestStatus;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\Receipt\Enums\GoodsReceiptStatus;
use App\Domain\Receipt\Enums\PutawayTaskStatus;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\PutawayTask;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Shipment\Enums\DiscrepancyStatus;
use App\Domain\Shipment\Enums\PickTaskStatus;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Shipment\Models\DeliveryDiscrepancy;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Shipment\Models\Shipment;

/**
 * Beranda back-office (A-186): kartu "pekerjaan menunggu" per pengguna. Setiap
 * kartu hanya muncul bila pengguna memegang izin lihat modulnya, dan hitungan
 * memakai cakupan gudang/proyek pengguna (global scope, BR-ACC-05).
 */
class WorkQueue
{
    /** @return array<int, array{label: string, count: int, url: string, icon: string, tone: string, hint: string}> */
    public function for(User $user): array
    {
        $kartu = [];

        $tambah = function (string $izin, string $label, callable $hitung, string $url, string $icon, string $hint, string $tone = 'primary') use ($user, &$kartu): void {
            if (! $user->can($izin)) {
                return;
            }

            $kartu[] = ['label' => $label, 'count' => (int) $hitung(), 'url' => $url, 'icon' => $icon, 'tone' => $tone, 'hint' => $hint];
        };

        $tambah('approval-inbox', __('Tugas approval saya'),
            fn () => ApprovalTask::query()->where('approver_user_id', $user->id)->where('status', ApprovalTaskStatus::Open->value)->count(),
            route('approval.inbox'), 'bi-check2-square', __('menunggu keputusan Anda'), 'warning');

        $tambah('request.review', __('REQ perlu ditinjau'),
            fn () => MaterialRequest::query()->where('status', MaterialRequestStatus::UnderReview->value)->count(),
            route('requests.index', ['statusFilter' => MaterialRequestStatus::UnderReview->value]), 'bi-inbox', __('tentukan gudang & cara pemenuhan'));

        $tambah('pick.view', __('Picking berjalan'),
            fn () => PickTask::query()->whereIn('status', [PickTaskStatus::Pending->value, PickTaskStatus::InProgress->value])->count(),
            route('picks.index'), 'bi-basket', __('menunggu atau sedang dipetik'));

        $tambah('shipment.view', __('SJ dalam perjalanan'),
            fn () => Shipment::query()->where('status', ShipmentStatus::Shipped->value)->count(),
            route('shipments.index'), 'bi-truck', __('menunggu bukti terima'));

        $tambah('discrepancy.view', __('Selisih pengiriman terbuka'),
            fn () => DeliveryDiscrepancy::query()->where('status', DiscrepancyStatus::Open->value)->count(),
            route('discrepancies.index'), 'bi-exclamation-diamond', __('perlu diselesaikan'), 'danger');

        $tambah('receipt.view', __('GRN belum selesai'),
            fn () => GoodsReceipt::query()->whereIn('status', [GoodsReceiptStatus::Draft->value, GoodsReceiptStatus::Received->value])->count(),
            route('receipts.index'), 'bi-box-arrow-in-down', __('draf, QC, atau belum diselesaikan'));

        $tambah('putaway.view', __('Put-away menunggu'),
            fn () => PutawayTask::query()->where('status', PutawayTaskStatus::Pending->value)->count(),
            route('putaways.index'), 'bi-box-seam', __('barang di bin Penerimaan'));

        $tambah('pr.view', __('PRQ belum dipesan'),
            fn () => PurchaseRequest::query()->where('status', PurchaseRequestStatus::Approved->value)->count(),
            route('purchase-requests.index', ['statusFilter' => PurchaseRequestStatus::Approved->value]), 'bi-cart3', __('disetujui, menunggu catatan pemesanan'));

        $tambah('pr.submit', __('Draf PRQ titik pesan ulang'),
            fn () => PurchaseRequest::query()->where('status', PurchaseRequestStatus::Draft->value)->count(),
            route('purchase-requests.index', ['statusFilter' => PurchaseRequestStatus::Draft->value]), 'bi-arrow-repeat', __('tinjau lalu ajukan'));

        $tambah('count.view', __('Stock opname berjalan'),
            fn () => StockCount::query()->whereIn('status', [StockCountStatus::InProgress->value, StockCountStatus::Recount->value, StockCountStatus::Reconciling->value])->count(),
            route('counts.index'), 'bi-clipboard-data', __('hitung, hitung ulang, rekonsiliasi'));

        $tambah('asset.view', __('Aset lewat jatuh tempo'),
            fn () => Serial::query()->overdue()->count(),
            route('assets.index', ['perhatian' => 1]), 'bi-alarm', __('belum kembali dari proyek'), 'danger');

        return $kartu;
    }
}

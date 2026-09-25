<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Actions;

use App\Domain\Access\Models\User;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestStatus;
use App\Domain\PurchaseRequest\Exceptions\PurchaseRequestRuleException;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\PurchaseRequest\Support\PurchaseRequestFlow;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `pr.submit` — draf titik pesan ulang `draft → submitted`
 * (Katalog §2.15, BR-REQ-11) setelah ditinjau Kepala Gudang; kejadian
 * `purchase_requested`, lalu `pending_approval`/`approved` sesuai aturan.
 */
class SubmitPurchaseRequest
{
    public function __construct(private readonly PurchaseRequestFlow $flow) {}

    public function handle(PurchaseRequest $prq, ?User $actor = null): PurchaseRequest
    {
        return DB::transaction(function () use ($prq, $actor) {
            $prq = PurchaseRequest::withoutGlobalScopes()->lockForUpdate()->findOrFail($prq->id);

            if ($prq->status !== PurchaseRequestStatus::Draft) {
                throw PurchaseRequestRuleException::rule('BR-GEN-01', 'Hanya PRQ berstatus Draf yang bisa diajukan.');
            }

            if ($actor !== null && ! $actor->canAccessWarehouse((int) $prq->warehouse_id)) {
                throw PurchaseRequestRuleException::rule('BR-ACC-05', 'Gudang PRQ ini di luar cakupan Anda.');
            }

            // Katalog §2.15 guard "baris ditinjau": minimal satu baris berjumlah > 0.
            if ($prq->lines()->where('qty_base', '>', 0)->doesntExist()) {
                throw PurchaseRequestRuleException::rule('BR-GEN-11', 'Draf tanpa baris berjumlah tidak bisa diajukan.');
            }

            return $this->flow->submit($prq, $actor);
        });
    }
}

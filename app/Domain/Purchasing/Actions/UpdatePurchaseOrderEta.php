<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Purchasing\Exceptions\PurchasingRuleException;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Support\PurchaseOrderIssuer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `po.create` — ubah perkiraan datang PO yang masih ditunggu
 * (`po_updated`, A-213): ETA catatan pemesanan di gudang ikut berubah.
 */
class UpdatePurchaseOrderEta
{
    public function __construct(private readonly PurchaseOrderIssuer $issuer) {}

    public function handle(PurchaseOrder $po, mixed $etaDate, ?User $actor = null): PurchaseOrder
    {
        if (! $po->status->isOpen()) {
            throw PurchasingRuleException::rule('BR-GEN-01', 'ETA hanya bisa diubah pada PO yang disetujui dan barangnya masih ditunggu.');
        }

        $isi = trim((string) $etaDate);

        try {
            $eta = $isi === '' ? null : Carbon::parse($isi)->toDateString();
        } catch (\Throwable) {
            throw PurchasingRuleException::field('BR-GEN-11', 'eta_date', 'Perkiraan datang tidak valid.');
        }

        return DB::transaction(function () use ($po, $eta, $actor) {
            $lama = $po->eta_date?->toDateString();
            $po->forceFill(['eta_date' => $eta])->save();
            $this->issuer->update($po);

            activity('purchase_order')->performedOn($po)->causedBy($actor)
                ->withProperties(['dari' => $lama, 'ke' => $eta])
                ->log('ETA PO diubah');

            return $po->refresh();
        });
    }
}

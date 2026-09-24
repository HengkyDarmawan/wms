<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Receipt\Enums\VendorReturnStatus;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Receipt\Models\VendorReturn;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Exceptions\LedgerException;
use App\Domain\Stock\Support\MovementRequest;
use App\Domain\Stock\Support\StockLedger;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `vendor_return.ship` — `approved` → `shipped` (Katalog §2.16).
 *
 * Ledger: Karantina → keluar; kejadian `goods_rejected` dengan penanda
 * `grn_ref` (matriks §14). Dokumen RTV yang dicetak menjadi surat jalan
 * returnya (A-80). Setelah ini RTV tidak bisa dibatalkan (BR-GEN-03).
 */
class ShipVendorReturn
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function handle(VendorReturn $rtv, ?string $notes = null, ?User $actor = null): VendorReturn
    {
        if ($rtv->status !== VendorReturnStatus::Approved) {
            throw ReceiptRuleException::rule('BR-GEN-01', 'Hanya RTV yang sudah disetujui yang bisa dikirim.');
        }

        $rtv->loadMissing('receipt', 'vendor');
        $lines = $rtv->lines()->with('item')->orderBy('id')->get();

        return DB::transaction(function () use ($rtv, $lines, $notes, $actor) {
            foreach ($lines as $l) {
                try {
                    $this->ledger->post(new MovementRequest(
                        item: $l->item,
                        qtyBase: (float) $l->qty_base,
                        fromBinId: (int) $l->bin_id,
                        toBinId: null,
                        stockStatus: $l->stock_status,
                        lotId: $l->lot_id,
                        serialId: $l->serial_id,
                        pieceId: $l->piece_id,
                        documentType: 'vendor_return',
                        documentId: (int) $rtv->id,
                        documentLineId: (int) $l->id,
                        documentNumber: $rtv->number,
                        reasonCodeId: $l->reason_code_id,
                        performedBy: $actor,
                        eventType: StockEventType::GoodsRejected,
                        eventPayload: [
                            'rtv_number' => $rtv->number,
                            'grn_ref' => $rtv->receipt?->number,
                            'vendor_id' => $rtv->vendor_id,
                            'vendor_name' => $rtv->vendor?->name,
                        ],
                    ));
                } catch (LedgerException $e) {
                    throw ReceiptRuleException::rule($e->rule, 'Baris '.$l->item->code.' gagal dikirim: '.$e->getMessage());
                }
            }

            $rtv->forceFill([
                'status' => VendorReturnStatus::Shipped,
                'shipped_at' => now(),
                'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : $rtv->notes,
            ])->save();

            activity('receipt')->performedOn($rtv)->causedBy($actor)
                ->withProperties(['baris' => $lines->count()])
                ->log('RTV dikirim ke vendor');

            return $rtv->refresh();
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Actions\SaveVendor;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Enums\VendorType;
use App\Domain\Master\Models\Vendor;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestStatus;
use App\Domain\PurchaseRequest\Exceptions\PurchaseRequestRuleException;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\PurchaseRequest\Models\PurchaseRequestLine;
use App\Domain\PurchaseRequest\Models\PurchaseRequestOrder;
use App\Domain\PurchaseRequest\Models\PurchaseRequestOrderLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `pr.order` — mencatat **catatan pemesanan** per vendor/toko
 * (Katalog §2.15 `approved → forwarded`, A-51). Satu PRQ boleh punya banyak
 * catatan; satu baris PRQ boleh dipecah ke beberapa vendor (Σ dipesan ≤
 * jumlah baris). Vendor baru boleh dibuat **sementara** dari form ini (A-53).
 * Catatan boleh ditambah selama PRQ disetujui/diteruskan/sebagian terpenuhi.
 */
class OrderPurchaseRequest
{
    public function __construct(private readonly SaveVendor $vendors) {}

    /**
     * @param  array{vendor_id?: mixed, new_vendor_name?: mixed, new_vendor_type?: mixed, new_vendor_phone?: mixed, external_po_no?: mixed, marketplace_order_no?: mixed, tracking_no?: mixed, eta_date?: mixed, vendor_note?: mixed, notes?: mixed}  $header
     * @param  array<int|string, mixed>  $lines  purchase_request_line_id => qty_ordered
     */
    public function handle(PurchaseRequest $prq, array $header, array $lines, ?User $actor = null): PurchaseRequestOrder
    {
        if (! $prq->status->acceptsOrders()) {
            throw PurchaseRequestRuleException::rule('BR-GEN-01', 'Catatan pemesanan hanya untuk PRQ yang sudah disetujui (status sekarang '.$prq->status->label().').');
        }

        $jumlah = [];
        $baris = $prq->lines()->with('item:id,code')->get()->keyBy('id');

        foreach ($lines as $id => $qty) {
            $q = is_numeric($qty) ? round((float) $qty, 4) : 0.0;

            if ($q == 0.0) {
                continue;
            }

            $l = $baris->get((int) $id) ?? throw PurchaseRequestRuleException::field('BR-GEN-11', 'lines', 'Baris bukan milik PRQ ini.');

            if ($q < 0) {
                throw PurchaseRequestRuleException::field('BR-LED-02', 'lines', $l->item?->code.': jumlah dipesan harus lebih dari nol.');
            }

            if ($q - $l->unorderedQty() > 0.00005) {
                throw PurchaseRequestRuleException::field('BR-REQ-08', 'lines', $l->item?->code.': dipesan '.$q.' melebihi sisa yang belum dipesan ('.$l->unorderedQty().').');
            }

            $jumlah[(int) $l->id] = $q;
        }

        if ($jumlah === []) {
            throw PurchaseRequestRuleException::field('BR-GEN-11', 'lines', 'Isi jumlah dipesan minimal satu baris.'); // A-51
        }

        $eta = null;

        if (($isi = trim((string) ($header['eta_date'] ?? ''))) !== '') {
            try {
                $eta = Carbon::parse($isi)->toDateString();
            } catch (\Throwable) {
                throw PurchaseRequestRuleException::field('BR-GEN-11', 'eta_date', 'Perkiraan datang tidak valid.');
            }
        }

        return DB::transaction(function () use ($prq, $header, $jumlah, $eta, $actor) {
            $vendor = $this->vendor($header, $actor);

            $catatan = PurchaseRequestOrder::create([
                'purchase_request_id' => $prq->id,
                'vendor_id' => $vendor->id,
                'external_po_no' => $this->teks($header['external_po_no'] ?? null, 60),
                'marketplace_order_no' => $this->teks($header['marketplace_order_no'] ?? null, 60),
                'tracking_no' => $this->teks($header['tracking_no'] ?? null, 60),
                'eta_date' => $eta,
                'ordered_by' => $actor?->id,
                'ordered_at' => now(),
                'vendor_note' => $this->teks($header['vendor_note'] ?? null),
                'notes' => $this->teks($header['notes'] ?? null),
            ]);

            foreach ($jumlah as $lineId => $q) {
                PurchaseRequestOrderLine::create([
                    'purchase_request_order_id' => $catatan->id,
                    'purchase_request_line_id' => $lineId,
                    'qty_ordered' => $q,
                ]);

                PurchaseRequestLine::query()->whereKey($lineId)->increment('qty_ordered', $q);
            }

            if ($prq->status === PurchaseRequestStatus::Approved) {
                $prq->forceFill([
                    'status' => PurchaseRequestStatus::Forwarded,
                    'forwarded_by' => $actor?->id,
                    'forwarded_at' => now(),
                ])->save();
            }

            activity('purchase_request')->performedOn($prq)->causedBy($actor)
                ->withProperties(['vendor' => $vendor->name, 'baris' => count($jumlah), 'po' => $catatan->external_po_no])
                ->log('Catatan pemesanan ke '.$vendor->name.' dicatat');

            return $catatan->refresh();
        });
    }

    /** @param  array<string, mixed>  $header */
    private function vendor(array $header, ?User $actor): Vendor
    {
        $nama = trim((string) ($header['new_vendor_name'] ?? ''));

        if ($nama !== '') {
            // A-53: vendor sementara dengan field minimum (nama *, jenis *, kontak).
            $jenis = VendorType::tryFrom((string) ($header['new_vendor_type'] ?? ''))
                ?? throw PurchaseRequestRuleException::field('BR-GEN-11', 'new_vendor_type', 'Jenis vendor baru wajib dipilih.');

            $vendor = $this->vendors->provisional(mb_substr($nama, 0, 150), $jenis, $actor);

            if (($telepon = trim((string) ($header['new_vendor_phone'] ?? ''))) !== '') {
                $vendor->forceFill(['phone' => mb_substr($telepon, 0, 20)])->save();
            }

            return $vendor;
        }

        $vendor = Vendor::query()->find(is_numeric($header['vendor_id'] ?? null) ? (int) $header['vendor_id'] : 0);

        if ($vendor === null) {
            throw PurchaseRequestRuleException::field('BR-GEN-11', 'vendor_id', 'Vendor wajib dipilih, atau isi nama vendor baru.');
        }

        if (! $vendor->is_active || $vendor->status === VendorStatus::Inactive) {
            throw PurchaseRequestRuleException::field('BR-MST-05', 'vendor_id', 'Vendor '.$vendor->name.' nonaktif.');
        }

        return $vendor;
    }

    private function teks(mixed $nilai, int $maks = 255): ?string
    {
        $isi = is_scalar($nilai) ? trim((string) $nilai) : '';

        return $isi === '' ? null : mb_substr($isi, 0, $maks);
    }
}

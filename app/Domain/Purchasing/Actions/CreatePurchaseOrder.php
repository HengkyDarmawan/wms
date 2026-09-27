<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Models\Vendor;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Exceptions\PurchasingRuleException;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\PurchaseOrderLine;
use App\Domain\Purchasing\Support\Money;
use App\Domain\Purchasing\Support\PurchaseOrderLines;
use App\Domain\Stock\Support\DocumentNumber;
use App\Domain\Warehouse\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `po.create` — PO draf dari baris PRQ (Katalog §2.17 `→ draft`,
 * A-210): satu vendor aktif × satu gudang tujuan dalam cakupan, jumlah dan
 * harga satuan per baris (A-211), tanda harga termasuk PPN (A-265, bawaan
 * ya). `update()` mengganti isi draf.
 */
class CreatePurchaseOrder
{
    public function __construct(
        private readonly PurchaseOrderLines $lines,
        private readonly DocumentNumber $nomor,
    ) {}

    /**
     * @param  array{vendor_id?: mixed, warehouse_id?: mixed, eta_date?: mixed, notes?: mixed, vendor_choice_note?: mixed, price_includes_tax?: mixed}  $header
     * @param  array<int, array<string, mixed>>  $lines  purchase_request_line_id, qty_base, unit_price, notes
     */
    public function handle(array $header, array $lines, ?User $actor = null): PurchaseOrder
    {
        [$vendor, $gudang, $eta] = $this->header($header, $actor);
        $isi = $this->lines->normalize((int) $gudang->id, $lines);

        return DB::transaction(function () use ($vendor, $gudang, $eta, $header, $isi, $actor) {
            $po = PurchaseOrder::create([
                'number' => $this->nomor->next('PO', (string) $gudang->code),
                'vendor_id' => $vendor->id,
                'warehouse_id' => $gudang->id,
                'status' => PurchaseOrderStatus::Draft,
                'order_date' => now()->toDateString(),
                'eta_date' => $eta,
                'currency' => Money::CURRENCY,
                'price_includes_tax' => $this->termasukPajak($header),
                'total_amount' => $isi['total'],
                'payment_terms' => $vendor->payment_terms,
                'notes' => $this->teks($header['notes'] ?? null),
                'vendor_choice_note' => $this->teks($header['vendor_choice_note'] ?? null),
                'created_by' => $actor?->id,
            ]);

            $this->simpanBaris($po, $isi['lines']);

            activity('purchase_order')->performedOn($po)->causedBy($actor)
                ->withProperties(['vendor' => $vendor->name, 'baris' => count($isi['lines']), 'nilai' => $isi['total']])
                ->log('PO draf dibuat');

            return $po->refresh();
        });
    }

    /**
     * @param  array{vendor_id?: mixed, warehouse_id?: mixed, eta_date?: mixed, notes?: mixed, vendor_choice_note?: mixed, price_includes_tax?: mixed}  $header
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function update(PurchaseOrder $po, array $header, array $lines, ?User $actor = null): PurchaseOrder
    {
        if ($po->status !== PurchaseOrderStatus::Draft) {
            throw PurchasingRuleException::rule('BR-GEN-01', 'Hanya PO draf yang bisa diubah.');
        }

        [$vendor, $gudang, $eta] = $this->header($header, $actor);
        $isi = $this->lines->normalize((int) $gudang->id, $lines, (int) $po->id);

        return DB::transaction(function () use ($po, $vendor, $gudang, $eta, $header, $isi, $actor) {
            // Baris draf belum menjadi catatan pemesanan, jadi aman diganti utuh.
            $po->lines()->delete();

            $po->forceFill([
                'vendor_id' => $vendor->id,
                'warehouse_id' => $gudang->id,
                'eta_date' => $eta,
                'price_includes_tax' => $this->termasukPajak($header),
                'total_amount' => $isi['total'],
                'payment_terms' => $vendor->payment_terms,
                'notes' => $this->teks($header['notes'] ?? null),
                'vendor_choice_note' => $this->teks($header['vendor_choice_note'] ?? null),
            ])->save();

            $this->simpanBaris($po, $isi['lines']);

            activity('purchase_order')->performedOn($po)->causedBy($actor)
                ->withProperties(['baris' => count($isi['lines']), 'nilai' => $isi['total']])
                ->log('PO draf diubah');

            return $po->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $header
     * @return array{0: Vendor, 1: Warehouse, 2: ?string}
     */
    private function header(array $header, ?User $actor): array
    {
        $vendor = Vendor::query()->find(is_numeric($header['vendor_id'] ?? null) ? (int) $header['vendor_id'] : 0);

        if ($vendor === null) {
            throw PurchasingRuleException::field('BR-GEN-11', 'vendor_id', 'Vendor wajib dipilih.');
        }

        if (! $vendor->is_active || $vendor->status !== VendorStatus::Active) {
            throw PurchasingRuleException::field('BR-MST-05', 'vendor_id', 'Vendor '.$vendor->name.' belum aktif; lengkapi data vendor sementara dulu.'); // A-53
        }

        $gudang = Warehouse::query()->find(is_numeric($header['warehouse_id'] ?? null) ? (int) $header['warehouse_id'] : 0);

        if ($gudang === null || ($actor !== null && ! $actor->canAccessWarehouse((int) $gudang->id))) {
            throw PurchasingRuleException::field('BR-GEN-09', 'warehouse_id', 'Gudang tujuan wajib dipilih dari cakupan Anda.');
        }

        $eta = null;

        if (($isi = trim((string) ($header['eta_date'] ?? ''))) !== '') {
            try {
                $eta = Carbon::parse($isi)->toDateString();
            } catch (\Throwable) {
                throw PurchasingRuleException::field('BR-GEN-11', 'eta_date', 'Perkiraan datang tidak valid.');
            }
        }

        return [$vendor, $gudang, $eta];
    }

    /** @param  array<int, array<string, mixed>>  $lines */
    private function simpanBaris(PurchaseOrder $po, array $lines): void
    {
        foreach ($lines as $l) {
            PurchaseOrderLine::create($l + ['purchase_order_id' => $po->id]);
        }
    }

    /** Tanpa isian = termasuk PPN (A-265). */
    private function termasukPajak(array $header): bool
    {
        if (! array_key_exists('price_includes_tax', $header) || $header['price_includes_tax'] === null || $header['price_includes_tax'] === '') {
            return true;
        }

        return filter_var($header['price_includes_tax'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }

    private function teks(mixed $nilai): ?string
    {
        $isi = is_scalar($nilai) ? trim((string) $nilai) : '';

        return $isi === '' ? null : mb_substr($isi, 0, 255);
    }
}

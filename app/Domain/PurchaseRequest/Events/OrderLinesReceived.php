<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Events;

use App\Domain\Access\Models\User;

/**
 * GRN vendor `received` mencatat barang di baris catatan pemesanan PRQ
 * (A-174). Modul Purchasing mendengarkan kejadian ini untuk jumlah diterima
 * PO (A-214) tanpa WMS perlu mengenal PO. Tanpa harga (D-07).
 */
final class OrderLinesReceived
{
    /** @param  array<int, float>  $qtyPerOrderLine  purchase_request_order_line_id => jumlah diterima */
    public function __construct(
        public readonly string $receiptNumber,
        public readonly array $qtyPerOrderLine,
        public readonly ?User $actor = null,
    ) {}
}

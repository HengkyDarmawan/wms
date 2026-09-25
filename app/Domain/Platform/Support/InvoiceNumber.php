<?php

declare(strict_types=1);

namespace App\Domain\Platform\Support;

use App\Domain\Platform\Models\SubscriptionInvoice;

/**
 * Nomor tagihan langganan `INV/<yymm>/<urut 4 digit>` di database pusat,
 * urut direset tiap bulan (pola BR-GEN-06, A-177).
 */
class InvoiceNumber
{
    public function next(): string
    {
        $awalan = 'INV/'.now()->format('ym').'/';

        $terakhir = SubscriptionInvoice::query()->where('number', 'like', $awalan.'%')
            ->lockForUpdate()->orderByDesc('number')->value('number');

        $urut = $terakhir === null ? 1 : ((int) substr((string) $terakhir, strlen($awalan))) + 1;

        return $awalan.str_pad((string) $urut, 4, '0', STR_PAD_LEFT);
    }
}

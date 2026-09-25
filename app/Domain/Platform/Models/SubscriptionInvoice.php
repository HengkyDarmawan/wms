<?php

declare(strict_types=1);

namespace App\Domain\Platform\Models;

use App\Domain\Platform\Enums\InvoiceStatus;
use App\Domain\Platform\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tagihan langganan bulanan (Blueprint §14, alur 10 langkah 6). Nominal adalah
 * harga paket platform di database pusat, bukan nilai barang WMS (D-07).
 *
 * @property InvoiceStatus $status
 */
class SubscriptionInvoice extends Model
{
    protected $connection = 'central';

    protected $table = 'subscription_invoices';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class, 'invoice_id');
    }

    public function pendingPayment(): ?SubscriptionPayment
    {
        return $this->payments()->where('status', PaymentStatus::Pending->value)->latest('id')->first();
    }
}

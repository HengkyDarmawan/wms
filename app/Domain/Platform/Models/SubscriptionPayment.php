<?php

declare(strict_types=1);

namespace App\Domain\Platform\Models;

use App\Domain\Platform\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bukti bayar langganan yang diunggah Admin Company dan diverifikasi Super
 * Admin (Blueprint §14, alur 10 langkah 7–8). Berkas bukti disimpan di disk
 * company pengunggah; `uploaded_by` adalah id user di database tenant.
 *
 * @property PaymentStatus $status
 */
class SubscriptionPayment extends Model
{
    protected $connection = 'central';

    protected $table = 'subscription_payments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'paid_at' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionInvoice::class, 'invoice_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'verified_by');
    }
}

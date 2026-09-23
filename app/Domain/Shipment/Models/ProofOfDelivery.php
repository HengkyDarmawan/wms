<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Models;

use App\Domain\Access\Models\User;
use App\Domain\Shipment\Enums\ProofChannel;
use App\Domain\Shipment\Enums\ReceiptConfirmation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bukti terima — satu per SJ (BR-SJ-05).
 *
 * Diisi driver lewat aplikasi, atau penerima tanpa akun lewat tautan bertoken.
 * Setelah itu pemohon masih punya tenggat untuk mengajukan keberatan
 * (BR-REQ-10); diam sampai tenggat dianggap menerima.
 *
 * @property ProofChannel $channel
 * @property ?ReceiptConfirmation $confirmation
 */
class ProofOfDelivery extends Model
{
    use HasFactory;

    protected $table = 'proofs_of_delivery';

    protected $guarded = [];

    protected $attributes = [
        'channel' => 'driver_pwa',
    ];

    protected function casts(): array
    {
        return [
            'channel' => ProofChannel::class,
            'confirmation' => ReceiptConfirmation::class,
            'confirmed_at' => 'datetime',
            'requester_confirmed_at' => 'datetime',
            'confirm_deadline_at' => 'datetime',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProofOfDeliveryLine::class);
    }

    /** BR-REQ-10: bukti terima yang tenggat keberatannya sudah lewat. */
    public function scopeAwaitingConfirmation(Builder $query): Builder
    {
        return $query->whereNull('confirmation')->whereNotNull('confirm_deadline_at');
    }

    public function scopeConfirmationOverdue(Builder $query): Builder
    {
        return $query->awaitingConfirmation()->where('confirm_deadline_at', '<=', now());
    }

    public function isPending(): bool
    {
        return $this->confirmation === null;
    }

    /** Ada yang tidak sampai utuh, apa pun sebabnya. */
    public function hasDiscrepancy(): bool
    {
        return $this->lines()
            ->where(fn (Builder $q) => $q->where('qty_damaged', '>', 0)->orWhere('qty_missing', '>', 0))
            ->exists();
    }
}

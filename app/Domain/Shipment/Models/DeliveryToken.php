<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tautan bukti terima bertoken (A-41, BR-SJ-05).
 *
 * Sekali pakai, berlaku 24 jam, dilindungi OTP. Penerima di lapangan sering
 * tidak punya akun; ini jalan supaya ia tetap bisa menandatangani tanpa kita
 * membuatkan akun untuk orang yang mungkin hanya ditemui sekali.
 *
 * OTP disimpan sebagai hash, tidak pernah sebagai teks.
 */
class DeliveryToken extends Model
{
    use HasFactory;

    protected $table = 'delivery_tokens';

    protected $guarded = [];

    protected $hidden = ['otp_hash'];

    protected $attributes = [
        'attempts' => 0,
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('used_at')->where('expires_at', '>', now());
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at?->isFuture() === true;
    }

    /** NFR-04: percobaan OTP dibatasi supaya token tidak bisa ditebak. */
    public function isLockedOut(int $max = 5): bool
    {
        return $this->attempts >= $max;
    }
}

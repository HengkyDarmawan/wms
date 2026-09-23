<?php

declare(strict_types=1);

namespace App\Domain\Access\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

/**
 * Undangan user (Blueprint §13). Token mentah hanya dikirim lewat email;
 * yang disimpan adalah hash-nya. Masa berlaku bawaan 72 jam (10-access §4).
 */
class UserInvitation extends Model
{
    use HasFactory;

    protected $table = 'user_invitations';

    protected $guarded = [];

    /**
     * Token mentah hasil pembuatan undangan. Properti PHP biasa (bukan atribut
     * Eloquent) supaya tidak ikut tersimpan ke tabel.
     */
    public ?string $plainToken = null;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'sent_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && ! $this->isExpired();
    }

    /** Tidak dipakai untuk token (token memakai hash SHA-256), disediakan untuk uji. */
    public function matches(string $plainToken): bool
    {
        return hash_equals($this->token, self::hashToken($plainToken))
            || Hash::check($plainToken, $this->token);
    }
}

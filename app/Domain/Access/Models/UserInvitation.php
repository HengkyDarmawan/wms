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

    /** Jangan pernah ikut terserialisasi (log, JSON, payload Livewire). */
    protected $hidden = ['token', 'token_plain'];

    /**
     * Token mentah hasil pembuatan undangan. Properti PHP biasa (bukan atribut
     * Eloquent) supaya tidak ikut tersimpan ke tabel.
     */
    public ?string $plainToken = null;

    protected function casts(): array
    {
        return [
            'token_plain' => 'encrypted',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'sent_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Tautan undangan lengkap di subdomain company (A-333). `Company::url()`
     * ikut membawa skema dan port, yang penting di lingkungan lokal `:8000`.
     */
    public function url(): ?string
    {
        $token = $this->plainToken ?? $this->token_plain;

        if (! is_string($token) || $token === '') {
            return null;
        }

        return tenant()?->url('/invitation/'.$token) ?? route('invitation.show', ['token' => $token]);
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

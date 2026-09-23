<?php

declare(strict_types=1);

namespace App\Domain\Access\Models;

use App\Domain\Access\Enums\LoginResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan percobaan login (NFR-03, laporan "Log login 30 hari").
 */
class LoginAttempt extends Model
{
    public $timestamps = false;

    protected $table = 'login_attempts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'result' => LoginResult::class,
            'attempted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(
        string $email,
        LoginResult $result,
        ?int $userId = null,
        ?string $ip = null,
        string $channel = 'web',
    ): self {
        return static::create([
            'email' => $email,
            'user_id' => $userId,
            'ip_address' => $ip,
            'channel' => $channel,
            'result' => $result,
            'attempted_at' => now(),
        ]);
    }
}

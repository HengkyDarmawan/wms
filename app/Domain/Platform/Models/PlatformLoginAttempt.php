<?php

declare(strict_types=1);

namespace App\Domain\Platform\Models;

use App\Domain\Access\Enums\LoginResult;
use Illuminate\Database\Eloquent\Model;

/** Catatan percobaan masuk Super Admin (NFR-03, NFR-04, A-182). */
class PlatformLoginAttempt extends Model
{
    public $timestamps = false;

    protected $connection = 'central';

    protected $table = 'platform_login_attempts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'result' => LoginResult::class,
            'attempted_at' => 'datetime',
        ];
    }

    public static function record(string $email, LoginResult $result, ?int $userId, ?string $ip): self
    {
        return self::create([
            'email' => mb_substr(mb_strtolower($email), 0, 150),
            'platform_user_id' => $userId,
            'result' => $result,
            'ip_address' => $ip,
            'attempted_at' => now(),
        ]);
    }
}

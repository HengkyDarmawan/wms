<?php

declare(strict_types=1);

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Super Admin platform (Blueprint §4.1). Tidak punya akses ke data operasional
 * company kecuali lewat akses dukungan berperiode (A-27, BR-SUB-04).
 */
class PlatformUser extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $connection = 'central';

    protected $table = 'platform_users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    /** Akses dukungan yang sedang berlaku untuk satu company. */
    public function hasActiveSupportAccess(int $companyId): bool
    {
        return SupportAccess::query()
            ->where('company_id', $companyId)
            ->where('platform_user_id', $this->id)
            ->active()
            ->exists();
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Notification\Models;

use Illuminate\Database\Eloquent\Model;

/** Preferensi kanal per user per kejadian (ERD 08c, Blueprint §10). */
class NotificationPreference extends Model
{
    public $incrementing = false;

    protected $table = 'notification_preferences';

    protected $primaryKey = 'user_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['in_app' => 'boolean', 'email' => 'boolean', 'whatsapp' => 'boolean'];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Master\Models;

use App\Domain\Master\Enums\HolidayKind;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Hari libur company (A-270): libur nasional & cuti bersama dari SKB, atau
 * libur company. Tidak dihapus — dinonaktifkan bila company tetap bekerja.
 *
 * @property HolidayKind $kind
 */
class Holiday extends Model
{
    use LogsActivity;

    protected $table = 'holidays';

    protected $guarded = [];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'kind' => HolidayKind::class,
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('master')->logOnly(['date', 'name', 'kind', 'is_active'])->logOnlyDirty();
    }
}

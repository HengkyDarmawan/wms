<?php

declare(strict_types=1);

namespace App\Domain\Access\Models;

use App\Domain\Access\Actions\SavePosition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Jabatan dalam unit organisasi; `level` dipakai aturan approval "jabatan X di unit Y"
 * (Blueprint §8.1).
 *
 * Peta jabatan (A-344): `reports_to_position_id` menunjuk jabatan atasan, dan
 * `level` dihitung dari peta itu (tanpa atasan = 1) oleh {@see SavePosition}.
 * Pemegang jabatan atasan menjadi atasan langsung bawaan pemegang jabatan ini.
 */
class Position extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'positions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function reportsTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reports_to_position_id');
    }

    public function subordinatePositions(): HasMany
    {
        return $this->hasMany(self::class, 'reports_to_position_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('access')->logOnly(['code', 'name', 'org_unit_id', 'reports_to_position_id', 'level', 'is_active'])->logOnlyDirty();
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Master\Models;

use App\Domain\Access\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Kendaraan milik sendiri untuk pengiriman kurir internal (A-57).
 *
 * A-311: driver bawaan disimpan sebagai nama + HP (driver tidak punya akun);
 * `default_driver_id` hanya dibaca untuk data sebelum A-311.
 */
class Vehicle extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'vehicles';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Data lama (sebelum A-311). */
    public function defaultDriver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_driver_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * A-315: isian form SJ/SJ jemput diisi driver bawaan kendaraan terpilih
     * bila nama driver masih kosong — driver pengganti tetap bisa diketik.
     *
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    public static function fillDriver(array $form, string|int|null $vehicleId): array
    {
        $kendaraan = $vehicleId === null || $vehicleId === '' ? null : self::query()->find((int) $vehicleId);

        if ($kendaraan !== null && trim((string) ($form['driver_name'] ?? '')) === '' && $kendaraan->default_driver_name) {
            $form['driver_name'] = $kendaraan->default_driver_name;
            $form['driver_phone'] = (string) $kendaraan->default_driver_phone;
        }

        return $form;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('master')
            ->logOnly(['plate_no', 'type', 'default_driver_name', 'default_driver_phone', 'is_active'])
            ->logOnlyDirty();
    }
}

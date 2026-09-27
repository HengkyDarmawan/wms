<?php

declare(strict_types=1);

namespace App\Domain\Master\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Saklar fitur per tenant (lot, serial, piece, expiry, fefo, rfid, qc) — lapis 1
 * dari P-08. Mematikan saklar menyembunyikan layar dan kolomnya, tidak menghapus
 * data yang sudah ada (P-03).
 *
 * Tidak memakai `LogsActivity`: kunci primernya teks, sedangkan
 * `audit_logs.subject_id` bilangan. Perubahan dicatat manual lewat properti
 * di {@see self::toggle()} (BR-GEN-05).
 */
class FeatureSetting extends Model
{
    protected $table = 'feature_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'config' => 'array',
        ];
    }

    public static function enabled(string $key): bool
    {
        return (bool) Cache::remember(
            'feature_setting:'.$key,
            now()->addMinutes(10),
            fn () => (bool) static::query()->find($key)?->enabled,
        );
    }

    /**
     * Menulis saklar dari seeder tanpa log aktivitas. Tanpa `$overwrite`,
     * saklar yang sudah ada tidak disentuh sehingga seed ulang tidak
     * mengembalikan pilihan company ke bawaan (A-284).
     *
     * @param  array<string, bool>  $map
     */
    public static function seed(array $map, bool $overwrite = false): void
    {
        foreach ($map as $key => $enabled) {
            $overwrite
                ? static::query()->updateOrCreate(['key' => $key], ['enabled' => $enabled])
                : static::query()->firstOrCreate(['key' => $key], ['enabled' => $enabled]);

            Cache::forget('feature_setting:'.$key);
        }
    }

    public static function toggle(string $key, bool $enabled, ?array $config = null): void
    {
        $sebelum = static::query()->find($key)?->enabled;

        static::query()->updateOrCreate(
            ['key' => $key],
            array_filter(['enabled' => $enabled, 'config' => $config], fn ($v) => $v !== null),
        );

        Cache::forget('feature_setting:'.$key);

        activity('master')
            ->withProperties(['key' => $key, 'dari' => $sebelum, 'ke' => $enabled])
            ->log('Saklar fitur diubah: '.$key);
    }
}

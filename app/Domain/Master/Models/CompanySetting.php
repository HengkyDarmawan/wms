<?php

declare(strict_types=1);

namespace App\Domain\Master\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan company per tenant (kunci => nilai JSON). Lapis 2 dari P-08:
 * fitur dinyalakan platform lewat {@see FeatureSetting}, perilaku diatur di sini.
 *
 * Tidak memakai `LogsActivity`: kunci primernya teks, sedangkan
 * `audit_logs.subject_id` bilangan. Perubahan dicatat manual lewat properti
 * di {@see self::put()} (BR-GEN-05).
 */
class CompanySetting extends Model
{
    protected $table = 'company_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $baris = Cache::remember(
            'company_setting:'.$key,
            now()->addMinutes(10),
            fn () => static::query()->find($key)?->value,
        );

        return $baris ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $sebelum = static::query()->find($key)?->value;

        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget('company_setting:'.$key);

        activity('master')
            ->withProperties(['key' => $key, 'dari' => $sebelum, 'ke' => $value])
            ->log('Pengaturan company diubah: '.$key);
    }
}

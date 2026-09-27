<?php

declare(strict_types=1);

namespace App\Domain\Shared\Support;

/**
 * Teks riwayat aktivitas untuk layar: deskripsi bawaan spatie/activitylog
 * (`created`, `updated`, `deleted`, `restored`) ditampilkan dalam Bahasa
 * Indonesia. Nilai di basis data tidak diubah; deskripsi buatan aplikasi
 * yang sudah berbahasa Indonesia dikembalikan apa adanya.
 */
class ActivityText
{
    public const BAWAAN = [
        'created' => 'dibuat',
        'updated' => 'diubah',
        'deleted' => 'dihapus',
        'restored' => 'dipulihkan',
    ];

    public static function label(?string $description): string
    {
        $teks = (string) $description;

        return isset(self::BAWAAN[$teks]) ? __(self::BAWAAN[$teks]) : $teks;
    }
}

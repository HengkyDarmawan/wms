<?php

declare(strict_types=1);

namespace App\Domain\Shared\Messaging;

/**
 * Nomor HP Indonesia dibakukan ke bentuk internasional tanpa `+`
 * (`0812…`, `+62 812…`, `62-812…` → `62812…`), bentuk yang diminta gateway
 * WhatsApp/SMS. Nomor yang jelas bukan HP ditolak (null).
 */
final class PhoneNumber
{
    public static function normalize(?string $nomor): ?string
    {
        $angka = preg_replace('/\D+/', '', (string) $nomor) ?? '';

        if (str_starts_with($angka, '0')) {
            $angka = '62'.substr($angka, 1);
        } elseif (str_starts_with($angka, '8')) {
            $angka = '62'.$angka;
        }

        // 62 + 8xx + 7–10 digit (HP Indonesia 10–13 digit tanpa kode negara).
        return preg_match('/^628\d{7,11}$/', $angka) === 1 ? $angka : null;
    }

    /** Untuk log & layar: `62812****7890`. */
    public static function mask(string $nomor): string
    {
        $panjang = strlen($nomor);

        return $panjang <= 8 ? $nomor : substr($nomor, 0, 5).str_repeat('*', $panjang - 9).substr($nomor, -4);
    }
}

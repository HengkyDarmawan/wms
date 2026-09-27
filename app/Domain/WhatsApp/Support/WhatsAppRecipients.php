<?php

declare(strict_types=1);

namespace App\Domain\WhatsApp\Support;

use App\Domain\Access\Models\User;
use App\Domain\Shared\Messaging\PhoneNumber;

/**
 * BR-WA-01: pesan WhatsApp hanya ke nomor **terverifikasi** milik user aktif,
 * dan nomor pengirim tombol harus sama dengan nomor itu (A-275).
 */
final class WhatsAppRecipients
{
    public static function number(User $user): ?string
    {
        if (! $user->is_active || $user->phone_verified_at === null) {
            return null;
        }

        return PhoneNumber::normalize($user->phone);
    }

    public static function matches(User $user, string $from): bool
    {
        $nomor = self::number($user);

        return $nomor !== null && $nomor === PhoneNumber::normalize($from);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Access\Enums;

/**
 * Hasil percobaan login untuk laporan "Log login 30 hari" (10-access §9, NFR-03).
 */
enum LoginResult: string
{
    case Success = 'success';
    case Invalid = 'invalid';
    case Locked = 'locked';
    case Inactive = 'inactive';
    case NoRole = 'no_role';
    case WrongPortal = 'wrong_portal';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Berhasil',
            self::Invalid => 'Email atau password salah',
            self::Locked => 'Akun terkunci',
            self::Inactive => 'Akun nonaktif',
            self::NoRole => 'Tanpa penugasan role',
            self::WrongPortal => 'Salah pintu masuk',
            self::Suspended => 'Langganan diakhiri',
        };
    }
}

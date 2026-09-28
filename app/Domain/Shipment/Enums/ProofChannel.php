<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Enums;

/**
 * A-41, A-312, A-316 — lewat jalur mana bukti terima diisi.
 *
 * `driver_pwa` hanya untuk data lama: sejak A-311 driver tidak punya akun.
 */
enum ProofChannel: string
{
    case ClientPortal = 'client_portal';
    case RecipientAccount = 'recipient_account';
    case SignedDocument = 'signed_document';
    case TokenLink = 'token_link';
    case DriverPwa = 'driver_pwa';

    public function label(): string
    {
        return match ($this) {
            self::ClientPortal => 'Portal klien',
            self::RecipientAccount => 'Akun penerima',
            self::SignedDocument => 'SJ bertanda tangan (diisi gudang asal)',
            self::TokenLink => 'Tautan bertoken',
            self::DriverPwa => 'Aplikasi driver (lama)',
        };
    }

    /** A-316: foto SJ bertanda tangan & cap wajib — penerimanya tidak memakai akun internal. */
    public function requiresSignedDocument(): bool
    {
        return $this === self::ClientPortal || $this === self::SignedDocument;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $hasil = [];

        foreach (self::cases() as $case) {
            $hasil[$case->value] = $case->label();
        }

        return $hasil;
    }
}

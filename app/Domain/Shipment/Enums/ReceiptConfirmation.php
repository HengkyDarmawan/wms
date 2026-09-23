<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Enums;

/** A-63 dan BR-REQ-10 — tanggapan pemohon atas bukti terima. */
enum ReceiptConfirmation: string
{
    case Confirmed = 'confirmed';
    case Disputed = 'disputed';
    case AutoConfirmed = 'auto_confirmed';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Dikonfirmasi',
            self::Disputed => 'Diajukan keberatan',
            self::AutoConfirmed => 'Dikonfirmasi otomatis',
        };
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

    /** Diam sampai tenggat dianggap menerima, sama seperti penggantian item. */
    public function isAcceptance(): bool
    {
        return $this !== self::Disputed;
    }
}

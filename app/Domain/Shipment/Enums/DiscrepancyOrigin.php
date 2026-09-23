<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Enums;

/**
 * A-63 — dari mana selisih ini datang.
 *
 * Dua pintu, satu dokumen: driver mencatatnya saat bukti terima, atau klien
 * mengajukannya belakangan dalam batas konfirmasi.
 */
enum DiscrepancyOrigin: string
{
    case PartialDelivery = 'partial_delivery';
    case ClientDispute = 'client_dispute';

    public function label(): string
    {
        return $this === self::PartialDelivery ? 'Bukti terima sebagian' : 'Keberatan klien';
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::PartialDelivery->value => self::PartialDelivery->label(),
            self::ClientDispute->value => self::ClientDispute->label(),
        ];
    }
}

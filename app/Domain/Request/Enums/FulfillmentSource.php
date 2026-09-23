<?php

declare(strict_types=1);

namespace App\Domain\Request\Enums;

/** BR-REQ-05: dari mana baris ini akan dipenuhi. Baris tanpa sumber menahan approval. */
enum FulfillmentSource: string
{
    case Stock = 'stock';
    case Transfer = 'transfer';
    case Purchase = 'purchase';

    public function label(): string
    {
        return match ($this) {
            self::Stock => 'Stok tersedia',
            self::Transfer => 'Transfer antar gudang',
            self::Purchase => 'Pembelian',
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

    /** Hanya sumber `stock` yang langsung menjadi reservasi lunak saat approval. */
    public function reservesOnApproval(): bool
    {
        return $this === self::Stock;
    }
}

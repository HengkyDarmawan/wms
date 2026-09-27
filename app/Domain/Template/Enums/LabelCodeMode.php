<?php

declare(strict_types=1);

namespace App\Domain\Template\Enums;

/** Katalog §3 `label_code_mode` — kode yang dicetak pada label (A-262). */
enum LabelCodeMode: string
{
    case Barcode = 'barcode';
    case Qr = 'qr';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Barcode => __('Barcode saja'),
            self::Qr => __('QR saja'),
            self::Both => __('Barcode + QR'),
        };
    }

    public function hasBarcode(): bool
    {
        return $this !== self::Qr;
    }

    public function hasQr(): bool
    {
        return $this !== self::Barcode;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(
            array_map(fn (self $m) => $m->value, self::cases()),
            array_map(fn (self $m) => $m->label(), self::cases()),
        );
    }
}

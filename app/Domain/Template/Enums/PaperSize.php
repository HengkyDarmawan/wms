<?php

declare(strict_types=1);

namespace App\Domain\Template\Enums;

/** Katalog §3 `paper_size` (A-120). */
enum PaperSize: string
{
    case A4 = 'a4';
    case A4Landscape = 'a4_landscape';
    case Label50x30 = 'label_50x30';
    case LabelA4Grid = 'label_a4_3x8';

    public function label(): string
    {
        return match ($this) {
            self::A4 => __('A4 tegak'),
            self::A4Landscape => __('A4 lanskap'),
            self::Label50x30 => __('Label thermal 50×30 mm'),
            self::LabelA4Grid => __('Lembar label A4 3×8'),
        };
    }

    public function isLabel(): bool
    {
        return $this === self::Label50x30 || $this === self::LabelA4Grid;
    }

    /**
     * Ukuran kertas untuk dompdf: nama baku atau [x0, y0, lebar, tinggi] dalam poin.
     *
     * @return array{0: string|array<int, float>, 1: string}
     */
    public function dompdf(): array
    {
        return match ($this) {
            self::A4, self::LabelA4Grid => ['a4', 'portrait'],
            self::A4Landscape => ['a4', 'landscape'],
            // 50×30 mm; 1 mm = 2,8346 pt.
            self::Label50x30 => [[0, 0, 141.73, 85.04], 'portrait'],
        };
    }

    /** @return array<int, self> */
    public static function forDocuments(): array
    {
        return [self::A4, self::A4Landscape];
    }

    /** @return array<int, self> */
    public static function forLabels(): array
    {
        return [self::Label50x30, self::LabelA4Grid];
    }
}

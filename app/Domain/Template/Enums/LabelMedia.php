<?php

declare(strict_types=1);

namespace App\Domain\Template\Enums;

/** Katalog §3 `label_media` — bentuk kertas label (A-261). */
enum LabelMedia: string
{
    case Roll = 'roll';
    case Sheet = 'sheet';

    public function label(): string
    {
        return match ($this) {
            self::Roll => __('Gulungan (thermal)'),
            self::Sheet => __('Lembar (A4/Letter, beberapa label)'),
        };
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

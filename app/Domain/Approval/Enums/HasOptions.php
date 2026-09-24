<?php

declare(strict_types=1);

namespace App\Domain\Approval\Enums;

/** Pilihan select `nilai => label` untuk enum bermetode `label()`. */
trait HasOptions
{
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

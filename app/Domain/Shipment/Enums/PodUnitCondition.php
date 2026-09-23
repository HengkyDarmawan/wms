<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Enums;

/** A-64 — kondisi per unit untuk item berserial dan per potong. */
enum PodUnitCondition: string
{
    case Good = 'good';
    case Damaged = 'damaged';
    case Missing = 'missing';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Baik',
            self::Damaged => 'Rusak',
            self::Missing => 'Kurang',
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

    /** Selisih yang lahir dari kondisi ini; `good` tidak melahirkan apa pun. */
    public function discrepancyType(): ?DiscrepancyType
    {
        return match ($this) {
            self::Good => null,
            self::Damaged => DiscrepancyType::Damaged,
            self::Missing => DiscrepancyType::Missing,
        };
    }
}

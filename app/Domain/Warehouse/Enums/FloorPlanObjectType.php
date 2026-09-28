<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Enums;

/**
 * Jenis objek denah gedung (A-320): benda nyata tanpa stok. Bukan status.
 *
 * `solid` = benda yang tidak boleh ditimpa rak (dicek tumpukan, A-321);
 * jalur forklift & area bebas hanya penanda lantai.
 */
enum FloorPlanObjectType: string
{
    case Door = 'door';
    case Dock = 'dock';
    case ForkliftLane = 'forklift_lane';
    case Pillar = 'pillar';
    case Office = 'office';
    case OpenArea = 'open_area';

    public function label(): string
    {
        return match ($this) {
            self::Door => 'Pintu',
            self::Dock => 'Dock / loading',
            self::ForkliftLane => 'Jalur forklift',
            self::Pillar => 'Pilar',
            self::Office => 'Kantor',
            self::OpenArea => 'Area bebas',
        };
    }

    public function solid(): bool
    {
        return ! in_array($this, [self::ForkliftLane, self::OpenArea], true);
    }

    /** Ukuran bawaan saat ditambahkan dari denah (panjang, lebar) dalam meter. */
    public function defaultSize(): array
    {
        return match ($this) {
            self::Door => [3.0, 0.5],
            self::Dock => [4.0, 4.0],
            self::ForkliftLane => [10.0, 2.0],
            self::Pillar => [0.5, 0.5],
            self::Office => [5.0, 4.0],
            self::OpenArea => [6.0, 4.0],
        };
    }

    /** @return array{0: string, 1: string} isi & garis SVG */
    public function colors(): array
    {
        return match ($this) {
            self::Door => ['#ffe066', '#e8590c'],
            self::Dock => ['#fff3bf', '#f08c00'],
            self::ForkliftLane => ['#f8f9fa', '#868e96'],
            self::Pillar => ['#495057', '#212529'],
            self::Office => ['#e7f5ff', '#1c7ed6'],
            self::OpenArea => ['#f4fce3', '#74b816'],
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
}

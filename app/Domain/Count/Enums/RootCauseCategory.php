<?php

declare(strict_types=1);

namespace App\Domain\Count\Enums;

/** Katalog Status §3 `root_cause_category` — wajib untuk selisih besar (BR-OPN-07). */
enum RootCauseCategory: string
{
    case Mispick = 'mispick';
    case Misplaced = 'misplaced';
    case WrongUom = 'wrong_uom';
    case DamagedLost = 'damaged_lost';
    case UnrecordedTxn = 'unrecorded_txn';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Mispick => 'Salah ambil',
            self::Misplaced => 'Salah taruh',
            self::WrongUom => 'Salah satuan',
            self::DamagedLost => 'Rusak/hilang',
            self::UnrecordedTxn => 'Transaksi tidak tercatat',
            self::Other => 'Lainnya',
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

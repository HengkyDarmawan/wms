<?php

declare(strict_types=1);

namespace App\Domain\Master\Support;

use App\Domain\Master\Models\Uom;
use App\Domain\Master\Models\UomCategory;
use Database\Seeders\Tenant\MasterReferenceSeeder;

/**
 * Satuan bawaan setiap company (A-411): satu sumber untuk provisioning
 * ({@see MasterReferenceSeeder}) dan migrasi yang
 * melengkapi company lama. Faktor = berapa satuan acuan kategori yang setara
 * 1 satuan ini (1 KM = 1.000 M). **Kemasan** (`is_packaging`, A-294) berfaktor 1
 * karena isinya ditentukan per barang (kemasan item).
 */
final class DefaultUoms
{
    /**
     * Kategori => nama, satuan acuan, daftar [kode, nama, faktor, kemasan?].
     *
     * @var array<string, array{name: string, reference: string, uoms: array<int, array{0: string, 1: string, 2: float, 3?: bool}>}>
     */
    public const DAFTAR = [
        'COUNT' => [
            'name' => 'Hitung',
            'reference' => 'PCS',
            'uoms' => [
                ['PCS', 'Pieces', 1],
                ['UNIT', 'Unit', 1],
                ['BH', 'Buah', 1],
                ['LBR', 'Lembar', 1],
                ['BATANG', 'Batang', 1],
                ['LSN', 'Lusin', 12],
                ['KODI', 'Kodi', 20],
                ['GROSS', 'Gross', 144],
                ['RIM', 'Rim', 500],
                ['BOX', 'Box', 1, true],
                ['DUS', 'Dus', 1, true],
                ['KARTON', 'Karton', 1, true],
                ['PACK', 'Pack', 1, true],
                ['SET', 'Set', 1, true],
                ['ROLL', 'Roll', 1, true],
                ['IKAT', 'Ikat', 1, true],
                ['KALENG', 'Kaleng', 1, true],
                ['PAIL', 'Pail', 1, true],
            ],
        ],
        'LENGTH' => [
            'name' => 'Panjang',
            'reference' => 'M',
            'uoms' => [
                ['KM', 'Kilometer', 1000],
                ['M', 'Meter', 1],
                ['YARD', 'Yard', 0.9144],
                ['FT', 'Kaki', 0.3048],
                ['INCH', 'Inci', 0.0254],
                ['CM', 'Sentimeter', 0.01],
                ['MM', 'Milimeter', 0.001],
            ],
        ],
        'WEIGHT' => [
            'name' => 'Berat',
            'reference' => 'KG',
            'uoms' => [
                ['TON', 'Ton', 1000],
                ['KW', 'Kuintal', 100],
                ['SAK', 'Sak 50 kg', 50],
                ['KG', 'Kilogram', 1],
                ['ONS', 'Ons', 0.1],
                ['G', 'Gram', 0.001],
                ['MG', 'Miligram', 0.000001],
            ],
        ],
        'VOLUME' => [
            'name' => 'Volume',
            'reference' => 'L',
            'uoms' => [
                ['M3', 'Meter kubik', 1000],
                ['DRUM', 'Drum 200 L', 200],
                ['L', 'Liter', 1],
                ['ML', 'Mililiter', 0.001],
            ],
        ],
        'AREA' => [
            'name' => 'Luas',
            'reference' => 'M2',
            'uoms' => [
                ['HA', 'Hektar', 10000],
                ['M2', 'Meter persegi', 1],
                ['CM2', 'Sentimeter persegi', 0.0001],
                ['MM2', 'Milimeter persegi', 0.000001],
            ],
        ],
    ];

    /**
     * Memasang satuan bawaan. `$timpa` = true (company baru): nama & faktor
     * disamakan dengan daftar. `$timpa` = false (company lama): hanya satuan &
     * kategori yang **belum ada** yang ditambahkan — buatan/ubahan company tidak disentuh.
     *
     * @return int jumlah satuan yang baru dibuat
     */
    public static function pasang(bool $timpa = true): int
    {
        $dibuat = 0;

        foreach (self::DAFTAR as $kode => $definisi) {
            $kategori = UomCategory::query()->where('code', $kode)->first();

            if ($kategori === null) {
                $kategori = UomCategory::create(['code' => $kode, 'name' => $definisi['name'], 'is_active' => true]);
            } elseif ($timpa) {
                $kategori->fill(['name' => $definisi['name'], 'is_active' => true])->save();
            }

            foreach ($definisi['uoms'] as $baris) {
                [$uomKode, $uomNama, $faktor] = $baris;
                $data = [
                    'uom_category_id' => $kategori->id,
                    'name' => $uomNama,
                    'factor_to_reference' => $faktor,
                    'is_packaging' => (bool) ($baris[3] ?? false),
                    'is_active' => true,
                ];
                $ada = Uom::query()->where('code', $uomKode)->first();

                if ($ada === null) {
                    Uom::create(['code' => $uomKode] + $data);
                    $dibuat++;
                } elseif ($timpa) {
                    $ada->fill($data)->save();
                } elseif ((bool) ($baris[3] ?? false) && ! $ada->is_packaging && (float) $ada->factor_to_reference === 1.0) {
                    // Company lama: satuan kemasan bawaan (BOX, DUS, …) yang belum ditandai.
                    $ada->forceFill(['is_packaging' => true])->save();
                }
            }

            // BR-MST-03: satuan acuan kategori selalu berfaktor 1.
            if ($kategori->reference_uom_id === null || $timpa) {
                $acuan = Uom::query()->where('code', $definisi['reference'])->first();

                if ($acuan !== null) {
                    $kategori->forceFill(['reference_uom_id' => $acuan->id])->save();
                }
            }
        }

        return $dibuat;
    }
}

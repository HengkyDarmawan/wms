<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Support;

use App\Domain\Warehouse\Models\Warehouse;

/**
 * Data **Cetak denah** (A-370): gambar tampak atas dari data denah yang sama
 * ({@see WarehouseLayoutData::build()}) + daftar barang per rak dari Tempat
 * Simpan, dan tips tata letak (saran statis). Tanpa harga (D-07); hanya
 * membaca.
 */
class FloorPlanPrintData
{
    /** Tips tata letak — saran saja, bukan aturan. */
    public const TIPS = [
        'Barang yang paling sering keluar ditaruh dekat pintu/dock dan jalur utama.',
        'Barang berat dan besar di tingkat paling bawah (L1) atau area lantai; barang ringan di atas.',
        'Barang sejenis dikumpulkan di satu rak supaya picking lebih pendek dan opname lebih mudah.',
        'Barang yang jarang keluar di rak belakang atau tingkat atas.',
        'Sisakan jalur forklift/troli tetap bebas; jangan menaruh barang di jalur.',
        'Barang ber-kedaluwarsa disusun supaya yang lebih dulu masuk mudah diambil lebih dulu (FIFO).',
        'Tandai "Khusus barang ini" hanya untuk tempat yang memang tidak boleh dipakai barang lain.',
    ];

    public function __construct(private readonly WarehouseLayoutData $denah) {}

    /** @return array<string, mixed> */
    public function build(Warehouse $gudang): array
    {
        $d = $this->denah->build($gudang);
        $tempat = $this->denah->tempatSimpan((int) $gudang->id);
        $daftar = [];

        foreach ($d['zones'] as $z) {
            foreach ($z['racks'] as $r) {
                $barang = [];

                foreach ($tempat['rak'][$r['id']] ?? [] as $b) {
                    $barang[] = $b + ['dimana' => $r['is_area'] ? __('area') : __('seluruh rak')];
                }

                foreach ($r['levels'] as $l) {
                    foreach ($l['bins'] as $bin) {
                        foreach ($tempat['bin'][$bin['id']] ?? [] as $b) {
                            $barang[] = $b + ['dimana' => $bin['pendek']];
                        }
                    }
                }

                $daftar[] = [
                    'zona' => $z['code'],
                    'rak' => $r['code'],
                    'nama' => $r['name'],
                    'area' => $r['is_area'],
                    'barang' => $barang,
                ];
            }
        }

        return $d + ['daftar' => $daftar, 'tips' => self::TIPS];
    }
}

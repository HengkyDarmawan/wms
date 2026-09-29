<?php

declare(strict_types=1);

namespace App\Domain\Master\Support;

use App\Domain\Master\Actions\SaveItem;
use App\Domain\Master\Models\Uom;

/**
 * A-355: baris kemasan sebagai kalimat "1 DUS berisi 40 PACK". Satuan isi
 * boleh satuan dasar atau kemasan lain item yang sama; hasilnya selalu
 * dihitung ke satuan dasar (`qty_base`) karena hanya angka itu yang dipakai
 * stok, cetakan, dan pemilih satuan dokumen.
 *
 * Satu penghitung untuk form item (tampilan langsung) dan {@see SaveItem}
 * (validasi saat simpan), supaya layar dan aksi tidak pernah berbeda pendapat.
 */
class PackagingSentence
{
    private const EPS = 0.00005;

    /**
     * @param  array<int, array<string, mixed>>  $rows  kunci `uom_id`, `content_qty`, `content_uom_id` ('' / null = satuan dasar), `is_nominal_piece`
     * @param  array<int, float>  $legacy  uom_id => qty_base tersimpan tanpa satuan isi; baris yang tidak diubah
     *                                     tidak dikenai aturan "lebih dari 1" (data lama, P-03)
     * @return array{
     *     rows: array<int, array{uom_id: int, content_qty: float, content_uom_id: ?int, qty_base: float, is_nominal_piece: bool}>,
     *     results: array<int, array{hasil: string, rincian: ?string}>,
     *     errors: array<int, string>
     * }
     */
    public static function resolve(array $rows, int $baseUomId, bool $perPiece = false, array $legacy = []): array
    {
        $kode = self::kodeSatuan($rows, $baseUomId);
        $dasar = $kode[$baseUomId] ?? '';
        $galat = [];
        $baris = [];
        $barisSatuan = [];

        foreach ($rows as $i => $row) {
            $uom = self::id($row['uom_id'] ?? null);
            $isiTeks = trim((string) ($row['content_qty'] ?? ''));
            $satuanIsi = self::id($row['content_uom_id'] ?? null);

            if ($uom === null && $isiTeks === '' && $satuanIsi === null) {
                continue; // baris kosong dilewati seperti sebelumnya
            }

            $nama = $uom === null ? '' : ($kode[$uom] ?? '?');

            if ($uom === null) {
                $galat[$i] = 'Pilih satuan kemasan.';

                continue;
            }

            if (! isset($kode[$uom])) {
                $galat[$i] = 'Satuan kemasan tidak ditemukan.';

                continue;
            }

            if ($uom === $baseUomId) {
                $galat[$i] = $nama.' adalah satuan dasar; kemasan harus satuan lain.';

                continue;
            }

            if (isset($barisSatuan[$uom])) {
                $galat[$i] = 'Kemasan '.$nama.' sudah ada di baris '.($barisSatuan[$uom] + 1).'.';

                continue;
            }

            $barisSatuan[$uom] = $i;

            if ($satuanIsi === $uom) {
                $galat[$i] = '1 '.$nama.' tidak bisa berisi '.$nama.'.';

                continue;
            }

            $isi = self::angka($isiTeks);

            if ($isi === null || $isi <= 0) {
                $galat[$i] = 'Isi 1 '.$nama.' wajib diisi.';

                continue;
            }

            $lama = $satuanIsi === null && isset($legacy[$uom]) && abs($legacy[$uom] - $isi) < self::EPS;

            if ($isi <= 1 && ! $lama) {
                $galat[$i] = 'Isi 1 '.$nama.' harus lebih dari 1.';

                continue;
            }

            if ($perPiece && $satuanIsi !== null && $satuanIsi !== $baseUomId) {
                $galat[$i] = 'Item per potong: isi kemasan ditulis dalam satuan dasar ('.$dasar.').';

                continue;
            }

            $baris[$i] = [
                'uom_id' => $uom,
                'content_qty' => $isi,
                'content_uom_id' => $satuanIsi === $baseUomId ? null : $satuanIsi,
                'is_nominal_piece' => (bool) ($row['is_nominal_piece'] ?? false),
                'lama' => $lama,
            ];
        }

        // Satuan isi harus satuan dasar atau kemasan lain di daftar yang sama.
        foreach ($baris as $i => $b) {
            $isi = $b['content_uom_id'];

            if ($isi !== null && ! isset($barisSatuan[$isi])) {
                $galat[$i] = 'Satuan isi '.($kode[$isi] ?? '?').' tidak ada di daftar kemasan item ini; tambahkan dulu atau pilih '.$dasar.'.';
                unset($baris[$i]);
            }
        }

        $hasil = [];
        $keadaan = [];

        foreach (array_keys($baris) as $i) {
            self::hitung($i, $baris, $barisSatuan, $kode, $keadaan, $hasil, $galat);
        }

        $siap = [];
        $tampil = [];

        foreach ($baris as $i => $b) {
            if (! isset($hasil[$i]) || isset($galat[$i])) {
                continue;
            }

            $qtyBase = round($hasil[$i], 4);

            if ($qtyBase <= 1 + self::EPS && ! $b['lama']) {
                $galat[$i] = '1 '.$kode[$b['uom_id']].' harus berisi lebih dari 1 '.$dasar.'.';

                continue;
            }

            $siap[$i] = [
                'uom_id' => $b['uom_id'],
                'content_qty' => $b['content_qty'],
                'content_uom_id' => $b['content_uom_id'],
                'qty_base' => $qtyBase,
                'is_nominal_piece' => $b['is_nominal_piece'],
            ];

            $tampil[$i] = [
                'hasil' => QtyFormat::withUnit($qtyBase, $dasar),
                'rincian' => $b['content_uom_id'] === null ? null
                    : QtyFormat::number($b['content_qty']).' × '.QtyFormat::number($hasil[$barisSatuan[$b['content_uom_id']]] ?? 0),
            ];
        }

        ksort($galat);

        return ['rows' => $siap, 'results' => $tampil, 'errors' => $galat];
    }

    /**
     * Isi satu baris dalam satuan dasar, menelusuri satuan isinya. Keadaan 1 =
     * sedang dihitung (bertemu lagi = lingkaran), 2 = selesai.
     *
     * @param  array<int, array<string, mixed>>  $baris
     * @param  array<int, int>  $barisSatuan
     * @param  array<int, string>  $kode
     * @param  array<int, int>  $keadaan
     * @param  array<int, float>  $hasil
     * @param  array<int, string>  $galat
     */
    private static function hitung(int $i, array $baris, array $barisSatuan, array $kode, array &$keadaan, array &$hasil, array &$galat, array $jalur = []): ?float
    {
        if (($keadaan[$i] ?? 0) === 2) {
            return $hasil[$i] ?? null;
        }

        if (($keadaan[$i] ?? 0) === 1) {
            // Lingkaran: tandai setiap baris di dalamnya dengan kalimat yang sama.
            $lingkar = array_slice($jalur, (int) array_search($i, $jalur, true));
            $teks = implode(', ', array_map(fn (int $j) => $kode[$baris[$j]['uom_id']].' berisi '.$kode[$baris[$j]['content_uom_id']], $lingkar));

            foreach ($lingkar as $j) {
                $galat[$j] = 'Kemasan saling berisi ('.$teks.'). Pilih satuan isi yang lebih kecil.';
            }

            return null;
        }

        $keadaan[$i] = 1;
        $b = $baris[$i];

        if ($b['content_uom_id'] === null) {
            $nilai = $b['content_qty'];
        } else {
            $j = $barisSatuan[$b['content_uom_id']];
            $isi = isset($baris[$j]) ? self::hitung($j, $baris, $barisSatuan, $kode, $keadaan, $hasil, $galat, [...$jalur, $i]) : null;
            $nilai = $isi === null || isset($galat[$i]) ? null : $b['content_qty'] * $isi;
        }

        $keadaan[$i] = 2;

        if ($nilai !== null) {
            $hasil[$i] = $nilai;
        }

        return $nilai;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, string>
     */
    private static function kodeSatuan(array $rows, int $baseUomId): array
    {
        $ids = [$baseUomId];

        foreach ($rows as $row) {
            $ids[] = self::id($row['uom_id'] ?? null);
            $ids[] = self::id($row['content_uom_id'] ?? null);
        }

        return Uom::query()->whereIn('id', array_filter(array_unique($ids)))->pluck('code', 'id')
            ->mapWithKeys(fn ($c, $id) => [(int) $id => (string) $c])->all();
    }

    private static function id(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private static function angka(string $teks): ?float
    {
        $teks = str_replace(',', '.', $teks);

        return is_numeric($teks) ? (float) $teks : null;
    }
}

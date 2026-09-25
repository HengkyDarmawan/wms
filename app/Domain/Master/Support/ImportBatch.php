<?php

declare(strict_types=1);

namespace App\Domain\Master\Support;

use App\Domain\Master\Exceptions\MasterRuleException;
use Illuminate\Support\Facades\DB;

/**
 * Impor semua-atau-tidak (A-192): setiap baris diproses dalam satu transaksi;
 * galat baris dikumpulkan dan bila ada satu saja, seluruh impor dibatalkan
 * dengan daftar galat (maks 50 baris ditampilkan).
 */
class ImportBatch
{
    /**
     * @param  array<int, array<string, mixed>>  $rows  nomor baris Excel => kolom
     * @param  callable(array<string, mixed>, int): void  $simpan  lempar MasterRuleException/\ValueError bila baris salah
     * @return int jumlah baris tersimpan
     */
    public static function run(array $rows, callable $simpan, string $jenis): int
    {
        $galat = [];
        $jumlah = 0;

        DB::beginTransaction();

        try {
            foreach ($rows as $nomor => $r) {
                try {
                    $simpan($r, $nomor);
                    $jumlah++;
                } catch (MasterRuleException $e) {
                    $galat[] = 'Baris '.$nomor.': '.($e->fieldErrors !== [] ? implode(' ', $e->fieldErrors) : $e->getMessage());
                } catch (\ValueError|\InvalidArgumentException $e) {
                    $galat[] = 'Baris '.$nomor.': '.$e->getMessage();
                }
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        if ($galat !== []) {
            DB::rollBack();

            throw new MasterRuleException(
                'Impor dibatalkan, tidak ada '.$jenis.' yang disimpan. '.count($galat).' baris bermasalah.',
                'BR-GEN-11',
                ['rows' => implode("\n", array_slice($galat, 0, 50))],
            );
        }

        DB::commit();

        return $jumlah;
    }
}

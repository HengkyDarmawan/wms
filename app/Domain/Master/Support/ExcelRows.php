<?php

declare(strict_types=1);

namespace App\Domain\Master\Support;

use App\Domain\Master\Exceptions\MasterRuleException;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Pembaca lembar Excel impor master (A-192): baris pertama = judul kolom
 * templat; judul dicocokkan ke kunci kolom (boleh berupa kunci atau label
 * templat). Hasil: nomor baris Excel => [kunci => nilai]; baris kosong dilewati.
 */
class ExcelRows
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * @param  array<string, string>  $columns  kunci => label templat
     * @return array<int, array<string, mixed>>
     */
    public static function read(UploadedFile $file, array $columns, int $maxRows): array
    {
        if (! in_array(strtolower($file->getClientOriginalExtension()), ['xlsx', 'xls', 'csv'], true) || $file->getSize() > self::MAX_BYTES) {
            throw MasterRuleException::rule('NFR-14', 'Berkas harus Excel (.xlsx/.xls/.csv) maksimal 5 MB.');
        }

        try {
            $sheet = IOFactory::load($file->getRealPath())->getActiveSheet()->toArray(null, true, false, false);
        } catch (\Throwable) {
            throw MasterRuleException::rule('BR-GEN-11', 'Berkas tidak bisa dibaca sebagai Excel.');
        }

        $judul = array_shift($sheet) ?? [];
        $peta = [];

        foreach ($judul as $i => $j) {
            $teks = mb_strtolower(trim((string) $j));
            $kunci = str_replace(' ', '_', trim((string) preg_replace('/\s*\(.*$|:.*$|\s*\*.*$/', '', $teks)));
            $peta[$i] = collect(array_keys($columns))->first(fn ($k) => $k === $kunci || mb_strtolower($columns[$k]) === $teks) ?? $kunci;
        }

        $hasil = [];

        foreach ($sheet as $n => $sel) {
            $r = [];

            foreach ($sel as $i => $nilai) {
                $r[$peta[$i] ?? $i] = is_string($nilai) ? trim($nilai) : $nilai;
            }

            if (collect($r)->filter(fn ($v) => $v !== null && $v !== '')->isEmpty()) {
                continue;
            }

            $hasil[$n + 2] = $r;
        }

        if ($hasil === []) {
            throw MasterRuleException::rule('BR-GEN-11', 'Berkas tidak berisi baris data di bawah judul kolom.');
        }

        if (count($hasil) > $maxRows) {
            throw MasterRuleException::rule('BR-GEN-11', 'Maksimal '.$maxRows.' baris per impor.');
        }

        return $hasil;
    }
}

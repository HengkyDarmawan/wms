<?php

declare(strict_types=1);

namespace App\Domain\Stock\Support;

use App\Domain\Stock\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Nomor dokumen (AD-13, BR-GEN-06).
 *
 * Format: `{JENIS}/{SEGMEN}/{YYMM}/{URUT}` — mis. `GRN/CKG/2609/0001`.
 * Segmen adalah kode gudang untuk dokumen bergudang, dan `ALL` untuk dokumen
 * lintas gudang seperti OPN dan PRQ.
 *
 * Baris penghitung dikunci `FOR UPDATE`, jadi dua proses yang meminta nomor
 * bersamaan tidak pernah mendapat nomor yang sama.
 */
class DocumentNumber
{
    private const PANJANG_URUT = 4;

    /** Nomor final untuk satu dokumen. */
    public function next(string $documentType, string $segment = 'ALL', ?\Carbon\CarbonInterface $date = null): string
    {
        $tanggal = $date ?? now();
        $periode = $tanggal->format('Y-m');
        $jenis = mb_strtoupper($documentType);
        $segmen = mb_strtoupper($segment) ?: 'ALL';

        $urut = DB::transaction(function () use ($jenis, $segmen, $periode): int {
            $baris = DocumentSequence::query()
                ->where('document_type', $jenis)
                ->where('segment', $segmen)
                ->where('period', $periode)
                ->lockForUpdate()
                ->first();

            if ($baris === null) {
                // Dibuat lebih dulu lalu dikunci ulang, supaya dua proses yang
                // memulai periode yang sama tidak membuat dua baris.
                DocumentSequence::query()->insertOrIgnore([
                    'document_type' => $jenis,
                    'segment' => $segmen,
                    'period' => $periode,
                    'last_number' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $baris = DocumentSequence::query()
                    ->where('document_type', $jenis)
                    ->where('segment', $segmen)
                    ->where('period', $periode)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $berikutnya = $baris->last_number + 1;

            $baris->forceFill(['last_number' => $berikutnya])->save();

            return $berikutnya;
        });

        return $jenis.'/'.$segmen.'/'.$tanggal->format('ym').'/'
            .str_pad((string) $urut, self::PANJANG_URUT, '0', STR_PAD_LEFT);
    }

    /**
     * Nomor sementara untuk dokumen yang dibuat luring (BR-GEN-06).
     * Diganti nomor final saat perangkat menyinkronkan datanya.
     */
    public function temporary(): string
    {
        return 'TMP-'.Str::uuid();
    }

    public function isTemporary(string $number): bool
    {
        return str_starts_with($number, 'TMP-');
    }
}

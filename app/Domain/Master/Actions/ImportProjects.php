<?php

declare(strict_types=1);

namespace App\Domain\Master\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\Client;
use App\Domain\Master\Support\ExcelRows;
use App\Domain\Master\Support\ImportBatch;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Permission: `project.create` (+ `client.create` bila klien baru) — impor
 * proyek dari Excel (A-192). Klien dicari dari kode; bila belum ada dan nama
 * klien diisi, klien dibuat lewat {@see SaveClient}. Proyek lewat
 * {@see SaveProject} (BR-MST-01, BR-MST-04). Semua atau tidak sama sekali;
 * hanya menambah.
 */
class ImportProjects
{
    public const COLUMNS = [
        'kode_proyek' => 'Kode proyek * (boleh kosong bila penomoran otomatis proyek aktif)',
        'nama_proyek' => 'Nama proyek *',
        'kode_klien' => 'Kode klien *',
        'nama_klien' => 'Nama klien (diisi bila klien baru)',
        'alamat' => 'Alamat proyek',
        'mulai' => 'Tanggal mulai (TTTT-BB-HH)',
        'target_selesai' => 'Target selesai (TTTT-BB-HH)',
    ];

    public const MAX_ROWS = 1000;

    public function __construct(
        private readonly SaveProject $projects,
        private readonly SaveClient $clients,
    ) {}

    public function handle(UploadedFile $file, User $actor): int
    {
        $baris = ExcelRows::read($file, self::COLUMNS, self::MAX_ROWS);
        $bolehKlienBaru = $actor->hasPermission('client.create');

        $jumlah = ImportBatch::run($baris, function (array $r) use ($actor, $bolehKlienBaru) {
            $kodeKlien = mb_strtoupper(trim((string) ($r['kode_klien'] ?? '')));

            if ($kodeKlien === '') {
                throw MasterRuleException::rule('BR-GEN-11', 'kode klien wajib diisi.');
            }

            $klien = Client::query()->where('code', $kodeKlien)->first();

            if ($klien === null) {
                $nama = trim((string) ($r['nama_klien'] ?? ''));

                if ($nama === '' || ! $bolehKlienBaru) {
                    throw MasterRuleException::rule('BR-GEN-11', 'klien "'.$kodeKlien.'" tidak dikenal'.($bolehKlienBaru ? '; isi nama klien untuk membuatnya.' : ' dan Anda tidak berhak membuat klien.'));
                }

                $klien = $this->clients->handle(null, ['code' => $kodeKlien, 'name' => $nama], $actor);
            }

            $this->projects->handle(null, [
                'code' => trim((string) ($r['kode_proyek'] ?? '')),
                'name' => trim((string) ($r['nama_proyek'] ?? '')),
                'client_id' => $klien->id,
                'is_internal' => false,
                'address' => ($a = trim((string) ($r['alamat'] ?? ''))) === '' ? null : $a,
                'start_date' => $this->tanggal($r['mulai'] ?? null, 'mulai'),
                'target_end_date' => $this->tanggal($r['target_selesai'] ?? null, 'target selesai'),
            ], $actor);
        }, 'proyek');

        activity('master')->causedBy($actor)->withProperties(['jumlah' => $jumlah, 'berkas' => $file->getClientOriginalName()])
            ->log('Impor proyek dari Excel: '.$jumlah.' proyek');

        return $jumlah;
    }

    private function tanggal(mixed $nilai, string $label): ?string
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }

        try {
            // Sel tanggal Excel terbaca sebagai angka seri.
            return is_numeric($nilai)
                ? Carbon::instance(Date::excelToDateTimeObject((float) $nilai))->toDateString()
                : Carbon::parse((string) $nilai)->toDateString();
        } catch (\Throwable) {
            throw MasterRuleException::rule('BR-GEN-11', 'tanggal '.$label.' tidak valid.');
        }
    }
}

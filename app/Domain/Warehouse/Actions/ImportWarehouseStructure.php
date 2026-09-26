<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Support\ExcelRows;
use App\Domain\Master\Support\ImportBatch;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Rack;
use App\Domain\Warehouse\Models\RackLevel;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\Zone;
use App\Domain\Warehouse\Support\BinCodeBuilder;
use Illuminate\Http\UploadedFile;

/**
 * Permission: `bin.manage` — impor struktur gudang dari Excel (A-258): satu
 * baris = satu bin di gudang yang sudah ada. Zona, rak, dan level yang belum
 * ada dibuat lewat {@see SaveLocation}, bin lewat {@see SaveBin}, sehingga
 * aturan (BR-WH-01/02/03, AD-14) dan audit sama dengan layar. Semua atau
 * tidak sama sekali (A-207); hanya menambah — kode bin yang sudah ada ditolak,
 * tidak ditimpa. Posisi/ukuran denah tidak diimpor (rak baru ditata otomatis).
 */
class ImportWarehouseStructure
{
    public const COLUMNS = [
        'gudang' => 'Kode gudang *',
        'zona' => 'Zona *',
        'nama_zona' => 'Nama zona (untuk zona baru)',
        'rak' => 'Rak *',
        'level' => 'Level *',
        'bin' => 'Kode bin *',
        'jenis' => 'Jenis bin: storage',
        'kapasitas' => 'Kapasitas (jumlah, opsional)',
    ];

    public const MAX_ROWS = 2000;

    /** Batas panjang kolom `zones`/`racks`/`rack_levels`.`code` dan `bins`.`code`. */
    private const PANJANG_LOKASI = 10;

    private const PANJANG_BIN = 40;

    private const PANJANG_NAMA_ZONA = 60;

    public function __construct(
        private readonly SaveLocation $locations,
        private readonly SaveBin $bins,
    ) {}

    /** @return int jumlah bin dibuat */
    public function handle(UploadedFile $file, User $actor): int
    {
        $baris = ExcelRows::read($file, self::COLUMNS, self::MAX_ROWS);

        /** @var array<string, int> $kodeDiBerkas kode bin penuh ⇒ nomor baris Excel */
        $kodeDiBerkas = [];

        $dibuat = ImportBatch::run($baris, function (array $r, int $nomor) use ($actor, &$kodeDiBerkas) {
            $gudang = $this->gudang($r['gudang'] ?? null, $actor);
            $jenis = $this->jenis($r['jenis'] ?? null);
            $kapasitas = $this->kapasitas($r['kapasitas'] ?? null);

            $kodeZona = $this->segmen($r['zona'] ?? null, 'zona', self::PANJANG_LOKASI);
            $kodeRak = $this->segmen($r['rak'] ?? null, 'rak', self::PANJANG_LOKASI);
            $kodeLevel = $this->segmen($r['level'] ?? null, 'level', self::PANJANG_LOKASI);
            $kodeBin = $this->segmen($r['bin'] ?? null, 'kode bin', self::PANJANG_LOKASI);

            $namaZona = trim((string) ($r['nama_zona'] ?? ''));

            if (mb_strlen($namaZona) > self::PANJANG_NAMA_ZONA) {
                throw MasterRuleException::rule('BR-GEN-11', 'nama zona lebih dari '.self::PANJANG_NAMA_ZONA.' karakter.');
            }

            try {
                $level = $this->level($gudang, $kodeZona, $namaZona, $kodeRak, $kodeLevel, $actor);

                $kodePenuh = BinCodeBuilder::forRackLevel($level, $kodeBin);

                if (strlen($kodePenuh) > self::PANJANG_BIN) {
                    throw MasterRuleException::rule('BR-GEN-11', 'kode bin "'.$kodePenuh.'" lebih dari '.self::PANJANG_BIN.' karakter.');
                }

                if (isset($kodeDiBerkas[$kodePenuh])) {
                    throw MasterRuleException::rule('BR-WH-01', 'kode bin "'.$kodePenuh.'" ganda di berkas (sama dengan baris '.$kodeDiBerkas[$kodePenuh].').');
                }

                $kodeDiBerkas[$kodePenuh] = $nomor;

                // Kode yang sudah ada ditolak SaveBin (BR-WH-01), tidak ditimpa.
                $this->bins->handle($gudang, null, [
                    'bin_type' => $jenis,
                    'rack_level_id' => $level->id,
                    'code' => $kodeBin,
                    'capacity_qty' => $kapasitas,
                ], $actor);
            } catch (WarehouseRuleException $e) {
                throw new MasterRuleException($e->getMessage(), $e->rule, $e->fieldErrors);
            }
        }, 'bin');

        activity('warehouse')->causedBy($actor)->withProperties(['jumlah' => $dibuat, 'berkas' => $file->getClientOriginalName()])
            ->log('Impor struktur gudang dari Excel: '.$dibuat.' bin');

        return $dibuat;
    }

    /** Gudang harus sudah ada, aktif, dan dalam cakupan user (BR-ACC-05). */
    private function gudang(mixed $nilai, User $actor): Warehouse
    {
        $kode = trim((string) $nilai);
        $gudang = Warehouse::withoutGlobalScopes()->where('code', mb_strtoupper($kode))->first();

        if ($gudang === null || ! $actor->canAccessWarehouse((int) $gudang->id)) {
            throw MasterRuleException::rule('BR-GEN-09', 'gudang "'.$kode.'" tidak dikenal atau di luar cakupan Anda.');
        }

        if (! $gudang->is_active) {
            throw MasterRuleException::rule('BR-WH-07', 'gudang '.$gudang->code.' nonaktif.');
        }

        return $gudang;
    }

    /**
     * Zona → rak → level: yang aktif dipakai ulang, yang belum ada dibuat,
     * yang nonaktif menolak baris. Pencarian di dalam transaksi impor, jadi
     * lokasi yang dibuat baris sebelumnya ikut terpakai.
     */
    private function level(Warehouse $gudang, string $kodeZona, string $namaZona, string $kodeRak, string $kodeLevel, User $actor): RackLevel
    {
        $zona = Zone::query()->where('warehouse_id', $gudang->id)->where('code', $kodeZona)->first()
            ?? $this->locations->saveZone($gudang, null, ['code' => $kodeZona, 'name' => $namaZona !== '' ? $namaZona : 'Zona '.$kodeZona], $actor);
        $this->aktif($zona->is_active, 'zona '.$kodeZona);

        $rak = Rack::query()->where('zone_id', $zona->id)->where('code', $kodeRak)->first()
            ?? $this->locations->saveRack($zona, null, ['code' => $kodeRak], $actor);
        $this->aktif($rak->is_active, 'rak '.$kodeZona.'-'.$kodeRak);

        $level = RackLevel::query()->where('rack_id', $rak->id)->where('code', $kodeLevel)->first()
            ?? $this->locations->saveLevel($rak, null, ['code' => $kodeLevel], $actor);
        $this->aktif($level->is_active, 'level '.$kodeZona.'-'.$kodeRak.'-'.$kodeLevel);

        return $level;
    }

    private function aktif(bool $aktif, string $nama): void
    {
        if (! $aktif) {
            throw MasterRuleException::rule('BR-WH-07', $nama.' nonaktif; aktifkan dulu lewat detail gudang.');
        }
    }

    private function segmen(mixed $nilai, string $kolom, int $maks): string
    {
        $kode = BinCodeBuilder::segment((string) $nilai);

        if ($kode === '') {
            throw MasterRuleException::rule('BR-GEN-11', $kolom.' wajib diisi (huruf/angka).');
        }

        if (strlen($kode) > $maks) {
            throw MasterRuleException::rule('BR-GEN-11', $kolom.' "'.$kode.'" lebih dari '.$maks.' karakter.');
        }

        return $kode;
    }

    /**
     * Jenis bin dari Katalog (kode atau label UI); kosong = Penyimpanan. Jenis
     * bawaan ditolak SaveBin (BR-WH-02); On-site dibuat per proyek (BR-WH-03).
     */
    private function jenis(mixed $nilai): BinType
    {
        $isi = mb_strtolower(trim((string) $nilai));

        if ($isi === '') {
            return BinType::Storage;
        }

        foreach (BinType::cases() as $kasus) {
            if ($kasus->value === $isi || mb_strtolower($kasus->label()) === $isi) {
                if ($kasus === BinType::OnSite) {
                    throw MasterRuleException::rule('BR-WH-03', 'bin On-site Proyek dibuat otomatis per proyek, tidak lewat impor.');
                }

                return $kasus;
            }
        }

        throw MasterRuleException::rule('AD-14', 'jenis bin "'.$isi.'" tidak dikenal.');
    }

    private function kapasitas(mixed $nilai): ?float
    {
        $teks = str_replace(',', '.', trim((string) $nilai));

        if ($teks === '') {
            return null;
        }

        if (! is_numeric($teks) || (float) $teks <= 0) {
            throw MasterRuleException::rule('BR-GEN-11', 'kapasitas "'.trim((string) $nilai).'" harus angka lebih dari 0.');
        }

        return (float) $teks;
    }
}

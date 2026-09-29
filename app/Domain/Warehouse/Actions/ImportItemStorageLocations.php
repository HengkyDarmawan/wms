<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Support\ExcelRows;
use App\Domain\Master\Support\ImportBatch;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Rack;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Support\BinCode;
use Illuminate\Http\UploadedFile;

/**
 * Permission: `bin.manage` — impor **Tempat Simpan** dari Excel (A-371):
 * `Kode item | Gudang | Tempat | Khusus`. Tempat = kode bin lengkap, kode
 * pendek ("R01 · L2 · 01", boleh berawalan zona), kode rak (= seluruh rak),
 * atau kode area lantai. Setiap baris ditambahkan di akhir urutan tempat
 * item itu lewat {@see SaveItemStorageLocations::add()} (tempat yang sudah
 * ada hanya diperbarui tanda Khusus-nya), sehingga aturan BR-WH-10 dan riwayat
 * sama dengan layar. Semua atau tidak sama sekali (A-207).
 */
class ImportItemStorageLocations
{
    public const COLUMNS = [
        'item' => 'Kode item *',
        'gudang' => 'Kode gudang *',
        'tempat' => 'Tempat * (kode bin / kode pendek / kode rak / kode area)',
        'khusus' => 'Khusus barang ini: Ya/Tidak',
    ];

    public const MAX_ROWS = 2000;

    /** @var array<int, array<string, array<int, string>>> gudang_id → kunci ternormalkan → kunci tempat */
    private array $peta = [];

    public function __construct(private readonly SaveItemStorageLocations $simpan) {}

    /** @return int jumlah baris tempat simpan yang diimpor */
    public function handle(UploadedFile $file, User $actor): int
    {
        $baris = ExcelRows::read($file, self::COLUMNS, self::MAX_ROWS);
        $this->peta = [];
        $dilihat = [];

        $jumlah = ImportBatch::run($baris, function (array $r, int $nomor) use ($actor, &$dilihat) {
            $item = Item::query()->where('code', mb_strtoupper(trim((string) ($r['item'] ?? ''))))->first();

            if ($item === null) {
                throw MasterRuleException::rule('BR-GEN-11', 'item "'.trim((string) ($r['item'] ?? '')).'" tidak dikenal.');
            }

            $kodeGudang = trim((string) ($r['gudang'] ?? ''));
            $gudang = Warehouse::withoutGlobalScopes()->where('code', mb_strtoupper($kodeGudang))->first();

            if ($gudang === null || ! $actor->canAccessWarehouse((int) $gudang->id)) {
                throw MasterRuleException::rule('BR-GEN-09', 'gudang "'.$kodeGudang.'" tidak dikenal atau di luar cakupan Anda.');
            }

            $tempat = $this->tempat($gudang, (string) ($r['tempat'] ?? ''));
            $kunci = $item->id.'|'.$tempat;

            if (isset($dilihat[$kunci])) {
                throw MasterRuleException::rule('BR-GEN-11', 'tempat ini sudah ada untuk '.$item->code.' di baris '.$dilihat[$kunci].'.');
            }

            $dilihat[$kunci] = $nomor;

            try {
                $this->simpan->add($item, $gudang, $tempat, $this->khusus($r['khusus'] ?? null), $actor);
            } catch (WarehouseRuleException $e) {
                throw new MasterRuleException($e->getMessage(), $e->rule, $e->fieldErrors);
            }
        }, 'tempat simpan');

        activity('warehouse')->causedBy($actor)->withProperties(['jumlah' => $jumlah, 'berkas' => $file->getClientOriginalName()])
            ->log('Impor tempat simpan dari Excel: '.$jumlah.' baris');

        return $jumlah;
    }

    /** Kunci `bin:{id}` / `rak:{id}` dari isian kolom Tempat. */
    private function tempat(Warehouse $gudang, string $isian): string
    {
        $asli = trim($isian);

        if ($asli === '') {
            throw MasterRuleException::rule('BR-GEN-11', 'tempat wajib diisi.');
        }

        $bin = Bin::query()->withoutGlobalScopes()->where('warehouse_id', $gudang->id)->where('code', mb_strtoupper($asli))->first();

        if ($bin !== null) {
            return 'bin:'.$bin->id;
        }

        $cocok = $this->peta($gudang)[self::normal($asli)] ?? [];

        if (count($cocok) > 1) {
            throw MasterRuleException::rule('BR-GEN-11', 'tempat "'.$asli.'" ada di beberapa zona; tulis dengan zona, mis. "A · '.$asli.'".');
        }

        if ($cocok === []) {
            throw MasterRuleException::rule('BR-GEN-11', 'tempat "'.$asli.'" tidak ditemukan di gudang '.$gudang->code.' (kode bin, kode pendek, kode rak, atau kode area).');
        }

        return $cocok[0];
    }

    /**
     * Semua cara menulis tempat di satu gudang → kunci tempat.
     *
     * @return array<string, array<int, string>>
     */
    private function peta(Warehouse $gudang): array
    {
        if (isset($this->peta[$gudang->id])) {
            return $this->peta[$gudang->id];
        }

        $peta = [];
        $tambah = function (string $teks, string $kunci) use (&$peta): void {
            $n = self::normal($teks);

            if (! in_array($kunci, $peta[$n] ?? [], true)) {
                $peta[$n][] = $kunci;
            }
        };

        foreach (Rack::query()->with('zone:id,code,warehouse_id')->whereHas('zone', fn ($q) => $q->where('warehouse_id', $gudang->id))->get() as $rak) {
            $kunci = 'rak:'.$rak->id;
            $tambah($rak->code, $kunci);
            $tambah($rak->zone->code.'-'.$rak->code, $kunci);

            if ($rak->is_area) {
                $tambah($rak->code.'-AREA', $kunci);
                $tambah($rak->zone->code.'-'.$rak->code.'-AREA', $kunci);
            }
        }

        foreach (Bin::query()->withoutGlobalScopes()->where('warehouse_id', $gudang->id)->whereNotNull('rack_level_id')->where('code', 'not like', '%-AREA')->get(['id', 'code']) as $bin) {
            $tambah(BinCode::pendek((string) $bin->code), 'bin:'.$bin->id);
            $tambah(BinCode::pendek((string) $bin->code, true), 'bin:'.$bin->id);
        }

        return $this->peta[$gudang->id] = $peta;
    }

    /** "a · R01 · L2 · 01" → "A-R01-L2-01". */
    private static function normal(string $teks): string
    {
        $t = mb_strtoupper(str_replace('·', '-', $teks));
        $t = (string) preg_replace('/[\s\-]+/u', '-', $t);

        return trim($t, '-');
    }

    private function khusus(mixed $nilai): bool
    {
        $isi = mb_strtolower(trim((string) $nilai));

        return match ($isi) {
            '', 'tidak', 't', 'no', 'n', '0', 'false' => false,
            'ya', 'y', 'yes', '1', 'true', 'khusus' => true,
            default => throw MasterRuleException::rule('BR-GEN-11', 'kolom Khusus "'.trim((string) $nilai).'" harus Ya atau Tidak.'),
        };
    }
}

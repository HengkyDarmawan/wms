<?php

declare(strict_types=1);

namespace App\Domain\Adjustment\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Adjustment\Exceptions\AdjustmentRuleException;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Adjustment\Support\AdjustmentLines;
use App\Domain\Approval\Exceptions\ApprovalRuleException;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Support\ExcelRows;
use App\Domain\Master\Support\ImportBatch;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Support\StorageLocationPlanner;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Permission: `adjustment.create` — impor saldo awal dari Excel (A-192, A-207).
 *
 * Stok tidak pernah ditulis langsung (P-01): setiap gudang di berkas menjadi
 * **satu ADJ manual** arah tambah dengan Alasan *Saldo awal*, yang tetap
 * melewati approval (A-09) dan baru masuk kartu stok saat diposting. Baris
 * diperiksa dengan aturan yang sama dengan form ADJ ({@see AdjustmentLines});
 * satu baris salah membatalkan seluruh impor (semua atau tidak).
 */
class ImportOpeningStock
{
    public const COLUMNS = [
        'gudang' => 'Kode gudang *',
        // A-381: boleh kosong bila item punya tempat simpan di gudang itu.
        'bin' => 'Kode bin (kosong = dari tempat simpan)',
        'item' => 'Kode item *',
        'jumlah' => 'Jumlah (satuan dasar) *',
        'kondisi' => 'Kondisi: tersedia/karantina/rusak',
        'lot' => 'Nomor lot',
        'kedaluwarsa' => 'Kedaluwarsa (YYYY-MM-DD)',
        'serial' => 'Nomor serial',
        'panjang' => 'Panjang potongan',
        'keterangan' => 'Keterangan',
    ];

    public const MAX_ROWS = 2000;

    public const REASON_CODE = 'OPENING';

    public function __construct(
        private readonly AdjustmentLines $lines,
        private readonly CreateStockAdjustment $create,
    ) {}

    /** @return int jumlah baris saldo awal yang diajukan */
    public function handle(UploadedFile $file, User $actor): int
    {
        $baris = ExcelRows::read($file, self::COLUMNS, self::MAX_ROWS);

        /** @var array<int, list<array<string, mixed>>> $perGudang */
        $perGudang = [];
        $serial = [];
        /** @var array<int, array<int, float>> $rencana gudang_id => bin_id => jumlah dari baris sebelumnya */
        $rencana = [];

        $jumlah = ImportBatch::run($baris, function (array $r, int $nomor) use ($actor, &$perGudang, &$serial, &$rencana) {
            $gudang = Warehouse::withoutGlobalScopes()->where('code', mb_strtoupper(trim((string) ($r['gudang'] ?? ''))))->first();

            if ($gudang === null || ! $actor->canAccessWarehouse((int) $gudang->id)) {
                throw MasterRuleException::rule('BR-GEN-09', 'gudang "'.trim((string) ($r['gudang'] ?? '')).'" tidak dikenal atau di luar cakupan Anda.');
            }

            $item = Item::query()->where('code', mb_strtoupper(trim((string) ($r['item'] ?? ''))))->first();

            if ($item === null) {
                throw MasterRuleException::rule('BR-GEN-11', 'item "'.trim((string) ($r['item'] ?? '')).'" tidak dikenal.');
            }

            // Templat lama berjudul "Kode bin *" terbaca sebagai `kode_bin`.
            $kodeBin = trim((string) ($r['bin'] ?? $r['kode_bin'] ?? ''));
            $qtyRencana = $this->jumlahRencana($item, $r);

            if ($kodeBin === '') {
                $bin = $this->binDariTempatSimpan($item, $gudang, $qtyRencana, $rencana[(int) $gudang->id] ?? []);
            } else {
                $bin = Bin::withoutGlobalScopes()->where('warehouse_id', $gudang->id)->where('code', mb_strtoupper($kodeBin))->first();

                if ($bin === null) {
                    throw MasterRuleException::rule('BR-STK-02', 'bin "'.$kodeBin.'" tidak ada di gudang '.$gudang->code.'.');
                }
            }

            $isian = [
                'bin_id' => $bin->id,
                'item_id' => $item->id,
                'direction' => 'in',
                'stock_status' => $this->kondisi($r['kondisi'] ?? null)->value,
                'qty' => $r['jumlah'] ?? null,
                'lot_no' => $r['lot'] ?? null,
                'expiry_date' => $this->tanggal($r['kedaluwarsa'] ?? null),
                'serial_no' => $r['serial'] ?? null,
                'piece_length' => $r['panjang'] ?? null,
                'notes' => $r['keterangan'] ?? null,
            ];

            if ($item->tracking_mode === TrackingMode::Serial) {
                $kunci = $item->id.'|'.mb_strtoupper(trim((string) $isian['serial_no']));

                if (isset($serial[$kunci])) {
                    throw MasterRuleException::rule('BR-LED-04', 'serial '.$isian['serial_no'].' sudah ada di baris '.$serial[$kunci].'.');
                }
            }

            try {
                $this->lines->normalize((int) $gudang->id, [$isian], $actor);
            } catch (AdjustmentRuleException $e) {
                // Pesan AdjustmentLines diawali "Baris 1 (KODE): "; nomor baris Excel ditulis ImportBatch.
                throw MasterRuleException::rule($e->rule, (string) preg_replace('/^Baris \d+( \([^)]*\))?: /', '', $e->getMessage()));
            }

            if (isset($kunci)) {
                $serial[$kunci] = $nomor;
            }

            $perGudang[(int) $gudang->id][] = $isian;
            $rencana[(int) $gudang->id][(int) $bin->id] = ($rencana[(int) $gudang->id][(int) $bin->id] ?? 0) + $qtyRencana;
        }, 'saldo awal');

        $alasan = $this->alasan();

        try {
            $adj = DB::transaction(fn () => collect($perGudang)->map(
                fn (array $isian, int $gudangId) => $this->create->handle([
                    'warehouse_id' => $gudangId,
                    'reason_code_id' => $alasan,
                    'notes' => 'Saldo awal dari impor Excel '.mb_substr($file->getClientOriginalName(), 0, 150),
                ], $isian, $actor),
            )->values());
        } catch (AdjustmentRuleException|ApprovalRuleException $e) {
            throw MasterRuleException::rule($e->rule, 'Impor dibatalkan: '.$e->getMessage());
        }

        activity('adjustment')->causedBy($actor)
            ->withProperties(['baris' => $jumlah, 'adj' => $adj->map(fn (StockAdjustment $a) => $a->number)->all(), 'berkas' => $file->getClientOriginalName()])
            ->log('Impor saldo awal dari Excel: '.$jumlah.' baris');

        return $jumlah;
    }

    /**
     * A-381: kode bin kosong → bin dari **Tempat Simpan** item di gudang itu
     * (`StorageLocationPlanner::saranBin`: urutan tempat, kapasitas seluruh
     * isi bin + baris sebelumnya di berkas, bin gabungan lewat bin utama, bin
     * Khusus barang lain dilewati). Tanpa tempat simpan atau semua tempat
     * penuh → galat baris; aturan lama A-84 **tidak** dipakai di sini.
     *
     * @param  array<int, float>  $rencana  bin_id => jumlah dari baris sebelumnya
     */
    private function binDariTempatSimpan(Item $item, Warehouse $gudang, float $qty, array $rencana): Bin
    {
        $planner = app(StorageLocationPlanner::class);

        if ($planner->kandidat($item, $gudang)->isEmpty()) {
            throw MasterRuleException::rule('BR-STK-02', 'kode bin kosong, tetapi '.$item->code.' belum punya tempat simpan di gudang '.$gudang->code
                .' (atau semua tempatnya khusus barang lain/nonaktif); isi kode bin atau atur tempat simpannya dulu.');
        }

        $saran = $planner->saranBin($item, $gudang, $qty, $rencana);

        if (! $saran['dari_tempat_simpan'] || $saran['bin'] === null) {
            throw MasterRuleException::rule('BR-WH-06', 'kode bin kosong, tetapi tempat simpan '.$item->code.' di gudang '.$gudang->code
                .' penuh untuk '.round($qty, 4).' lagi; isi kode bin lain.');
        }

        return $saran['bin'];
    }

    /** Jumlah yang akan masuk bin (satuan dasar) untuk perencanaan kapasitas; serial = 1. */
    private function jumlahRencana(Item $item, array $r): float
    {
        if ($item->tracking_mode === TrackingMode::Serial) {
            return 1.0;
        }

        $nilai = $item->tracking_mode === TrackingMode::Piece && ($r['jumlah'] ?? '') === '' ? ($r['panjang'] ?? 0) : ($r['jumlah'] ?? 0);

        return is_numeric($nilai) ? max(0.0, (float) $nilai) : 0.0;
    }

    /** Alasan penyesuaian *Saldo awal*; company lama yang belum punya barisnya dibuatkan. */
    private function alasan(): int
    {
        return (int) ReasonCode::query()->firstOrCreate(
            ['context' => ReasonContext::Adjustment->value, 'code' => self::REASON_CODE],
            ['label' => 'Saldo awal', 'is_active' => true],
        )->id;
    }

    private function kondisi(mixed $nilai): StockStatus
    {
        $isi = is_scalar($nilai) ? mb_strtolower(trim((string) $nilai)) : '';

        if ($isi === '') {
            return StockStatus::Available;
        }

        foreach (StockStatus::cases() as $s) {
            if ($s->value === $isi || mb_strtolower($s->label()) === $isi) {
                return $s;
            }
        }

        throw MasterRuleException::rule('BR-GEN-11', 'kondisi "'.$isi.'" tidak dikenal (tersedia/karantina/rusak).');
    }

    private function tanggal(mixed $nilai): ?string
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }

        try {
            return is_numeric($nilai)
                ? Carbon::instance(Date::excelToDateTimeObject((float) $nilai))->toDateString()
                : Carbon::parse((string) $nilai)->toDateString();
        } catch (\Throwable) {
            throw MasterRuleException::rule('BR-STK-12', 'tanggal kedaluwarsa tidak valid.');
        }
    }
}

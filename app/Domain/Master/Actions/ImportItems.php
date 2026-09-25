<?php

declare(strict_types=1);

namespace App\Domain\Master\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\ItemCategory;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Support\ExcelRows;
use App\Domain\Master\Support\ImportBatch;
use Illuminate\Http\UploadedFile;

/**
 * Permission: `item.create` — impor item dari Excel (Blueprint §18 "Impor data
 * master dari Excel", A-192). Setiap baris lewat {@see SaveItem} sehingga
 * aturannya sama dengan form (BR-MST-01, matriks kombinasi BR-STK-11).
 * **Semua atau tidak sama sekali**: satu baris salah membatalkan seluruh
 * impor dan semua galat baris dilaporkan sekaligus. Item yang kodenya sudah
 * ada ditolak (impor hanya menambah, tidak mengubah).
 */
class ImportItems
{
    /** Kolom templat, urut sesuai berkas. */
    public const COLUMNS = [
        'kode' => 'Kode * (boleh kosong bila penomoran otomatis item aktif)',
        'nama' => 'Nama *',
        'kategori' => 'Kode kategori',
        'satuan' => 'Kode satuan dasar *',
        'pelacakan' => 'Pelacakan: none/lot/serial/piece',
        'kedaluwarsa' => 'Kedaluwarsa: ya/tidak',
        'kepemilikan' => 'Kepemilikan: consumable/asset/both',
        'strategi' => 'Strategi: fifo/fefo/manual/offcut_first',
        'titik_pesan_ulang' => 'Titik pesan ulang',
        'stok_minimum' => 'Stok minimum',
        'wajib_qc' => 'Wajib QC: ya/tidak',
        'barcode' => 'Barcode',
    ];

    public const MAX_ROWS = 2000;

    public function __construct(private readonly SaveItem $save) {}

    /** @return int jumlah item dibuat */
    public function handle(UploadedFile $file, User $actor): int
    {
        $baris = ExcelRows::read($file, self::COLUMNS, self::MAX_ROWS);

        $kategori = ItemCategory::query()->pluck('id', 'code')->mapWithKeys(fn ($id, $kode) => [mb_strtoupper((string) $kode) => $id]);
        $satuan = Uom::query()->pluck('id', 'code')->mapWithKeys(fn ($id, $kode) => [mb_strtoupper((string) $kode) => $id]);

        $dibuat = ImportBatch::run($baris, function (array $r) use ($kategori, $satuan, $actor) {
            $kodeKategori = mb_strtoupper(trim((string) ($r['kategori'] ?? '')));
            $kodeSatuan = mb_strtoupper(trim((string) ($r['satuan'] ?? '')));

            if ($kodeKategori !== '' && ! $kategori->has($kodeKategori)) {
                throw MasterRuleException::rule('BR-GEN-11', 'kategori "'.$kodeKategori.'" tidak dikenal.');
            }

            if (! $satuan->has($kodeSatuan)) {
                throw MasterRuleException::rule('BR-GEN-11', 'satuan "'.$kodeSatuan.'" tidak dikenal.');
            }

            $this->save->handle(null, [
                'code' => trim((string) ($r['kode'] ?? '')),
                'name' => trim((string) ($r['nama'] ?? '')),
                'item_category_id' => $kodeKategori === '' ? null : $kategori[$kodeKategori],
                'base_uom_id' => $satuan[$kodeSatuan],
                'tracking_mode' => $this->teks($r['pelacakan'] ?? null) ?? 'none',
                'has_expiry' => $this->ya($r['kedaluwarsa'] ?? null),
                'ownership_model' => $this->teks($r['kepemilikan'] ?? null) ?? 'consumable',
                'removal_strategy' => $this->teks($r['strategi'] ?? null),
                'reorder_point' => $r['titik_pesan_ulang'] ?? null,
                'min_stock' => $r['stok_minimum'] ?? null,
                'requires_qc' => $this->ya($r['wajib_qc'] ?? null),
                'barcode' => $this->teks($r['barcode'] ?? null),
                'is_cuttable' => false,
            ], null, null, $actor);
        }, 'item');

        activity('master')->causedBy($actor)->withProperties(['jumlah' => $dibuat, 'berkas' => $file->getClientOriginalName()])
            ->log('Impor item dari Excel: '.$dibuat.' item');

        return $dibuat;
    }

    private function teks(mixed $v): ?string
    {
        $isi = is_scalar($v) ? mb_strtolower(trim((string) $v)) : '';

        return $isi === '' ? null : $isi;
    }

    private function ya(mixed $v): bool
    {
        return in_array($this->teks($v), ['ya', 'y', 'yes', '1', 'true'], true);
    }
}

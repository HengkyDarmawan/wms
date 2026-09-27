<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Support\ExcelRows;
use App\Domain\Master\Support\ImportBatch;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use Illuminate\Http\UploadedFile;

/**
 * Permission: `warehouse.create` — impor gudang dari Excel (A-272, menutup
 * sisa [O-12]: struktur zona–bin sudah lewat A-258). Satu baris = satu gudang
 * baru lewat {@see SaveWarehouse}, jadi aturannya sama dengan form: kode unik
 * huruf besar (BR-MST-01), Gudang Site wajib proyek dan tipe lain tanpa proyek
 * (BR-WH-04), hierarki tidak melingkar (BR-WH-05), dan bin bawaan + bin
 * `in_transit` langsung dibuat (BR-WH-02). Semua atau tidak sama sekali dan
 * hanya menambah (A-207): kode yang sudah ada ditolak, tidak ditimpa. Induk
 * boleh gudang yang dibuat baris sebelumnya di berkas yang sama.
 */
class ImportWarehouses
{
    public const COLUMNS = [
        'kode' => 'Kode gudang *',
        'nama' => 'Nama gudang *',
        'tipe' => 'Tipe gudang * (kode atau nama, mis. MAIN / Gudang Utama)',
        'induk' => 'Kode gudang induk (opsional)',
        'proyek' => 'Kode proyek (wajib untuk Gudang Site)',
        'kepala' => 'Email kepala gudang (opsional)',
        'alamat' => 'Alamat',
    ];

    public const MAX_ROWS = 500;

    /** Batas panjang kolom tabel `warehouses`. */
    private const PANJANG = ['kode' => 10, 'nama' => 100, 'alamat' => 500];

    public function __construct(private readonly SaveWarehouse $save) {}

    /** @return int jumlah gudang dibuat */
    public function handle(UploadedFile $file, User $actor): int
    {
        $baris = ExcelRows::read($file, self::COLUMNS, self::MAX_ROWS);

        $dibuat = ImportBatch::run($baris, function (array $r) use ($actor) {
            foreach (self::PANJANG as $kolom => $maks) {
                if (mb_strlen(trim((string) ($r[$kolom] ?? ''))) > $maks) {
                    throw MasterRuleException::rule('BR-GEN-11', $kolom.' lebih dari '.$maks.' karakter.');
                }
            }

            $kode = trim((string) ($r['kode'] ?? ''));

            if ($kode === '') {
                // Form membuat kode dari nama; di impor kode wajib supaya baris
                // induk di berkas yang sama bisa dirujuk.
                throw MasterRuleException::rule('BR-GEN-11', 'kode gudang wajib diisi.');
            }

            try {
                $this->save->handle(null, [
                    'code' => $kode,
                    'name' => trim((string) ($r['nama'] ?? '')),
                    'warehouse_type_id' => $this->tipe($r['tipe'] ?? null)->id,
                    'parent_id' => $this->induk($r['induk'] ?? null, $actor),
                    'project_id' => $this->proyek($r['proyek'] ?? null, $actor),
                    'head_user_id' => $this->kepala($r['kepala'] ?? null),
                    'address' => $r['alamat'] ?? null,
                ], $actor);
            } catch (WarehouseRuleException $e) {
                throw new MasterRuleException($e->getMessage(), $e->rule, $e->fieldErrors);
            }
        }, 'gudang');

        activity('warehouse')->causedBy($actor)->withProperties(['jumlah' => $dibuat, 'berkas' => $file->getClientOriginalName()])
            ->log('Impor gudang dari Excel: '.$dibuat.' gudang');

        return $dibuat;
    }

    /** Tipe gudang aktif, dicari dari kode atau nama (AD-14: master, bukan enum). */
    private function tipe(mixed $nilai): WarehouseType
    {
        $isi = trim((string) $nilai);

        if ($isi === '') {
            throw MasterRuleException::rule('BR-GEN-11', 'tipe gudang wajib diisi.');
        }

        $tipe = WarehouseType::query()->where('is_active', true)
            ->where(fn ($q) => $q->where('code', mb_strtoupper($isi))->orWhereRaw('LOWER(name) = ?', [mb_strtolower($isi)]))
            ->first();

        if ($tipe === null) {
            throw MasterRuleException::rule('BR-GEN-11', 'tipe gudang "'.$isi.'" tidak dikenal atau nonaktif.');
        }

        return $tipe;
    }

    /** Induk harus ada (termasuk yang dibuat baris sebelumnya) dan dalam cakupan user (BR-ACC-05). */
    private function induk(mixed $nilai, User $actor): ?int
    {
        $kode = trim((string) $nilai);

        if ($kode === '') {
            return null;
        }

        $induk = Warehouse::withoutGlobalScopes()->where('code', mb_strtoupper($kode))->first();

        if ($induk === null || ! $actor->canAccessWarehouse((int) $induk->id)) {
            throw MasterRuleException::rule('BR-WH-05', 'gudang induk "'.$kode.'" tidak dikenal atau di luar cakupan Anda.');
        }

        return (int) $induk->id;
    }

    private function proyek(mixed $nilai, User $actor): ?int
    {
        $kode = trim((string) $nilai);

        if ($kode === '') {
            return null;
        }

        $proyek = Project::query()->where('code', mb_strtoupper($kode))->first();

        if ($proyek === null || ! $actor->canAccessProject((int) $proyek->id)) {
            throw MasterRuleException::rule('BR-WH-04', 'proyek "'.$kode.'" tidak dikenal atau di luar cakupan Anda.');
        }

        return (int) $proyek->id;
    }

    /** Kepala gudang dari email user aktif; kosong = belum ditunjuk. */
    private function kepala(mixed $nilai): ?int
    {
        $email = mb_strtolower(trim((string) $nilai));

        if ($email === '') {
            return null;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->where('is_active', true)->first();

        if ($user === null) {
            throw MasterRuleException::rule('BR-GEN-11', 'kepala gudang "'.$email.'" bukan user aktif.');
        }

        return (int) $user->id;
    }
}

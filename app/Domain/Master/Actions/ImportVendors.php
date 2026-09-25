<?php

declare(strict_types=1);

namespace App\Domain\Master\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Enums\VendorType;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Support\ExcelRows;
use App\Domain\Master\Support\ImportBatch;
use Illuminate\Http\UploadedFile;

/**
 * Permission: `vendor.create` — impor vendor dari Excel (A-192, A-207). Setiap
 * baris lewat {@see SaveVendor} sehingga aturannya sama dengan form: kode unik
 * (BR-MST-01), jenis vendor (A-52), vendor aktif butuh telepon atau email
 * (A-53). Semua atau tidak sama sekali; hanya menambah, tidak mengubah.
 * Tanpa harga (D-07).
 */
class ImportVendors
{
    public const COLUMNS = [
        'kode' => 'Kode (kosong = dibuat dari nama)',
        'nama' => 'Nama *',
        'jenis' => 'Jenis: company/shop/online_marketplace/individual',
        'status' => 'Status: active/provisional',
        'npwp' => 'NPWP',
        'kontak' => 'Nama kontak',
        'telepon' => 'Telepon',
        'email' => 'Email',
        'alamat' => 'Alamat',
        'termin' => 'Termin (teks)',
    ];

    public const MAX_ROWS = 1000;

    /** Batas panjang kolom tabel `vendors`. */
    private const PANJANG = ['kode' => 30, 'nama' => 150, 'npwp' => 30, 'kontak' => 100, 'telepon' => 20, 'email' => 150, 'termin' => 60];

    public function __construct(private readonly SaveVendor $save) {}

    /** @return int jumlah vendor dibuat */
    public function handle(UploadedFile $file, User $actor): int
    {
        $baris = ExcelRows::read($file, self::COLUMNS, self::MAX_ROWS);

        $dibuat = ImportBatch::run($baris, function (array $r) use ($actor) {
            foreach (self::PANJANG as $kolom => $maks) {
                if (mb_strlen(trim((string) ($r[$kolom] ?? ''))) > $maks) {
                    throw MasterRuleException::rule('BR-GEN-11', $kolom.' lebih dari '.$maks.' karakter.');
                }
            }

            $email = trim((string) ($r['email'] ?? ''));

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw MasterRuleException::rule('BR-GEN-11', 'email "'.$email.'" tidak sah.');
            }

            $this->save->handle(null, [
                'code' => trim((string) ($r['kode'] ?? '')),
                'name' => trim((string) ($r['nama'] ?? '')),
                'vendor_type' => $this->pilihan(VendorType::class, $r['jenis'] ?? null, VendorType::Company, 'jenis'),
                'status' => $this->pilihan(VendorStatus::class, $r['status'] ?? null, VendorStatus::Active, 'status', [VendorStatus::Inactive]),
                'tax_id' => $r['npwp'] ?? null,
                'contact_name' => $r['kontak'] ?? null,
                'phone' => isset($r['telepon']) ? trim((string) $r['telepon']) : null,
                'email' => $email,
                'address' => $r['alamat'] ?? null,
                'payment_terms' => $r['termin'] ?? null,
            ], $actor);
        }, 'vendor');

        activity('master')->causedBy($actor)->withProperties(['jumlah' => $dibuat, 'berkas' => $file->getClientOriginalName()])
            ->log('Impor vendor dari Excel: '.$dibuat.' vendor');

        return $dibuat;
    }

    /**
     * Nilai enum boleh ditulis sebagai kode (`online_marketplace`) atau label UI
     * (`Toko online`).
     *
     * @template T of VendorType|VendorStatus
     *
     * @param  class-string<T>  $enum
     * @param  T  $bawaan
     * @param  list<T>  $dilarang
     * @return T
     */
    private function pilihan(string $enum, mixed $nilai, VendorType|VendorStatus $bawaan, string $kolom, array $dilarang = []): VendorType|VendorStatus
    {
        $isi = is_scalar($nilai) ? mb_strtolower(trim((string) $nilai)) : '';

        if ($isi === '') {
            return $bawaan;
        }

        foreach ($enum::cases() as $kasus) {
            if (! in_array($kasus, $dilarang, true) && ($kasus->value === $isi || mb_strtolower($kasus->label()) === $isi)) {
                return $kasus;
            }
        }

        throw MasterRuleException::rule('BR-GEN-11', $kolom.' "'.$isi.'" tidak dikenal.');
    }
}

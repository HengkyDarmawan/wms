<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Support;

use App\Domain\Warehouse\Actions\DeleteBin;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Support\Facades\DB;

/**
 * K-C (A-362): apakah sebuah bin **pernah dipakai**?
 *
 * Definisi ketat: bin dianggap pernah dipakai bila dirujuk oleh baris mana
 * pun di tabel yang punya foreign key ke `bins` — kartu stok, saldo,
 * reservasi, dokumen, tugas, opname, label kemasan, dan bin utama gabungan.
 * Bin yang tidak dirujuk sama sekali boleh dihapus; sisanya hanya bisa
 * dinonaktifkan (P-03, BR-WH-07).
 *
 * Daftar {@see REFERENSI} + {@see KONFIGURASI} dijaga oleh uji yang membaca
 * skema (`information_schema`): foreign key baru ke `bins` yang tidak ikut
 * didaftarkan di sini membuat uji itu gagal.
 *
 * {@see KONFIGURASI} (A-368): baris pengaturan yang **bukan** pemakaian —
 * Tempat Simpan barang. Bin yang hanya dirujuk di sini tetap boleh dihapus;
 * baris tempat simpannya ikut dilepas oleh {@see DeleteBin}.
 */
class BinUsage
{
    /** @var array<int, array{0: string, 1: string, 2: string}> tabel, kolom, sebutan untuk pesan */
    public const REFERENSI = [
        ['bins', 'occupied_by_bin_id', 'bin tergabung'],
        ['conversion_inputs', 'bin_id', 'konversi'],
        ['conversion_outputs', 'bin_id', 'konversi'],
        ['count_assignments', 'bin_id', 'opname'],
        ['count_lines', 'bin_id', 'opname'],
        ['goods_receipt_lines', 'damaged_bin_id', 'penerimaan'],
        ['goods_receipt_lines', 'receiving_bin_id', 'penerimaan'],
        ['goods_return_lines', 'from_bin_id', 'retur'],
        ['goods_return_lines', 'target_bin_id', 'retur'],
        ['material_issue_lines', 'bin_id', 'pemakaian'],
        ['package_labels', 'bin_id', 'label kemasan'],
        ['package_label_moves', 'from_bin_id', 'label kemasan'],
        ['package_label_moves', 'to_bin_id', 'label kemasan'],
        ['pick_task_lines', 'bin_id', 'picking'],
        ['pick_task_lines', 'suggested_bin_id', 'picking'],
        ['putaway_task_lines', 'bin_id', 'put-away'],
        ['putaway_task_lines', 'from_bin_id', 'put-away'],
        ['putaway_task_lines', 'suggested_bin_id', 'put-away'],
        ['stock_adjustment_lines', 'bin_id', 'penyesuaian'],
        ['stock_balances', 'bin_id', 'saldo stok'],
        ['stock_movements', 'from_bin_id', 'kartu stok'],
        ['stock_movements', 'to_bin_id', 'kartu stok'],
        ['stock_reservations', 'bin_id', 'reservasi'],
        ['storage_dedication_overrides', 'bin_id', 'buka tempat khusus'],
        ['vendor_return_lines', 'bin_id', 'retur vendor'],
        ['waste_disposal_lines', 'bin_id', 'waste'],
        ['waste_disposals', 'target_bin_id', 'waste'],
    ];

    /** @var array<int, array{0: string, 1: string, 2: string}> pengaturan yang ikut dilepas saat bin dihapus */
    public const KONFIGURASI = [
        ['item_storage_locations', 'bin_id', 'tempat simpan'],
    ];

    /** Sebutan pemakaian pertama yang ditemukan, atau null bila belum pernah dipakai. */
    public function dipakaiDi(Bin $bin): ?string
    {
        foreach (self::REFERENSI as [$tabel, $kolom, $sebutan]) {
            if (DB::table($tabel)->where($kolom, $bin->id)->exists()) {
                return $sebutan;
            }
        }

        return null;
    }

    /**
     * Id bin (dari daftar) yang **pernah** dipakai — satu query per referensi,
     * dipakai panel rak untuk menampilkan tombol Hapus hanya bila boleh.
     *
     * @param  array<int, int>  $binIds
     * @return array<int, true>
     */
    public function dipakai(array $binIds): array
    {
        if ($binIds === []) {
            return [];
        }

        $hasil = [];

        foreach (self::REFERENSI as [$tabel, $kolom]) {
            foreach (DB::table($tabel)->whereIn($kolom, $binIds)->distinct()->pluck($kolom) as $id) {
                $hasil[(int) $id] = true;
            }
        }

        return $hasil;
    }
}

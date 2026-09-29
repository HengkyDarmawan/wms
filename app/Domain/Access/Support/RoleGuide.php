<?php

declare(strict_types=1);

namespace App\Domain\Access\Support;

use App\Domain\Access\Models\Role;

/**
 * Penjelasan peran untuk form pengguna "pilih peran dulu" (A-331).
 *
 * Tabel `roles` hanya menyimpan kode dan nama, jadi kalimat penjelasan, jenis
 * pertanyaan cakupan, dan saran unit organisasi dikumpulkan di satu tempat ini.
 * Role buatan company yang tidak dikenal jatuh ke `CUSTOM`: formnya kembali ke
 * baris peran × cakupan yang lama, bukan menebak-nebak.
 */
final class RoleGuide
{
    /** Tidak ada pertanyaan cakupan: otomatis seluruh company. */
    public const SEMUA = 'semua';

    /** Pilih satu atau beberapa gudang tetap (bukan Gudang Site). */
    public const GUDANG = 'gudang';

    /** Pilih satu atau beberapa proyek. */
    public const PROYEK = 'proyek';

    /** Pilih klien, lalu proyek awalnya (periodenya diatur Tim site). */
    public const KLIEN = 'klien';

    /** Peran buatan company: pakai baris penugasan lama. */
    public const CUSTOM = 'custom';

    /** @var array<string, array{0: string, 1: string, 2: string|null}> kode => [penjelasan, pertanyaan, saran unit] */
    private const PANDUAN = [
        'company_admin' => ['Mengatur pengguna, master data, dan seluruh pengaturan company.', self::SEMUA, 'Direksi'],
        'management' => ['Melihat semua data dan menyetujui dokumen yang besar.', self::SEMUA, 'Direksi'],
        'warehouse_head' => ['Mengatur gudang, menyetujui permintaan dan penyesuaian stok.', self::GUDANG, 'Operasional'],
        'warehouse_staff' => ['Menerima, menyimpan, mengambil, dan mengirim barang.', self::GUDANG, 'Operasional'],
        'internal_requester' => ['Meminta material untuk proyeknya.', self::PROYEK, 'Proyek'],
        'pr_follow_up' => ['Mencatat pemesanan dan membuat PO ke vendor.', self::SEMUA, 'Keuangan'],
        'internal_auditor' => ['Memeriksa stok dan opname tanpa mengubah data.', self::SEMUA, 'Audit'],
        'external_auditor' => ['Auditor dari luar company; hanya melihat data yang diizinkan.', self::SEMUA, null],
        'client_user' => ['Melihat proyek kliennya dan mengisi bukti terima lewat portal.', self::KLIEN, null],
        'driver' => ['Role lama yang tidak ditawarkan lagi untuk pengguna baru.', self::SEMUA, null],
    ];

    public static function penjelasan(Role $role): string
    {
        return self::PANDUAN[$role->code][0]
            ?? 'Peran buatan company. Cakupannya diatur sendiri di Pengaturan lanjutan.';
    }

    public static function pertanyaan(Role $role): string
    {
        return self::PANDUAN[$role->code][1] ?? self::CUSTOM;
    }

    /**
     * Saran unit organisasi dari peran (A-332). Hanya saran: dipakai bila ada
     * unit bernama persis itu dan Admin belum memilih sendiri.
     */
    public static function saranUnit(Role $role): ?string
    {
        return self::PANDUAN[$role->code][2] ?? null;
    }

    /** Ikon Bootstrap untuk kartu peran. */
    public static function ikon(Role $role): string
    {
        return match (self::pertanyaan($role)) {
            self::GUDANG => 'bi-building',
            self::PROYEK => 'bi-signpost-split',
            self::KLIEN => 'bi-person-badge',
            self::SEMUA => 'bi-shield-check',
            default => 'bi-person-gear',
        };
    }
}

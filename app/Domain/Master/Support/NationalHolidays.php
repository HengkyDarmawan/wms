<?php

declare(strict_types=1);

namespace App\Domain\Master\Support;

use App\Domain\Master\Enums\HolidayKind;

/**
 * Libur nasional & cuti bersama Indonesia menurut SKB 3 Menteri (Menteri
 * Agama, Menteri Ketenagakerjaan, MenPAN-RB), dibundel di kode supaya tidak
 * bergantung jaringan (A-270). Tambah satu tahun setiap SKB baru terbit
 * (biasanya September–Oktober tahun sebelumnya) lewat pembaruan aplikasi.
 *
 * Sumber: setneg.go.id "Inilah SKB 3 Menteri Libur Nasional dan Cuti Bersama
 * 2026" (SKB No. 1497, 2, 5 Tahun 2025) dan "… 2027"; diperiksa silang dengan
 * hukumonline.com dan detik.com, 27 Sep 2026.
 */
final class NationalHolidays
{
    /** @var array<int, array<int, array{0: string, 1: string, 2: HolidayKind}>> */
    private const DATA = [
        2026 => [
            ['2026-01-01', 'Tahun Baru 2026 Masehi', HolidayKind::National],
            ['2026-01-16', 'Isra Mikraj Nabi Muhammad SAW', HolidayKind::National],
            ['2026-02-16', 'Cuti bersama Tahun Baru Imlek', HolidayKind::JointLeave],
            ['2026-02-17', 'Tahun Baru Imlek 2577 Kongzili', HolidayKind::National],
            ['2026-03-18', 'Cuti bersama Hari Suci Nyepi', HolidayKind::JointLeave],
            ['2026-03-19', 'Hari Suci Nyepi (Tahun Baru Saka 1948)', HolidayKind::National],
            ['2026-03-20', 'Cuti bersama Idulfitri 1447 H', HolidayKind::JointLeave],
            ['2026-03-21', 'Idulfitri 1447 H', HolidayKind::National],
            ['2026-03-22', 'Idulfitri 1447 H', HolidayKind::National],
            ['2026-03-23', 'Cuti bersama Idulfitri 1447 H', HolidayKind::JointLeave],
            ['2026-03-24', 'Cuti bersama Idulfitri 1447 H', HolidayKind::JointLeave],
            ['2026-04-03', 'Wafat Yesus Kristus', HolidayKind::National],
            ['2026-04-05', 'Kebangkitan Yesus Kristus (Paskah)', HolidayKind::National],
            ['2026-05-01', 'Hari Buruh Internasional', HolidayKind::National],
            ['2026-05-14', 'Kenaikan Yesus Kristus', HolidayKind::National],
            ['2026-05-15', 'Cuti bersama Kenaikan Yesus Kristus', HolidayKind::JointLeave],
            ['2026-05-27', 'Iduladha 1447 H', HolidayKind::National],
            ['2026-05-28', 'Cuti bersama Iduladha 1447 H', HolidayKind::JointLeave],
            ['2026-05-31', 'Hari Raya Waisak 2570 BE', HolidayKind::National],
            ['2026-06-01', 'Hari Lahir Pancasila', HolidayKind::National],
            ['2026-06-16', '1 Muharam Tahun Baru Islam 1448 H', HolidayKind::National],
            ['2026-08-17', 'Proklamasi Kemerdekaan', HolidayKind::National],
            ['2026-08-25', 'Maulid Nabi Muhammad SAW', HolidayKind::National],
            ['2026-12-24', 'Cuti bersama Kelahiran Yesus Kristus', HolidayKind::JointLeave],
            ['2026-12-25', 'Kelahiran Yesus Kristus (Natal)', HolidayKind::National],
        ],
        2027 => [
            ['2027-01-01', 'Tahun Baru 2027 Masehi', HolidayKind::National],
            ['2027-01-05', 'Isra Mikraj Nabi Muhammad SAW', HolidayKind::National],
            ['2027-02-05', 'Cuti bersama Tahun Baru Imlek', HolidayKind::JointLeave],
            ['2027-02-06', 'Tahun Baru Imlek 2578 Kongzili', HolidayKind::National],
            ['2027-03-08', 'Hari Suci Nyepi (Tahun Baru Saka 1949)', HolidayKind::National],
            ['2027-03-09', 'Cuti bersama Idulfitri 1448 H', HolidayKind::JointLeave],
            ['2027-03-10', 'Idulfitri 1448 H', HolidayKind::National],
            ['2027-03-11', 'Idulfitri 1448 H', HolidayKind::National],
            ['2027-03-12', 'Cuti bersama Idulfitri 1448 H', HolidayKind::JointLeave],
            ['2027-03-15', 'Cuti bersama Idulfitri 1448 H', HolidayKind::JointLeave],
            ['2027-03-25', 'Cuti bersama Wafat Yesus Kristus', HolidayKind::JointLeave],
            ['2027-03-26', 'Wafat Yesus Kristus', HolidayKind::National],
            ['2027-03-28', 'Kebangkitan Yesus Kristus (Paskah)', HolidayKind::National],
            ['2027-05-01', 'Hari Buruh Internasional', HolidayKind::National],
            ['2027-05-06', 'Kenaikan Yesus Kristus', HolidayKind::National],
            ['2027-05-17', 'Iduladha 1448 H', HolidayKind::National],
            ['2027-05-18', 'Cuti bersama Iduladha 1448 H', HolidayKind::JointLeave],
            ['2027-05-19', 'Cuti bersama Hari Raya Waisak', HolidayKind::JointLeave],
            ['2027-05-20', 'Hari Raya Waisak 2571 BE', HolidayKind::National],
            ['2027-06-01', 'Hari Lahir Pancasila', HolidayKind::National],
            ['2027-06-06', '1 Muharam Tahun Baru Islam 1449 H', HolidayKind::National],
            ['2027-08-15', 'Maulid Nabi Muhammad SAW', HolidayKind::National],
            ['2027-08-17', 'Proklamasi Kemerdekaan', HolidayKind::National],
            ['2027-12-24', 'Cuti bersama Kelahiran Yesus Kristus', HolidayKind::JointLeave],
            ['2027-12-25', 'Kelahiran Yesus Kristus (Natal)', HolidayKind::National],
            ['2027-12-26', 'Isra Mikraj Nabi Muhammad SAW', HolidayKind::National],
        ],
    ];

    /** @return array<int, int> tahun yang datanya tersedia */
    public static function years(): array
    {
        return array_keys(self::DATA);
    }

    public static function has(int $year): bool
    {
        return isset(self::DATA[$year]);
    }

    /** @return array<int, array{date: string, name: string, kind: HolidayKind}> */
    public static function for(int $year): array
    {
        return array_map(fn (array $d) => ['date' => $d[0], 'name' => $d[1], 'kind' => $d[2]], self::DATA[$year] ?? []);
    }
}

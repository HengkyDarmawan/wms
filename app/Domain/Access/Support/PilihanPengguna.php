<?php

declare(strict_types=1);

namespace App\Domain\Access\Support;

use App\Domain\Access\Models\OrgUnit;
use App\Domain\Access\Models\Position;
use App\Domain\Access\Models\User;
use App\Domain\Master\Models\Project;
use App\Domain\Shared\Pilihan\Pilihan;
use Closure;

/**
 * Daftar pilihan form pengguna (10-access §6.3; A-358, A-385, A-386) —
 * dipisah dari `UserForm` supaya berkas komponen tetap di bawah ±450 baris.
 * Tiap daftar adalah satu {@see Pilihan}: dipakai render, pencarian ke
 * server, dan validasi simpan sekaligus.
 */
class PilihanPengguna
{
    public const UNIT_INI = 'Unit ini';

    public const UNIT_INDUK = 'Unit induk';

    public const UNIT_LAIN = 'Unit lain';

    /** @return array<int, string> urutan kelompok Atasan langsung */
    public static function kelompokAtasan(): array
    {
        return [__(self::UNIT_INI), __(self::UNIT_INDUK), __(self::UNIT_LAIN)];
    }

    /**
     * A-386: calon Atasan langsung — pengguna internal aktif selain dirinya
     * (A-345, A-358), berkelompok *Unit ini* → *Unit induk* (yang terdekat
     * dulu, naik sampai akar) → *Unit lain* (termasuk tanpa unit). Tanpa unit
     * terpilih: satu daftar tanpa kelompok.
     */
    public static function atasan(?int $userId, ?int $unitId): Pilihan
    {
        $induk = $unitId === null ? [] : self::indukDari($unitId);
        $urutan = $unitId === null ? [] : [$unitId, ...$induk];

        $query = User::query()->active()->internal()
            ->when($userId !== null, fn ($q) => $q->whereKeyNot($userId))
            ->with(['position:id,name', 'orgUnit:id,name']);

        if ($urutan !== []) {
            $kasus = implode(' ', array_fill(0, count($urutan), 'WHEN users.org_unit_id = ? THEN ?'));
            $ikatan = [];

            foreach ($urutan as $i => $id) {
                array_push($ikatan, $id, $i);
            }

            $query->orderByRaw("CASE {$kasus} ELSE 999 END", $ikatan);
        }

        $query->orderBy('name')->orderBy('id');

        return Pilihan::dari($query, ['name', 'email', 'position.name', 'orgUnit.name'], function (User $u) use ($unitId, $induk): array {
            $opsi = [
                'value' => (int) $u->id,
                'text' => $u->name,
                'badge' => $u->position?->name,
                'sub' => $u->orgUnit?->name,
            ];

            if ($unitId !== null) {
                $opsi['group'] = match (true) {
                    (int) $u->org_unit_id === $unitId => __(self::UNIT_INI),
                    in_array((int) $u->org_unit_id, $induk, true) => __(self::UNIT_INDUK),
                    default => __(self::UNIT_LAIN),
                };
            }

            return $opsi;
        });
    }

    /**
     * A-385: jabatan aktif di unit terpilih; tanpa unit = semua jabatan aktif
     * dikelompokkan per unit.
     */
    public static function jabatan(?int $unitId): Pilihan
    {
        $query = Position::query()->where('positions.is_active', true)
            ->with('orgUnit:id,name')
            ->when($unitId !== null, fn ($q) => $q->where('positions.org_unit_id', $unitId))
            ->when($unitId === null, fn ($q) => $q->leftJoin('org_units', 'org_units.id', '=', 'positions.org_unit_id')
                ->orderBy('org_units.name')->select('positions.*'))
            ->orderBy('positions.level')->orderBy('positions.name');

        return Pilihan::dari($query, ['name', 'code'], fn (Position $p) => array_filter([
            'value' => (int) $p->id,
            'text' => $p->name,
            'group' => $unitId === null ? ($p->orgUnit?->name ?? __('Tanpa unit')) : null,
        ], fn ($v) => $v !== null));
    }

    /**
     * A-358, A-386: isian awal Atasan langsung (±30 teratas + nilai terpilih).
     * Atasan tersimpan yang kini tidak memenuhi syarat tetap tampil paling
     * atas bertanda supaya nilainya tidak tampak hilang.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function opsiAtasan(?int $userId, ?int $unitId, ?int $managerId): array
    {
        $daftar = self::atasan($userId, $unitId)->awalDengan($managerId);

        if ($managerId !== null && ! collect($daftar)->contains('value', $managerId)) {
            $tersimpan = User::query()->with(['position:id,name', 'orgUnit:id,name'])->find($managerId, ['id', 'name', 'position_id', 'org_unit_id', 'is_active']);

            if ($tersimpan !== null) {
                array_unshift($daftar, [
                    'value' => (int) $tersimpan->id,
                    'text' => $tersimpan->name.' ('.($tersimpan->is_active ? __('tidak berlaku') : __('nonaktif')).')',
                    'badge' => $tersimpan->position?->name,
                    'sub' => $tersimpan->orgUnit?->name,
                ]);
            }
        }

        return $daftar;
    }

    /**
     * A-385: jabatan aktif di unit terpilih (tanpa unit: berkelompok per unit);
     * jabatan tersimpan yang kini nonaktif/di unit lain tetap tampil bertanda.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function opsiJabatan(?int $unitId, ?int $positionId): array
    {
        $daftar = self::jabatan($unitId)->semua();

        if ($positionId !== null && ! collect($daftar)->contains('value', $positionId)
            && ($p = Position::query()->find($positionId)) !== null) {
            array_unshift($daftar, ['value' => (int) $p->id, 'text' => $p->name.' ('.($p->is_active ? __('unit lain') : __('nonaktif')).')']);
        }

        return $daftar;
    }

    /**
     * A-385: jabatan harus aktif dan milik unit terpilih — kecuali pasangan
     * unit+jabatan tersimpan yang tidak diubah (data lama tetap bisa disimpan).
     */
    public static function aturanJabatan(?int $userId, ?int $unitId): Closure
    {
        return function (string $atribut, mixed $nilai, Closure $gagal) use ($userId, $unitId): void {
            if ($nilai === null || $nilai === '') {
                return;
            }

            $tersimpan = $userId === null ? null : User::query()->find($userId, ['id', 'org_unit_id', 'position_id']);

            if ($tersimpan !== null && (int) $tersimpan->position_id === (int) $nilai && (int) $tersimpan->org_unit_id === (int) $unitId) {
                return;
            }

            if (! self::jabatan($unitId)->berisi($nilai)) {
                $gagal(__('Pilih jabatan aktif di unit yang dipilih.'));
            }
        };
    }

    /** A-386: atasan harus dari daftar (aktif, internal, bukan dirinya) atau atasan tersimpan. */
    public static function aturanAtasan(?int $userId): Closure
    {
        return function (string $atribut, mixed $nilai, Closure $gagal) use ($userId): void {
            if ($nilai === null || $nilai === '') {
                return;
            }

            $tersimpan = $userId === null ? null : User::query()->whereKey($userId)->value('manager_id');

            if ($tersimpan !== null && (int) $tersimpan === (int) $nilai && (int) $nilai !== (int) $userId) {
                return;
            }

            if (! self::atasan($userId, null)->berisi($nilai)) {
                $gagal(__('Pilih atasan dari daftar: pengguna internal aktif selain dirinya.'));
            }
        };
    }

    /** Unit organisasi aktif (daftar pendek, dimuat sekaligus). */
    public static function unit(): Pilihan
    {
        return Pilihan::dari(OrgUnit::query()->where('is_active', true)->orderBy('name'), ['name', 'code'], fn (OrgUnit $u) => [
            'value' => (int) $u->id,
            'text' => $u->name,
        ]);
    }

    /**
     * Proyek aktif untuk baris cakupan *Peran lain* — sama dengan daftar
     * sebelumnya (semua proyek aktif): pemberi cakupan harus bisa memilih
     * proyek di luar cakupannya sendiri, dan layar ini menuntut `role.assign`.
     */
    public static function proyek(): Pilihan
    {
        return Pilihan::dari(Project::query()->active()->orderBy('code'), ['code', 'name'], fn (Project $p) => [
            'value' => (int) $p->id,
            'text' => $p->code.' — '.$p->name,
        ]);
    }

    /** @return array<int, int> id unit induk, terdekat dulu, sampai akar */
    public static function indukDari(int $unitId): array
    {
        $hasil = [];
        $induk = OrgUnit::query()->whereKey($unitId)->value('parent_id');

        for ($i = 0; $induk !== null && $i < 30 && ! in_array((int) $induk, $hasil, true); $i++) {
            $hasil[] = (int) $induk;
            $induk = OrgUnit::query()->whereKey($induk)->value('parent_id');
        }

        return $hasil;
    }
}

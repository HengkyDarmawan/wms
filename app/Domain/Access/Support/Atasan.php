<?php

declare(strict_types=1);

namespace App\Domain\Access\Support;

use App\Domain\Access\Models\Position;
use App\Domain\Access\Models\User;
use Closure;

/**
 * Atasan langsung **efektif** seorang user (D-16, A-344, A-345) — satu sumber
 * untuk approval "Atasan langsung", pengalihan SoD, eskalasi, Struktur
 * organisasi, dan Peta approval:
 *
 *  1. isian *Atasan langsung* di user (`users.manager_id`) = **penimpaan
 *     manual**, selalu menang bila terisi;
 *  2. selain itu **pemegang jabatan atasan** (`positions.reports_to_position_id`
 *     jabatan user): user internal aktif yang memegang jabatan itu. Bisa lebih
 *     dari satu orang — semuanya calon (lapis "cukup salah satu").
 *
 * Tingkat 2 = atasan efektif dari atasan tingkat 1.
 */
class Atasan
{
    public const MANUAL = 'manual';

    public const JABATAN = 'jabatan';

    /** @var array<int, array<int, int>> */
    private array $cache = [];

    /** @return array<int, int> id atasan tingkat ke-`$tingkat`, urut id */
    public function dari(int $userId, int $tingkat = 1): array
    {
        $lapis = [$userId];

        for ($i = 0; $i < max(1, $tingkat); $i++) {
            $berikut = [];

            foreach ($lapis as $id) {
                $berikut = array_merge($berikut, $this->langsung($id));
            }

            $lapis = array_values(array_unique(array_diff($berikut, [$userId])));

            if ($lapis === []) {
                return [];
            }
        }

        sort($lapis);

        return $lapis;
    }

    /** Satu atasan tingkat 1 (id terkecil) — untuk pengalihan satu orang. */
    public function pertama(int $userId): ?int
    {
        return $this->dari($userId)[0] ?? null;
    }

    /** `manual` | `jabatan` | null — asal atasan efektif, untuk ditampilkan. */
    public function sumber(User $user): ?string
    {
        if ($user->manager_id !== null) {
            return self::MANUAL;
        }

        return $this->langsung((int) $user->id) === [] ? null : self::JABATAN;
    }

    /**
     * A-387: apakah menyimpan user dengan atasan manual `$calonManagerId` dan
     * jabatan `$calonPositionId` membuat **lingkaran atasan efektif** (BFS
     * rantai: manual bila ada, selain itu pemegang jabatan atasan).
     *
     * @param  int|null  $userId  null = user baru
     * @return list<string>|null nama sepanjang lingkaran (dari user ini kembali ke dirinya), null bila aman
     */
    public static function membentukLingkaran(?int $userId, ?int $calonManagerId, ?int $calonPositionId, string $namaUser = '', bool $internalAktif = true): ?array
    {
        $diri = $userId ?? 0;
        $dataUser = self::pembaca();
        // User yang sedang diubah dihitung sebagai pemegang jabatan calonnya (bila internal & aktif).
        $jabatanDiri = $internalAktif ? $calonPositionId : null;

        $langsung = function (int $id) use ($diri, $calonManagerId, $calonPositionId, $dataUser, $jabatanDiri): array {
            [$manager, $jabatan] = $id === $diri ? [$calonManagerId, $calonPositionId] : $dataUser($id);

            if ($manager !== null) {
                return [$manager];
            }

            $atasanJabatan = $jabatan === null ? null : Position::query()->whereKey($jabatan)->value('reports_to_position_id');

            return $atasanJabatan === null ? [] : self::pemegang((int) $atasanJabatan, $id, $diri, $jabatanDiri);
        };

        $jalur = self::naik([$diri], [$diri], $langsung);

        return $jalur === null ? null : self::nama($jalur, [$diri => $namaUser !== '' ? $namaUser : __('pengguna ini')]);
    }

    /**
     * A-387: apakah memasang jabatan `$jabatan` di bawah `$calonAtasanId`
     * membuat lingkaran pada **pemegangnya** (mis. atasan manual pemegang
     * jabatan atasan adalah pemegang jabatan ini).
     *
     * @return list<string>|null
     */
    public static function jabatanMembentukLingkaran(Position $jabatan, ?int $calonAtasanId): ?array
    {
        if ($calonAtasanId === null) {
            return null;
        }

        // Pemegang tanpa atasan manual: atasan efektifnya berasal dari jabatan ini.
        $pemegang = User::query()->active()->internal()->where('position_id', $jabatan->id)->whereNull('manager_id')
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($pemegang === []) {
            return null;
        }

        $dataUser = self::pembaca();

        $langsung = function (int $id) use ($jabatan, $calonAtasanId, $dataUser): array {
            [$manager, $posisi] = $dataUser($id);

            if ($manager !== null) {
                return [$manager];
            }

            $atasanJabatan = match (true) {
                $posisi === null => null,
                $posisi === (int) $jabatan->id => $calonAtasanId,
                default => Position::query()->whereKey($posisi)->value('reports_to_position_id'),
            };

            return $atasanJabatan === null ? [] : self::pemegang((int) $atasanJabatan, $id, null, null);
        };

        $jalur = self::naik($pemegang, $pemegang, $langsung);

        return $jalur === null ? null : self::nama($jalur, []);
    }

    /** @return Closure(int): array{0: ?int, 1: ?int} manager_id & position_id tersimpan */
    private static function pembaca(): Closure
    {
        $cache = [];

        return function (int $id) use (&$cache): array {
            if (! array_key_exists($id, $cache)) {
                $u = User::query()->find($id, ['id', 'manager_id', 'position_id']);
                $cache[$id] = $u === null ? [null, null] : [
                    $u->manager_id === null ? null : (int) $u->manager_id,
                    $u->position_id === null ? null : (int) $u->position_id,
                ];
            }

            return $cache[$id];
        };
    }

    /**
     * Pemegang jabatan (user internal aktif) selain `$kecuali`. User `$diri`
     * yang sedang diubah dihitung dengan jabatan calonnya, bukan yang tersimpan.
     *
     * @return array<int, int>
     */
    private static function pemegang(int $positionId, int $kecuali, ?int $diri, ?int $jabatanCalonDiri): array
    {
        $ids = User::query()->active()->internal()->where('position_id', $positionId)
            ->when($diri !== null, fn ($q) => $q->whereKeyNot($diri))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($diri !== null && $jabatanCalonDiri === $positionId) {
            $ids[] = $diri;
        }

        return array_values(array_diff($ids, [$kecuali]));
    }

    /**
     * BFS naik dari `$mulai`; berhenti saat bertemu salah satu `$tujuan`.
     *
     * @param  array<int, int>  $mulai
     * @param  array<int, int>  $tujuan
     * @param  Closure(int): array<int, int>  $langsung
     * @return list<int>|null
     */
    private static function naik(array $mulai, array $tujuan, Closure $langsung): ?array
    {
        $antre = array_map(fn (int $id) => [$id, [$id]], $mulai);
        $dilihat = array_fill_keys($mulai, true);

        for ($i = 0; $i < count($antre) && $i < 2000; $i++) {
            [$id, $jalur] = $antre[$i];

            foreach ($langsung($id) as $atas) {
                if (in_array($atas, $tujuan, true)) {
                    return [...$jalur, $atas];
                }

                if (! isset($dilihat[$atas])) {
                    $dilihat[$atas] = true;
                    $antre[] = [$atas, [...$jalur, $atas]];
                }
            }
        }

        return null;
    }

    /**
     * @param  list<int>  $jalur
     * @param  array<int, string>  $tambahan
     * @return list<string>
     */
    private static function nama(array $jalur, array $tambahan): array
    {
        $nama = User::query()->whereIn('id', $jalur)->pluck('name', 'id')->all();

        return array_map(fn (int $id) => $tambahan[$id] ?? ($nama[$id] ?? '#'.$id), $jalur);
    }

    /** @return array<int, int> */
    private function langsung(int $userId): array
    {
        if (array_key_exists($userId, $this->cache)) {
            return $this->cache[$userId];
        }

        $user = User::query()->find($userId, ['id', 'manager_id', 'position_id']);

        if ($user === null) {
            return $this->cache[$userId] = [];
        }

        if ($user->manager_id !== null) {
            return $this->cache[$userId] = [(int) $user->manager_id];
        }

        $jabatanAtasan = $user->position_id === null
            ? null
            : Position::query()->whereKey($user->position_id)->value('reports_to_position_id');

        if ($jabatanAtasan === null) {
            return $this->cache[$userId] = [];
        }

        return $this->cache[$userId] = User::query()
            ->where('position_id', $jabatanAtasan)
            ->where('is_active', true)
            ->whereNull('client_id')
            ->whereKeyNot($userId)
            ->orderBy('id')
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Access\Support;

use App\Domain\Access\Models\Position;
use App\Domain\Access\Models\User;

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

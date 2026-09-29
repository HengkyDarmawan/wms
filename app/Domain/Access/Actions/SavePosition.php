<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\OrgUnit;
use App\Domain\Access\Models\Position;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\Atasan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Permission: `org.manage`.
 *
 * Jabatan melekat pada satu unit dan (opsional) melapor ke satu jabatan atasan
 * — peta jabatan (A-344). `level` **dihitung** dari peta itu: tanpa atasan = 1,
 * selain itu level atasan + 1; mengubah atasan ikut menghitung ulang semua
 * jabatan di bawahnya. Level tetap dipakai aturan approval "jabatan X di divisi
 * Y" (Blueprint §8.1). Peta tidak boleh berputar.
 */
class SavePosition
{
    /** Batas kedalaman peta; menjaga hitung ulang dari data rusak. */
    private const MAKS_KEDALAMAN = 30;

    /** @param  array<string, mixed>  $attributes  code, name, reports_to_position_id */
    public function handle(?Position $position, OrgUnit $unit, array $attributes, ?User $actor = null): Position
    {
        $nama = trim((string) ($attributes['name'] ?? ''));

        if ($nama === '') {
            throw AccessRuleException::rule('BR-GEN-11', 'Nama jabatan wajib diisi.');
        }

        $atasan = $this->atasan($position, $attributes['reports_to_position_id'] ?? null);
        $level = $atasan === null ? 1 : $atasan->level + 1;

        // A-387: peta jabatan tidak berputar, tetapi atasan manual pemegangnya bisa
        // membuat lingkaran orang (mis. atasan manual Kepala = Staf yang melapor ke Kepala).
        if ($position !== null && $atasan !== null && (int) $atasan->id !== (int) $position->reports_to_position_id
            && ($jalur = Atasan::jabatanMembentukLingkaran($position, (int) $atasan->id)) !== null) {
            throw AccessRuleException::rule('A-387', 'Atasan jabatan ini membuat lingkaran atasan pada pemegangnya: '
                .implode(' → ', $jalur).'. Ubah dulu atasan manual orang tersebut, atau pilih atasan jabatan lain.');
        }

        return DB::transaction(function () use ($position, $unit, $attributes, $actor, $nama, $atasan, $level): Position {
            if ($position === null) {
                $kode = Str::of((string) ($attributes['code'] ?? $nama))
                    ->upper()->replace(' ', '_')->replaceMatches('/[^A-Z0-9_]/', '')->value();

                if ($kode === '') {
                    throw AccessRuleException::rule('BR-GEN-11', 'Kode jabatan wajib diisi.');
                }

                if (Position::query()->where('code', $kode)->exists()) {
                    throw new AccessRuleException('Kode jabatan "'.$kode.'" sudah dipakai.');
                }

                $position = Position::create([
                    'org_unit_id' => $unit->id,
                    'reports_to_position_id' => $atasan?->id,
                    'code' => $kode,
                    'name' => $nama,
                    'level' => $level,
                    'is_active' => true,
                ]);

                activity('access')->performedOn($position)->causedBy($actor)->log('Jabatan dibuat');

                return $position;
            }

            $position->fill([
                'org_unit_id' => $unit->id,
                'reports_to_position_id' => $atasan?->id,
                'name' => $nama,
                'level' => $level,
            ])->save();

            $this->hitungUlangBawahan($position);

            activity('access')->performedOn($position)->causedBy($actor)->log('Jabatan diubah');

            return $position->refresh();
        });
    }

    public function deactivate(Position $position, ?User $actor = null): Position
    {
        $dipakai = User::query()->where('position_id', $position->id)->where('is_active', true)->count();

        if ($dipakai > 0) {
            throw new AccessRuleException(
                'Jabatan masih dipakai '.$dipakai.' user aktif. Ubah jabatan mereka lebih dulu.',
            );
        }

        $bawahan = Position::query()->where('reports_to_position_id', $position->id)->where('is_active', true)->count();

        if ($bawahan > 0) {
            throw new AccessRuleException(
                'Jabatan ini masih menjadi atasan '.$bawahan.' jabatan aktif. Pindahkan atasan jabatan-jabatan itu lebih dulu.',
            );
        }

        $position->forceFill(['is_active' => false])->save();

        activity('access')->performedOn($position)->causedBy($actor)->log('Jabatan dinonaktifkan');

        return $position->refresh();
    }

    public function reactivate(Position $position, ?User $actor = null): Position
    {
        $position->forceFill(['is_active' => true])->save();

        activity('access')->performedOn($position)->causedBy($actor)->log('Jabatan diaktifkan kembali');

        return $position->refresh();
    }

    /** Jabatan atasan yang sah: aktif, bukan dirinya, dan tidak membuat peta berputar. */
    private function atasan(?Position $position, mixed $id): ?Position
    {
        $id = $id === null || $id === '' ? null : (int) $id;

        if ($id === null) {
            return null;
        }

        $atasan = Position::query()->where('is_active', true)->find($id);

        if ($atasan === null) {
            throw AccessRuleException::rule('BR-GEN-11', 'Jabatan atasan tidak ditemukan atau nonaktif.');
        }

        if ($position === null) {
            return $atasan;
        }

        // Naik dari calon atasan; bila bertemu jabatan ini, petanya berputar.
        $cek = $atasan;

        for ($i = 0; $cek !== null && $i < self::MAKS_KEDALAMAN; $i++) {
            if ((int) $cek->id === (int) $position->id) {
                throw AccessRuleException::rule('A-344', 'Jabatan tidak bisa melapor ke dirinya sendiri atau ke jabatan di bawahnya.');
            }

            $cek = $cek->reports_to_position_id === null ? null : Position::query()->find($cek->reports_to_position_id);
        }

        return $atasan;
    }

    private function hitungUlangBawahan(Position $position, int $kedalaman = 0): void
    {
        if ($kedalaman >= self::MAKS_KEDALAMAN) {
            return;
        }

        foreach (Position::query()->where('reports_to_position_id', $position->id)->get() as $bawahan) {
            $bawahan->forceFill(['level' => $position->level + 1])->save();
            $this->hitungUlangBawahan($bawahan, $kedalaman + 1);
        }
    }
}

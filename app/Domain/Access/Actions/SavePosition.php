<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\OrgUnit;
use App\Domain\Access\Models\Position;
use App\Domain\Access\Models\User;
use Illuminate\Support\Str;

/**
 * Permission: `org.manage`.
 *
 * Jabatan melekat pada satu unit; `level` dipakai aturan approval
 * "jabatan X di divisi Y" (Blueprint §8.1). Level 1 = tertinggi.
 */
class SavePosition
{
    /** @param  array<string, mixed>  $attributes */
    public function handle(?Position $position, OrgUnit $unit, array $attributes, ?User $actor = null): Position
    {
        $nama = trim((string) ($attributes['name'] ?? ''));

        if ($nama === '') {
            throw AccessRuleException::rule('BR-GEN-11', 'Nama jabatan wajib diisi.');
        }

        $level = (int) ($attributes['level'] ?? 1);

        if ($level < 1) {
            throw AccessRuleException::rule('BR-GEN-11', 'Level jabatan minimal 1.');
        }

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
            'name' => $nama,
            'level' => $level,
        ])->save();

        activity('access')->performedOn($position)->causedBy($actor)->log('Jabatan diubah');

        return $position->refresh();
    }

    public function deactivate(Position $position, ?User $actor = null): Position
    {
        $dipakai = User::query()->where('position_id', $position->id)->where('is_active', true)->count();

        if ($dipakai > 0) {
            throw new AccessRuleException(
                'Jabatan masih dipakai '.$dipakai.' user aktif. Ubah jabatan mereka lebih dulu.',
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
}

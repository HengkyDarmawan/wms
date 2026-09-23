<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Exceptions\AccessRuleException;
use App\Domain\Access\Models\OrgUnit;
use App\Domain\Access\Models\User;
use Illuminate\Support\Str;

/**
 * Permission: `org.manage`.
 *
 * Unit organisasi dipakai aturan approval "atasan langsung" dan "jabatan X di
 * unit Y" (D-16, Blueprint §8.1), jadi hierarkinya harus tetap berupa pohon.
 */
class SaveOrgUnit
{
    /** @param  array<string, mixed>  $attributes */
    public function handle(?OrgUnit $unit, array $attributes, ?User $actor = null): OrgUnit
    {
        $nama = trim((string) ($attributes['name'] ?? ''));

        if ($nama === '') {
            throw AccessRuleException::rule('BR-GEN-11', 'Nama unit wajib diisi.');
        }

        $parentId = $attributes['parent_id'] ?? null;
        $parentId = $parentId !== null && $parentId !== '' ? (int) $parentId : null;

        if ($unit !== null) {
            $this->guardHierarchy($unit, $parentId);
        }

        if ($unit === null) {
            $kode = $this->normalkanKode((string) ($attributes['code'] ?? $nama));

            if (OrgUnit::query()->where('code', $kode)->exists()) {
                throw new AccessRuleException('Kode unit "'.$kode.'" sudah dipakai.');
            }

            $unit = OrgUnit::create([
                'code' => $kode,
                'name' => $nama,
                'parent_id' => $parentId,
                'is_active' => true,
            ]);

            activity('access')->performedOn($unit)->causedBy($actor)->log('Unit organisasi dibuat');

            return $unit;
        }

        $unit->fill(['name' => $nama, 'parent_id' => $parentId])->save();

        activity('access')->performedOn($unit)->causedBy($actor)->log('Unit organisasi diubah');

        return $unit->refresh();
    }

    private function normalkanKode(string $kode): string
    {
        $hasil = Str::of($kode)->upper()->replace(' ', '_')->replaceMatches('/[^A-Z0-9_]/', '')->value();

        if ($hasil === '') {
            throw AccessRuleException::rule('BR-GEN-11', 'Kode unit wajib diisi.');
        }

        return $hasil;
    }

    /** Induk tidak boleh dirinya sendiri atau turunannya (mencegah lingkaran). */
    private function guardHierarchy(OrgUnit $unit, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($parentId === $unit->id) {
            throw new AccessRuleException('Unit tidak bisa menjadi induk dirinya sendiri.');
        }

        $calon = OrgUnit::find($parentId);

        while ($calon !== null) {
            if ($calon->id === $unit->id) {
                throw new AccessRuleException('Induk tidak boleh unit turunannya sendiri.');
            }

            $calon = $calon->parent_id !== null ? OrgUnit::find($calon->parent_id) : null;
        }
    }
}

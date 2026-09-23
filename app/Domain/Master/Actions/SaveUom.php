<?php

declare(strict_types=1);

namespace App\Domain\Master\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Models\UomCategory;
use App\Domain\Master\Support\MasterCode;

/**
 * Permission: `uom.manage`.
 *
 * D-11: konversi hanya sah di dalam satu kategori. `factor_to_reference`
 * menyatakan berapa satuan acuan yang setara dengan 1 satuan ini.
 * BR-MST-03: satuan acuan kategori selalu berfaktor 1 dan tidak bisa diubah.
 */
class SaveUom
{
    /** @param  array<string, mixed>  $attributes */
    public function handle(?Uom $uom, array $attributes, ?User $actor = null): Uom
    {
        $baru = $uom === null || ! $uom->exists;

        $nama = trim((string) ($attributes['name'] ?? ''));

        if ($nama === '') {
            throw MasterRuleException::rule('BR-GEN-11', 'Nama satuan wajib diisi.');
        }

        $kode = MasterCode::resolve($uom, (string) ($attributes['code'] ?? ''), 'satuan');

        $bentrok = Uom::query()->where('code', $kode)
            ->when(! $baru, fn ($q) => $q->whereKeyNot($uom->getKey()))
            ->exists();

        if ($bentrok) {
            throw MasterRuleException::rule('BR-MST-01', 'Kode satuan "'.$kode.'" sudah dipakai.');
        }

        $kategoriId = $this->idAtauNull($attributes['uom_category_id'] ?? null) ?? ($baru ? null : (int) $uom->uom_category_id);

        if ($kategoriId === null) {
            throw MasterRuleException::fields(['uom_category_id' => 'Kategori satuan wajib dipilih.'], 'BR-GEN-11');
        }

        $kategori = UomCategory::query()->find($kategoriId);

        if ($kategori === null) {
            throw MasterRuleException::fields(['uom_category_id' => 'Kategori satuan tidak ditemukan.'], 'BR-GEN-11');
        }

        // Memindahkan satuan ke kategori lain merusak konversi yang sudah dipakai.
        if (! $baru && (int) $uom->uom_category_id !== $kategoriId) {
            throw MasterRuleException::fields(
                ['uom_category_id' => 'Kategori satuan tidak bisa dipindah setelah satuan dibuat.'],
                'BR-MST-01',
            );
        }

        $adalahAcuan = ! $baru && (int) $kategori->reference_uom_id === (int) $uom->getKey();
        $faktor = $this->angkaAtauNull($attributes['factor_to_reference'] ?? null);

        if ($adalahAcuan) {
            $faktor = 1.0;
        }

        if ($faktor === null || $faktor <= 0) {
            throw MasterRuleException::fields(
                ['factor_to_reference' => 'Faktor terhadap satuan acuan wajib diisi dan lebih besar dari nol.'],
                'BR-MST-03',
            );
        }

        $data = [
            'uom_category_id' => $kategoriId,
            'code' => $kode,
            'name' => $nama,
            'factor_to_reference' => $faktor,
            'rounding' => $this->angkaAtauNull($attributes['rounding'] ?? null),
        ];

        if ($baru) {
            $uom = Uom::create($data + ['is_active' => true]);
        } else {
            $uom->fill($data)->save();
        }

        activity('master')
            ->performedOn($uom)
            ->causedBy($actor)
            ->log($baru ? 'Satuan dibuat' : 'Satuan diubah');

        return $uom->refresh();
    }

    /** P-03: satuan tidak dihapus; satuan acuan kategori tidak boleh dinonaktifkan. */
    public function deactivate(Uom $uom, ?User $actor = null): Uom
    {
        if ($uom->isReference()) {
            throw MasterRuleException::rule(
                'BR-MST-03',
                'Satuan acuan kategori tidak bisa dinonaktifkan. Tetapkan satuan acuan lain dulu.',
            );
        }

        $uom->forceFill(['is_active' => false])->save();

        activity('master')->performedOn($uom)->causedBy($actor)->log('Satuan dinonaktifkan');

        return $uom->refresh();
    }

    public function reactivate(Uom $uom, ?User $actor = null): Uom
    {
        $uom->forceFill(['is_active' => true])->save();

        activity('master')->performedOn($uom)->causedBy($actor)->log('Satuan diaktifkan kembali');

        return $uom->refresh();
    }

    private function angkaAtauNull(mixed $value): ?float
    {
        $teks = trim((string) ($value ?? ''));

        return $teks === '' ? null : (float) str_replace(',', '.', $teks);
    }

    private function idAtauNull(mixed $value): ?int
    {
        return ($value === '' || $value === null) ? null : (int) $value;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Master\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Models\UomCategory;
use App\Domain\Master\Support\MasterCode;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `uom.manage`.
 *
 * BR-MST-03: setiap kategori satuan wajib punya satu satuan acuan ber-faktor 1.
 * Kategori baru boleh dibuat bersama satuan acuannya dalam satu langkah, karena
 * kategori tanpa acuan tidak berguna.
 */
class SaveUomCategory
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $referenceUom  kode & nama satuan acuan saat kategori baru
     */
    public function handle(
        ?UomCategory $category,
        array $attributes,
        ?array $referenceUom = null,
        ?User $actor = null,
    ): UomCategory {
        $baru = $category === null || ! $category->exists;

        $nama = trim((string) ($attributes['name'] ?? ''));

        if ($nama === '') {
            throw MasterRuleException::rule('BR-GEN-11', 'Nama kategori satuan wajib diisi.');
        }

        $kode = MasterCode::resolve($category, (string) ($attributes['code'] ?? ''), 'kategori satuan');

        $bentrok = UomCategory::query()->where('code', $kode)
            ->when(! $baru, fn ($q) => $q->whereKeyNot($category->getKey()))
            ->exists();

        if ($bentrok) {
            throw MasterRuleException::rule('BR-MST-01', 'Kode kategori satuan "'.$kode.'" sudah dipakai.');
        }

        $acuanId = $this->idAtauNull($attributes['reference_uom_id'] ?? null);

        if ($baru && $acuanId === null && $referenceUom === null) {
            throw MasterRuleException::fields(
                ['reference_uom_id' => 'Kategori satuan wajib punya satuan acuan (BR-MST-03).'],
                'BR-MST-03',
            );
        }

        $category = DB::transaction(function () use ($category, $baru, $kode, $nama, $acuanId, $referenceUom): UomCategory {
            if ($baru) {
                $category = UomCategory::create([
                    'code' => $kode,
                    'name' => $nama,
                    'is_active' => true,
                ]);
            } else {
                $category->fill(['code' => $kode, 'name' => $nama])->save();
            }

            if ($referenceUom !== null) {
                $acuanId = $this->buatSatuanAcuan($category, $referenceUom)->id;
            }

            if ($acuanId !== null) {
                $this->pastikanAcuanSah($category, $acuanId);
                $category->forceFill(['reference_uom_id' => $acuanId])->save();
            }

            return $category;
        });

        activity('master')
            ->performedOn($category)
            ->causedBy($actor)
            ->log($baru ? 'Kategori satuan dibuat' : 'Kategori satuan diubah');

        return $category->refresh();
    }

    /** @param  array<string, mixed>  $referenceUom */
    private function buatSatuanAcuan(UomCategory $category, array $referenceUom): Uom
    {
        $kode = MasterCode::normalize((string) ($referenceUom['code'] ?? ''));
        $nama = trim((string) ($referenceUom['name'] ?? ''));

        if ($kode === '' || $nama === '') {
            throw MasterRuleException::fields(
                ['reference_uom_id' => 'Kode dan nama satuan acuan wajib diisi.'],
                'BR-MST-03',
            );
        }

        $adaSebelumnya = Uom::query()->where('code', $kode)->first();

        // `uoms.code` unik global. Tanpa penjagaan ini, membuat kategori baru
        // dengan kode acuan yang sudah dipakai akan MEMINDAHKAN satuan itu ke
        // kategori baru dan memaksa faktornya menjadi 1 — merusak seluruh
        // konversi yang memakainya (bandingkan larangan pindah kategori di SaveUom).
        if ($adaSebelumnya !== null && (int) $adaSebelumnya->uom_category_id !== (int) $category->id) {
            throw MasterRuleException::fields(
                ['reference_uom_id' => 'Kode satuan "'.$kode.'" sudah dipakai kategori lain.'],
                'BR-MST-01',
            );
        }

        return Uom::query()->updateOrCreate(
            ['code' => $kode],
            [
                'uom_category_id' => $category->id,
                'name' => $nama,
                'factor_to_reference' => 1,
                'is_active' => true,
            ],
        );
    }

    /** BR-MST-03: acuan harus satuan dalam kategori itu sendiri dan berfaktor 1. */
    private function pastikanAcuanSah(UomCategory $category, int $uomId): void
    {
        $uom = Uom::query()->find($uomId);

        if ($uom === null || (int) $uom->uom_category_id !== (int) $category->id) {
            throw MasterRuleException::fields(
                ['reference_uom_id' => 'Satuan acuan harus berasal dari kategori ini.'],
                'BR-MST-03',
            );
        }

        if ((float) $uom->factor_to_reference !== 1.0) {
            $uom->forceFill(['factor_to_reference' => 1])->save();
        }
    }

    private function idAtauNull(mixed $value): ?int
    {
        return ($value === '' || $value === null) ? null : (int) $value;
    }
}

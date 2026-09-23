<?php

declare(strict_types=1);

namespace App\Domain\Master\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\RemovalStrategy;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\ItemCategory;
use App\Domain\Master\Support\EnumInput;
use App\Domain\Master\Support\MasterCode;

/**
 * Permission: `item_category.manage`. Kategori mewariskan kategori penyimpanan,
 * strategi pengambilan, dan ambang toleransi opname (BR-OPN-04).
 */
class SaveItemCategory
{
    /** @param  array<string, mixed>  $attributes */
    public function handle(?ItemCategory $category, array $attributes, ?User $actor = null): ItemCategory
    {
        $baru = $category === null || ! $category->exists;

        $nama = trim((string) ($attributes['name'] ?? ''));

        if ($nama === '') {
            throw MasterRuleException::rule('BR-GEN-11', 'Nama kategori wajib diisi.');
        }

        $kode = MasterCode::resolve($category, (string) ($attributes['code'] ?? ''), 'kategori');

        $bentrok = ItemCategory::query()->where('code', $kode)
            ->when(! $baru, fn ($q) => $q->whereKeyNot($category->getKey()))
            ->exists();

        if ($bentrok) {
            throw MasterRuleException::rule('BR-MST-01', 'Kode kategori "'.$kode.'" sudah dipakai.');
        }

        $parentId = $this->idAtauNull($attributes['parent_id'] ?? null);

        if (! $baru && $parentId !== null) {
            $this->tolakLingkaran($category, $parentId);
        }

        $strategi = EnumInput::optional(RemovalStrategy::class, $attributes['removal_strategy'] ?? null, 'removal_strategy');

        $data = [
            'code' => $kode,
            'name' => $nama,
            'parent_id' => $parentId,
            'storage_category_id' => $this->idAtauNull($attributes['storage_category_id'] ?? null),
            'removal_strategy' => $strategi,
            'tolerance_pct' => $this->angkaAtauNull($attributes['tolerance_pct'] ?? null),
            'tolerance_abs' => $this->angkaAtauNull($attributes['tolerance_abs'] ?? null),
            'abc_class' => $this->kosongJadiNull($attributes['abc_class'] ?? null),
        ];

        if ($baru) {
            $category = ItemCategory::create($data + ['is_active' => true]);
        } else {
            $category->fill($data)->save();
        }

        activity('master')
            ->performedOn($category)
            ->causedBy($actor)
            ->log($baru ? 'Kategori item dibuat' : 'Kategori item diubah');

        return $category->refresh();
    }

    /** Kategori tidak boleh menjadi induk dirinya sendiri, langsung maupun berantai. */
    private function tolakLingkaran(ItemCategory $category, int $parentId): void
    {
        $id = $parentId;
        $batas = 0;

        while ($id !== null && $batas < 20) {
            if ($id === (int) $category->getKey()) {
                throw MasterRuleException::rule('BR-MST-01', 'Kategori tidak boleh menjadi induk dirinya sendiri.');
            }

            $indukId = ItemCategory::query()->whereKey($id)->value('parent_id');
            $id = $indukId === null ? null : (int) $indukId;
            $batas++;
        }
    }

    private function kosongJadiNull(mixed $value): ?string
    {
        $teks = trim((string) ($value ?? ''));

        return $teks === '' ? null : $teks;
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

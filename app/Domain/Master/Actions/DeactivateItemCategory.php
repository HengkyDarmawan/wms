<?php

declare(strict_types=1);

namespace App\Domain\Master\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\ItemCategory;

/**
 * Permission: `item_category.manage`.
 * BR-MST-05: kategori yang masih memuat item aktif tidak bisa dinonaktifkan.
 */
class DeactivateItemCategory
{
    public function handle(ItemCategory $category, string $reasonCode, ?string $notes = null, ?User $actor = null): ItemCategory
    {
        if (trim($reasonCode) === '') {
            throw MasterRuleException::rule('BR-GEN-11', 'Alasan wajib dipilih.');
        }

        $item = $category->items()->where('status', ItemStatus::Active->value)->count();

        if ($item > 0) {
            throw MasterRuleException::rule(
                'BR-MST-05',
                'Kategori masih memuat '.$item.' item aktif. Pindahkan atau nonaktifkan itemnya dulu.',
            );
        }

        $anak = $category->children()->where('is_active', true)->count();

        if ($anak > 0) {
            throw MasterRuleException::rule('BR-MST-05', 'Kategori masih punya '.$anak.' sub-kategori aktif.');
        }

        $category->forceFill(['is_active' => false])->save();

        activity('master')
            ->performedOn($category)
            ->causedBy($actor)
            ->withProperties(['reason_code' => $reasonCode, 'notes' => $notes])
            ->log('Kategori item dinonaktifkan');

        return $category->refresh();
    }

    public function reactivate(ItemCategory $category, ?User $actor = null): ItemCategory
    {
        $category->forceFill(['is_active' => true])->save();

        activity('master')
            ->performedOn($category)
            ->causedBy($actor)
            ->log('Kategori item diaktifkan kembali');

        return $category->refresh();
    }
}

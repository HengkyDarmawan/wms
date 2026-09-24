<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Support;

use App\Domain\Master\Models\FeatureSetting;
use App\Domain\Master\Models\Item;
use App\Domain\Receipt\Enums\ReceiptType;

/**
 * Apakah baris penerimaan wajib QC? (BR-GRN-01, P-08)
 *
 * Dua lapis, seperti fitur stok lainnya: company menyalakan saklar `qc`, lalu
 * item menentukan apakah QC berlaku untuknya (`items.requires_qc`, Blueprint
 * §6.4). QC hanya untuk barang dari vendor — transfer antar gudang adalah
 * barang company sendiri yang sudah pernah lolos QC saat pertama masuk (A-79).
 */
class QcRequirement
{
    public const FEATURE = 'qc';

    public function required(Item $item, ReceiptType $type): bool
    {
        if ($type !== ReceiptType::Vendor) {
            return false;
        }

        return (bool) $item->requires_qc && FeatureSetting::enabled(self::FEATURE);
    }
}

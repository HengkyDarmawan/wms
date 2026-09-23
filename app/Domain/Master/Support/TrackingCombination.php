<?php

declare(strict_types=1);

namespace App\Domain\Master\Support;

use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Enums\RemovalStrategy;
use App\Domain\Master\Enums\TrackingMode;

/**
 * Satu tempat untuk memeriksa kombinasi pelacakan item — BR-STK-08, BR-STK-09,
 * BR-STK-11, BR-STK-12, dan matriks kombinasi di Aturan Bisnis §15.
 *
 * Dipisah dari model dan layar agar aturannya bisa diuji langsung.
 */
class TrackingCombination
{
    /**
     * Mengembalikan daftar pelanggaran; kosong berarti kombinasinya sah.
     *
     * @param  bool|null  $baseUomIsLength  null = belum diketahui (tidak diperiksa)
     * @return array<string, string> field => pesan
     */
    public function violations(
        TrackingMode $trackingMode,
        OwnershipModel $ownershipModel,
        ?RemovalStrategy $removalStrategy,
        bool $hasExpiry,
        bool $isCuttable = false,
        ?float $minOffcutLength = null,
        ?bool $baseUomIsLength = null,
    ): array {
        $masalah = [];

        // BR-STK-08: aset wajib serial.
        if (! in_array($ownershipModel, $trackingMode->allowedOwnershipModels(), true)) {
            $masalah['ownership_model'] = $ownershipModel === OwnershipModel::Consumable
                ? 'Model kepemilikan ini tidak cocok dengan mode pelacakan '.$trackingMode->label().'.'
                : 'Aset dipinjamkan wajib memakai mode pelacakan Serial number (BR-STK-08).';
        }

        // BR-STK-11: strategi pengambilan harus ada di matriks.
        if ($removalStrategy !== null
            && ! in_array($removalStrategy, $trackingMode->allowedRemovalStrategies(), true)) {
            $masalah['removal_strategy'] = 'Strategi '.$removalStrategy->label().
                ' tidak berlaku untuk mode pelacakan '.$trackingMode->label().' (matriks kombinasi).';
        }

        // BR-STK-12: kedaluwarsa hanya lot/serial, dan FEFO menuntut kedaluwarsa.
        if ($hasExpiry && ! $trackingMode->allowsExpiry()) {
            $masalah['has_expiry'] = 'Tanggal kedaluwarsa hanya untuk mode Batch/Lot atau Serial number.';
        }

        if ($removalStrategy?->requiresExpiry() && ! $hasExpiry) {
            $masalah['has_expiry'] = 'Strategi FEFO menuntut tanggal kedaluwarsa diaktifkan (BR-STK-12).';
        }

        // BR-STK-09: item per potong memakai satuan dasar berkategori panjang.
        if ($trackingMode->requiresLengthBaseUom() && $baseUomIsLength === false) {
            $masalah['base_uom_id'] = 'Item per potong memakai satuan dasar berkategori panjang (BR-STK-09).';
        }

        // BR-CNV-03: panjang minimum offcut wajib untuk item yang bisa dipotong.
        if ($isCuttable && ($minOffcutLength === null || $minOffcutLength <= 0)) {
            $masalah['min_offcut_length'] = 'Panjang minimum offcut wajib diisi untuk item yang bisa dipotong (BR-CNV-03).';
        }

        return $masalah;
    }

    public function isValid(
        TrackingMode $trackingMode,
        OwnershipModel $ownershipModel,
        ?RemovalStrategy $removalStrategy,
        bool $hasExpiry,
        bool $isCuttable = false,
        ?float $minOffcutLength = null,
        ?bool $baseUomIsLength = null,
    ): bool {
        return $this->violations(
            $trackingMode, $ownershipModel, $removalStrategy,
            $hasExpiry, $isCuttable, $minOffcutLength, $baseUomIsLength,
        ) === [];
    }
}

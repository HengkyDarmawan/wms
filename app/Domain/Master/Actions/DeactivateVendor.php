<?php

declare(strict_types=1);

namespace App\Domain\Master\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\VendorStatus;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\Vendor;

/**
 * Permission: `vendor.deactivate`. P-03: vendor tidak dihapus, hanya
 * dinonaktifkan; riwayat penerimaan yang menunjuknya tetap utuh.
 */
class DeactivateVendor
{
    public function handle(Vendor $vendor, string $reasonCode, ?string $notes = null, ?User $actor = null): Vendor
    {
        if (trim($reasonCode) === '') {
            throw MasterRuleException::rule('BR-GEN-11', 'Alasan wajib dipilih.');
        }

        $vendor->forceFill([
            'status' => VendorStatus::Inactive,
            'is_active' => false,
        ])->save();

        activity('master')
            ->performedOn($vendor)
            ->causedBy($actor)
            ->withProperties(['reason_code' => $reasonCode, 'notes' => $notes])
            ->log('Vendor dinonaktifkan');

        return $vendor->refresh();
    }

    public function reactivate(Vendor $vendor, ?User $actor = null): Vendor
    {
        $vendor->forceFill([
            'status' => VendorStatus::Active,
            'is_active' => true,
        ])->save();

        activity('master')
            ->performedOn($vendor)
            ->causedBy($actor)
            ->log('Vendor diaktifkan kembali');

        return $vendor->refresh();
    }
}

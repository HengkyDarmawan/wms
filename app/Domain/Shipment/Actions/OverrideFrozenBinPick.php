<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Shipment\Enums\PickTaskStatus;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Warehouse\Enums\BinStatus;

/**
 * Permission: `bin.manage` — override Kepala Gudang untuk SJ mendesak
 * (BR-OPN-02, A-240): PCK yang masih `pending` boleh mengambil dari bin yang
 * sedang dibeku opname. Alasan wajib (BR-GEN-11). Tidak mengubah status PCK
 * maupun stok; pergerakannya tetap lewat penyelesaian PCK, yang lalu
 * menggeser angka sesi opname dan menandai bin untuk dihitung ulang
 * (`FrozenBinPickShift`).
 */
class OverrideFrozenBinPick
{
    public function handle(PickTask $task, string $reason, ?User $actor = null): PickTask
    {
        if ($task->status !== PickTaskStatus::Pending) {
            throw ShipmentRuleException::rule('BR-OPN-02', 'Override hanya untuk tugas picking yang belum dimulai.');
        }

        $alasan = trim($reason);

        if ($alasan === '') {
            throw ShipmentRuleException::field('BR-GEN-11', 'reason', 'Alasan override wajib diisi.');
        }

        $beku = $task->lines()->with(['bin' => fn ($q) => $q->withoutGlobalScopes()])->get()
            ->map(fn ($l) => $l->bin)
            ->filter(fn ($b) => $b?->bin_status === BinStatus::Frozen)
            ->unique('id');

        if ($beku->isEmpty()) {
            throw ShipmentRuleException::rule('BR-OPN-02', 'Tidak ada bin beku di tugas ini; override tidak diperlukan.');
        }

        $task->forceFill([
            'freeze_override_by' => $actor?->id,
            'freeze_override_at' => now(),
            'freeze_override_reason' => mb_substr($alasan, 0, 255),
        ])->save();

        activity('shipment')->performedOn($task)->causedBy($actor)
            ->withProperties(['bins' => $beku->pluck('code')->values()->all(), 'reason' => $alasan])
            ->log('Override bin beku untuk SJ mendesak: '.$beku->pluck('code')->implode(', '));

        return $task->refresh();
    }
}

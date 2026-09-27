<?php

declare(strict_types=1);

namespace App\Domain\Label\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Label\Enums\PackageLabelStatus;
use App\Domain\Label\Exceptions\LabelRuleException;
use App\Domain\Label\Models\PackageLabel;
use App\Domain\Label\Support\PackageLabelLedger;
use App\Domain\Master\Models\ReasonCode;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `adjustment.approve` (Kepala Gudang, A-302) — membatalkan label
 * Di gudang (salah cetak, rusak, atau label yatim setelah stok keluar lewat
 * penyesuaian). Stok tidak disentuh (P-01) dan label tidak dihapus (P-03).
 * Membatalkan induk ikut membatalkan label isinya yang masih Di gudang.
 */
class CancelLabel
{
    public function __construct(private readonly PackageLabelLedger $ledger) {}

    public function handle(PackageLabel $label, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): PackageLabel
    {
        if ($reasonCodeId === null || ! ReasonCode::query()->whereKey($reasonCodeId)->exists()) {
            throw LabelRuleException::field('BR-GEN-11', 'reason', 'Alasan pembatalan label wajib dipilih.');
        }

        if ($label->status !== PackageLabelStatus::InStock) {
            throw LabelRuleException::rule('BR-LBL-05', 'Hanya label Di gudang yang bisa dibatalkan.');
        }

        return DB::transaction(function () use ($label, $reasonCodeId, $notes, $actor) {
            $daftar = collect([$label])->merge($label->isParent() ? $label->children()->inStock()->get() : []);

            foreach ($daftar as $l) {
                $l = PackageLabel::query()->lockForUpdate()->findOrFail($l->id);
                $sisa = (float) $l->qty_remaining;

                $l->forceFill([
                    'status' => PackageLabelStatus::Cancelled,
                    'qty_remaining' => 0,
                    'cancel_reason_id' => $reasonCodeId,
                    'cancelled_at' => now(),
                    'cancelled_by' => $actor?->id,
                ])->save();

                $this->ledger->catat($l, -$sisa, ['type' => null, 'id' => null, 'line_id' => null, 'number' => null], $actor,
                    ['warehouse_id' => $l->warehouse_id, 'reason_code_id' => $reasonCodeId, 'notes' => $notes !== null && trim($notes) !== '' ? mb_substr(trim($notes), 0, 255) : null]);
            }

            $this->ledger->segarkanInduk($label->parent_id);

            return $label->refresh();
        });
    }
}

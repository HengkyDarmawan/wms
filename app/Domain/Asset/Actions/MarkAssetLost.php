<?php

declare(strict_types=1);

namespace App\Domain\Asset\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Adjustment\Actions\CreateStockAdjustment;
use App\Domain\Adjustment\Enums\AdjustmentOrigin;
use App\Domain\Adjustment\Enums\StockAdjustmentStatus;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Adjustment\Models\StockAdjustmentLine;
use App\Domain\Asset\Enums\AssetHandoverStatus;
use App\Domain\Asset\Exceptions\AssetRuleException;
use App\Domain\Asset\Models\AssetHandover;
use App\Domain\Asset\Support\AssetStateSync;
use App\Domain\Master\Enums\AssetState;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Serial;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Support\StockLedger;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `asset.mark_lost` — BR-AST-04: aset ditandai hilang dengan
 * Alasan `*` (konteks kehilangan) → state `lost` → kejadian
 * `asset_lost_or_damaged` tanpa pergerakan → ADJ asal `asset_lost` keluar
 * lewat approval → saat diposting aset `written_off` (A-167).
 *
 * `found()` membatalkan tanda hilang selama ADJ-nya belum diposting (ADJ
 * ditolak atau dibatalkan dulu); state dihitung ulang dari lokasinya.
 */
class MarkAssetLost
{
    public function __construct(
        private readonly CreateStockAdjustment $adj,
        private readonly StockLedger $ledger,
        private readonly AssetStateSync $sync,
    ) {}

    public function handle(Serial $serial, mixed $reasonCodeId, ?string $notes = null, ?User $actor = null): StockAdjustment
    {
        if (! $serial->item?->isAsset()) {
            throw AssetRuleException::rule('BR-STK-08', 'Serial '.$serial->serial_no.' bukan aset.');
        }

        if (in_array($serial->asset_state, [AssetState::Lost, AssetState::WrittenOff], true)) {
            throw AssetRuleException::rule('BR-AST-04', 'Aset '.$serial->serial_no.' sudah '.$serial->asset_state->label().'.');
        }

        $alasan = is_numeric($reasonCodeId) ? (int) $reasonCodeId : 0;

        if ($alasan <= 0 || ! ReasonCode::query()->whereKey($alasan)->where('context', ReasonContext::Lost->value)->exists()) {
            throw AssetRuleException::field('BR-GEN-11', 'reason', 'Alasan kehilangan wajib dipilih.');
        }

        $saldo = StockBalance::query()->where('serial_id', $serial->id)->where('qty_base', '>', 0)->first();

        if ($saldo === null) {
            throw AssetRuleException::rule('BR-AST-04', 'Aset '.$serial->serial_no.' tidak tercatat di bin mana pun.');
        }

        $bin = Bin::query()->withoutGlobalScopes()->findOrFail($saldo->bin_id);

        if ($bin->bin_type === BinType::InTransit) {
            throw AssetRuleException::rule('BR-SJ-10', 'Aset dalam perjalanan yang hilang diselesaikan lewat selisih pengiriman (DSC), bukan tanda hilang.');
        }

        // Cakupan: gudang tempat aset berada, atau gudang yang meminjamkannya.
        $asalAst = AssetHandover::query()->withoutGlobalScopes()->where('serial_id', $serial->id)
            ->where('status', AssetHandoverStatus::CheckedOut->value)->latest('id')->value('warehouse_id');

        if ($actor !== null && ! $actor->canAccessWarehouse((int) $bin->warehouse_id)
            && ($asalAst === null || ! $actor->canAccessWarehouse((int) $asalAst))) {
            throw AssetRuleException::rule('BR-ACC-05', 'Lokasi aset '.$serial->serial_no.' di luar cakupan Anda.');
        }

        $ket = $notes !== null && trim($notes) !== '' ? mb_substr(trim($notes), 0, 255) : null;

        return DB::transaction(function () use ($serial, $saldo, $bin, $alasan, $ket, $actor) {
            $ast = AssetHandover::query()->withoutGlobalScopes()
                ->where('serial_id', $serial->id)
                ->where('status', AssetHandoverStatus::CheckedOut->value)
                ->latest('id')->first();

            $serial->forceFill(['asset_state' => AssetState::Lost])->save();

            $this->ledger->emitEvent(StockEventType::AssetLostOrDamaged, array_filter([
                'kind' => 'lost',
                'serial_id' => (int) $serial->id,
                'serial_no' => $serial->serial_no,
                'item_id' => (int) $serial->item_id,
                'bin_id' => (int) $bin->id,
                'handover_number' => $ast?->number,
                'reason_code_id' => $alasan,
            ], fn ($v) => $v !== null), 'asset', (int) $serial->id, $serial->serial_no, $ast?->project_id ?? $bin->project_id);

            $adj = $this->adj->forLostAsset($serial, $saldo, $alasan, $ket, $actor);

            $ast?->forceFill([
                'lost_at' => now(),
                'lost_reason_id' => $alasan,
                'stock_adjustment_id' => $adj->id,
                'updated_by' => $actor?->id,
            ])->save();

            activity('asset')->performedOn($serial)->causedBy($actor)
                ->withProperties(['adj' => $adj->number, 'reason_code_id' => $alasan, 'keterangan' => $ket])
                ->log('Aset ditandai hilang; '.$adj->number.' menunggu approval');

            return $adj;
        });
    }

    public function found(Serial $serial, ?User $actor = null): Serial
    {
        if ($serial->asset_state !== AssetState::Lost) {
            throw AssetRuleException::rule('BR-GEN-01', 'Hanya aset berstatus Hilang yang bisa ditandai ditemukan.');
        }

        $adjAktif = StockAdjustment::query()->withoutGlobalScopes()
            ->where('origin', AdjustmentOrigin::AssetLost->value)
            ->whereIn('id', StockAdjustmentLine::query()->where('serial_id', $serial->id)->select('stock_adjustment_id'))
            ->whereIn('status', [StockAdjustmentStatus::Submitted->value, StockAdjustmentStatus::PendingApproval->value, StockAdjustmentStatus::Approved->value])
            ->first();

        if ($adjAktif !== null) {
            throw AssetRuleException::rule('BR-AST-04', 'ADJ '.$adjAktif->number.' untuk aset ini masih berjalan; tolak atau batalkan dulu.');
        }

        return DB::transaction(function () use ($serial, $actor) {
            $this->sync->recompute($serial);

            AssetHandover::query()->withoutGlobalScopes()
                ->where('serial_id', $serial->id)
                ->whereNotNull('lost_at')
                ->where('status', AssetHandoverStatus::CheckedOut->value)
                ->update(['lost_at' => null, 'updated_by' => $actor?->id, 'updated_at' => now()]);

            activity('asset')->performedOn($serial)->causedBy($actor)->log('Aset ditemukan kembali; tanda hilang dibatalkan');

            return $serial->refresh();
        });
    }
}

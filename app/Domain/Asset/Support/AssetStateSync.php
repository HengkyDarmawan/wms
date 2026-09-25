<?php

declare(strict_types=1);

namespace App\Domain\Asset\Support;

use App\Domain\Asset\Enums\AssetHandoverStatus;
use App\Domain\Asset\Models\AssetHandover;
use App\Domain\Master\Enums\AssetState;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Serial;
use App\Domain\Stock\Enums\ReservationLevel;
use App\Domain\Stock\Enums\ReservationStatus;
use App\Domain\Stock\Enums\StockStatus;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;

/**
 * BR-AST-01 — state aset mengikuti lokasi dan kondisinya (A-164).
 *
 * Dipasang sebagai observer `StockMovement::created` dan
 * `StockReservation::saved`, jadi setiap modul yang menggerakkan aset (PCK,
 * SJ, GRN, RET, TRF, ADJ, OPN) otomatis memperbarui state tanpa tahu modul
 * Aset ada. Kartu stok tetap satu-satunya penggerak stok (P-01); di sini hanya
 * kolom `serials.asset_state` yang ditulis.
 *
 * | Lokasi (bin) × kondisi | State |
 * |---|---|
 * | Penyimpanan/Penerimaan × Tersedia | `available`, atau `reserved` bila ada alokasi keras |
 * | Loading Area × Tersedia | `reserved` (sudah dipetik) |
 * | Karantina (bin atau kondisi) | `maintenance` (kecuali sudah `damaged`) |
 * | Kondisi Rusak / bin Waste | `damaged` (kecuali sudah `maintenance`) |
 * | Dalam Perjalanan | `in_transit` |
 * | On-site Proyek | `on_loan` + `current_project_id` |
 * | Retur | `returned` |
 * | keluar ledger | `written_off` bila sudah `lost` atau lewat ADJ |
 *
 * Pengecualian: aset `lost` tidak berubah oleh pergerakan selain keluar
 * ledger; pemilahan RET atas aset yang sudah diperiksa memakai hasil
 * pemeriksaan (A-164).
 */
class AssetStateSync
{
    public function fromMovement(StockMovement $m): void
    {
        if ($m->serial_id === null) {
            return;
        }

        $item = Item::query()->find($m->item_id);

        if ($item === null || ! $item->isAsset()) {
            return;
        }

        $serial = Serial::query()->find($m->serial_id);

        if ($serial === null) {
            return;
        }

        if ($m->to_bin_id === null) {
            if ($serial->asset_state === AssetState::Lost || $m->document_type === 'stock_adjustment') {
                $this->simpan($serial, AssetState::WrittenOff, null);
            }

            return;
        }

        if ($serial->asset_state === AssetState::Lost || $serial->asset_state === AssetState::WrittenOff) {
            return;
        }

        // Pemilahan RET setelah pemeriksaan: hasil pemeriksaan yang berlaku.
        if ($m->document_type === 'goods_return' && $this->sudahDiperiksa($serial)) {
            return;
        }

        $bin = Bin::query()->withoutGlobalScopes()->find($m->to_bin_id);

        if ($bin === null) {
            return;
        }

        [$state, $proyek] = $this->petakan($serial, $bin, $m->stock_status);
        $this->simpan($serial, $state, $proyek);
    }

    public function fromReservation(StockReservation $r): void
    {
        if ($r->serial_id === null || $r->level !== ReservationLevel::Hard) {
            return;
        }

        $serial = Serial::query()->find($r->serial_id);

        if ($serial === null || ! $serial->item?->isAsset()) {
            return;
        }

        if ($r->status === ReservationStatus::Active && $serial->asset_state === AssetState::Available) {
            $this->simpan($serial, AssetState::Reserved, $serial->current_project_id);

            return;
        }

        if ($r->status === ReservationStatus::Released && $serial->asset_state === AssetState::Reserved) {
            $this->recompute($serial);
        }
    }

    /** Menghitung ulang state dari saldo sekarang (mis. aset `lost` ditemukan kembali). */
    public function recompute(Serial $serial): void
    {
        $saldo = StockBalance::query()->where('serial_id', $serial->id)->where('qty_base', '>', 0)->first();

        if ($saldo === null) {
            $this->simpan($serial, AssetState::WrittenOff, null);

            return;
        }

        $bin = Bin::query()->withoutGlobalScopes()->find($saldo->bin_id);

        if ($bin === null) {
            return;
        }

        [$state, $proyek] = $this->petakan($serial, $bin, $saldo->stock_status, true);
        $this->simpan($serial, $state, $proyek);
    }

    /**
     * @return array{0: AssetState, 1: ?int}
     */
    private function petakan(Serial $serial, Bin $bin, StockStatus $kondisi, bool $ulang = false): array
    {
        $sekarang = $ulang ? null : $serial->asset_state;

        return match (true) {
            $bin->bin_type === BinType::OnSite => [AssetState::OnLoan, $bin->project_id !== null ? (int) $bin->project_id : $serial->current_project_id],
            $bin->bin_type === BinType::InTransit => [AssetState::InTransit, $serial->current_project_id],
            $bin->bin_type === BinType::Return => [AssetState::Returned, $serial->current_project_id],
            $bin->bin_type === BinType::Waste => [AssetState::Damaged, null],
            $bin->bin_type === BinType::Quarantine || $kondisi === StockStatus::Quarantine => [$sekarang === AssetState::Damaged ? AssetState::Damaged : AssetState::Maintenance, null],
            $kondisi === StockStatus::Damaged => [$sekarang === AssetState::Maintenance ? AssetState::Maintenance : AssetState::Damaged, null],
            $bin->bin_type === BinType::Staging => [AssetState::Reserved, null],
            $this->dialokasikan($serial) => [AssetState::Reserved, null],
            default => [AssetState::Available, null],
        };
    }

    private function dialokasikan(Serial $serial): bool
    {
        return StockReservation::query()->active()
            ->where('level', ReservationLevel::Hard->value)
            ->where('serial_id', $serial->id)
            ->exists();
    }

    private function sudahDiperiksa(Serial $serial): bool
    {
        return AssetHandover::query()->withoutGlobalScopes()
            ->where('serial_id', $serial->id)
            ->latest('id')
            ->value('status') === AssetHandoverStatus::Inspected->value;
    }

    private function simpan(Serial $serial, AssetState $state, ?int $proyek): void
    {
        $ubah = ['asset_state' => $state, 'current_project_id' => $proyek];

        // Tanggal kembali hanya berarti selama aset di luar gudang.
        if (! in_array($state, [AssetState::OnLoan, AssetState::InTransit, AssetState::Returned], true)) {
            $ubah['due_return_date'] = null;
        }

        $serial->forceFill($ubah);

        if ($serial->isDirty()) {
            $serial->save();
        }
    }
}

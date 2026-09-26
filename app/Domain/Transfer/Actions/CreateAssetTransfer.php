<?php

declare(strict_types=1);

namespace App\Domain\Transfer\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Master\Enums\AssetState;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Serial;
use App\Domain\Return\Enums\GoodsReturnStatus;
use App\Domain\Return\Models\GoodsReturnLine;
use App\Domain\Shipment\Exceptions\ShipmentRuleException;
use App\Domain\Shipment\Support\WarehouseBins;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Support\DocumentNumber;
use App\Domain\Transfer\Enums\TransferOrigin;
use App\Domain\Transfer\Enums\TransferStatus;
use App\Domain\Transfer\Exceptions\TransferRuleException;
use App\Domain\Transfer\Models\Transfer;
use App\Domain\Transfer\Models\TransferLine;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `transfer.create` — TRF aset On-site antar proyek (A-249,
 * BR-RET-02 "atau `on_site` untuk aset"; mengubah A-116).
 *
 * Aset yang sedang dipinjam proyek asal dipindah langsung ke proyek tujuan
 * tanpa kembali ke gudang: TRF ini diputus mesin approval seperti TRF lain,
 * lalu diangkut dengan SJ antar site (SJ tanpa PCK, A-247) dan baru bergerak
 * di kartu stok — bin On-site asal → bin On-site tujuan — saat diterima di
 * proyek tujuan. Gudang asal/tujuan TRF = gudang pemilik bin On-site
 * masing-masing proyek (Gudang Site-nya).
 */
class CreateAssetTransfer
{
    public function __construct(
        private readonly DocumentNumber $nomor,
        private readonly ApprovalEngine $approval,
        private readonly WarehouseBins $bins,
    ) {}

    /**
     * @param  array<string, mixed>  $header  from_project_id, to_project_id, notes
     * @param  array<int, int|string>  $serialIds
     */
    public function handle(array $header, array $serialIds, ?User $actor = null): Transfer
    {
        $asal = $this->proyek($header['from_project_id'] ?? null, 'from_project_id', $actor);
        $tujuan = $this->proyek($header['to_project_id'] ?? null, 'to_project_id', $actor);

        if ((int) $asal->id === (int) $tujuan->id) {
            throw TransferRuleException::field('BR-RET-02', 'to_project_id', 'Proyek tujuan harus berbeda dari proyek asal.');
        }

        $binAsal = $this->binOnSite($asal, 'from_project_id');
        $binTujuan = $this->binOnSite($tujuan, 'to_project_id');
        $aset = $this->aset($serialIds, $asal, $binAsal);

        return DB::transaction(function () use ($asal, $tujuan, $binAsal, $binTujuan, $aset, $header, $actor) {
            $gudangAsal = $binAsal->warehouse()->withoutGlobalScopes()->firstOrFail();

            $trf = Transfer::create([
                'number' => $this->nomor->next('TRF', (string) $gudangAsal->code),
                'from_warehouse_id' => $binAsal->warehouse_id,
                'to_warehouse_id' => $binTujuan->warehouse_id,
                'from_project_id' => $asal->id,
                'to_project_id' => $tujuan->id,
                'origin' => TransferOrigin::Manual,
                'asset_onsite' => true,
                'status' => TransferStatus::Submitted,
                'submitted_by' => $actor?->id,
                'notes' => $this->teks($header['notes'] ?? null),
            ]);

            foreach ($aset as $serial) {
                TransferLine::create([
                    'transfer_id' => $trf->id,
                    'item_id' => $serial->item_id,
                    'serial_id' => $serial->id,
                    'qty_base' => 1,
                ]);
            }

            activity('transfer')->performedOn($trf)->causedBy($actor)
                ->withProperties(['dari' => $asal->code, 'ke' => $tujuan->code, 'aset' => $aset->pluck('serial_no')->all()])
                ->log('TRF aset antar proyek diajukan');

            $trf->forceFill(['status' => TransferStatus::PendingApproval])->save();

            $this->approval->submit(ApprovalDocumentType::Transfer, $trf, $actor);

            return $trf->refresh();
        });
    }

    /** BR-PRJ-01, BR-ACC-05: proyek aktif dalam cakupan pengaju. */
    private function proyek(mixed $id, string $kolom, ?User $actor): Project
    {
        $proyek = is_numeric($id) ? Project::query()->withoutGlobalScopes()->find((int) $id) : null;
        $cakupan = $actor?->accessibleProjectIds();

        if ($proyek === null || ($cakupan !== null && $kolom === 'from_project_id' && ! in_array((int) $proyek->id, $cakupan, true))) {
            throw TransferRuleException::field('BR-ACC-05', $kolom, $kolom === 'from_project_id' ? 'Proyek asal wajib dipilih dari cakupan Anda.' : 'Proyek tujuan wajib dipilih.');
        }

        if (! $proyek->acceptsDocuments()) {
            throw TransferRuleException::field('BR-PRJ-01', $kolom, 'Proyek '.$proyek->code.' berstatus '.$proyek->status->label().' dan tidak menerima dokumen baru.');
        }

        return $proyek;
    }

    private function binOnSite(Project $proyek, string $kolom): Bin
    {
        try {
            return $this->bins->onSite($proyek);
        } catch (ShipmentRuleException $e) {
            throw TransferRuleException::field($e->rule, $kolom, $e->getMessage());
        }
    }

    /**
     * Aset: serial `on_loan` di proyek asal yang saldonya di bin On-site proyek
     * itu, belum diajukan di TRF aset atau RET lain yang masih berjalan.
     *
     * @param  array<int, int|string>  $serialIds
     * @return Collection<int, Serial>
     */
    private function aset(array $serialIds, Project $asal, Bin $binAsal): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $serialIds))));

        if ($ids === []) {
            throw TransferRuleException::rule('BR-RET-01', 'Pilih minimal satu aset yang dipindah.');
        }

        $hasil = collect();

        foreach ($ids as $id) {
            $serial = Serial::query()->with('item')->find($id);
            $label = $serial?->serial_no ?? '#'.$id;

            if ($serial === null || ! $serial->item?->isAsset()) {
                throw TransferRuleException::field('BR-AST-01', 'serial_ids', 'Aset '.$label.' tidak ditemukan.');
            }

            $diOnSite = StockBalance::query()->nonZero()
                ->where('serial_id', $serial->id)->where('bin_id', $binAsal->id)->exists();

            if ($serial->asset_state !== AssetState::OnLoan || (int) $serial->current_project_id !== (int) $asal->id || ! $diOnSite) {
                throw TransferRuleException::field('BR-RET-02', 'serial_ids', 'Aset '.$label.' tidak sedang dipinjam di proyek '.$asal->code.'.');
            }

            $trfLain = TransferLine::query()->where('serial_id', $serial->id)
                ->whereHas('transfer', fn ($q) => $q->where('asset_onsite', true)->whereNotIn('status', [
                    TransferStatus::Completed->value, TransferStatus::Rejected->value, TransferStatus::Cancelled->value,
                ]))->exists();

            $retLain = GoodsReturnLine::query()->where('serial_id', $serial->id)->whereNull('split_from_line_id')
                ->whereHas('goodsReturn', fn ($q) => $q->withoutGlobalScopes()->whereIn('status', [
                    GoodsReturnStatus::Submitted->value, GoodsReturnStatus::PendingApproval->value,
                    GoodsReturnStatus::Approved->value, GoodsReturnStatus::InProgress->value,
                ]))->exists();

            if ($trfLain || $retLain) {
                throw TransferRuleException::field('BR-RET-02', 'serial_ids', 'Aset '.$label.' sudah diajukan di transfer atau retur lain yang masih berjalan.');
            }

            $hasil->push($serial);
        }

        return $hasil;
    }

    private function teks(mixed $nilai): ?string
    {
        $isi = is_scalar($nilai) ? trim((string) $nilai) : '';

        return $isi === '' ? null : mb_substr($isi, 0, 255);
    }
}

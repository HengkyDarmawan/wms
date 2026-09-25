<?php

declare(strict_types=1);

namespace App\Domain\Waste\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Shared\Files\StoreUpload;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Waste\Enums\WasteDisposalStatus;
use App\Domain\Waste\Exceptions\WasteRuleException;
use App\Domain\Waste\Models\WasteDisposal;
use App\Domain\Waste\Models\WasteDisposalLine;
use App\Domain\Waste\Support\DisposableWaste;
use App\Domain\Waste\Support\WastePoster;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Permission: `waste.close` — `approved → closed` (Katalog §2.14).
 *
 * Guard "bukti (foto/berita acara)": foto BA ATAU nomor/keterangan BA
 * bertanda tangan — salah satu wajib (A-160). Isi bin Waste diperiksa ulang,
 * lalu bin Waste → keluar (atau ke bin penyimpanan bila dipakai ulang) dengan
 * kejadian `waste_disposed`.
 */
class CloseWasteDisposal
{
    public function __construct(
        private readonly DisposableWaste $waste,
        private readonly WastePoster $poster,
        private readonly StoreUpload $files,
    ) {}

    public function handle(WasteDisposal $wst, ?UploadedFile $photo, ?string $note, ?User $actor = null): WasteDisposal
    {
        if ($wst->status !== WasteDisposalStatus::Approved) {
            throw WasteRuleException::rule('BR-GEN-01', 'Hanya BA waste yang sudah disetujui yang bisa ditutup.');
        }

        if ($actor !== null && ! $actor->canAccessWarehouse((int) $wst->warehouse_id)) {
            throw WasteRuleException::rule('BR-ACC-05', 'Gudang BA waste ini di luar cakupan Anda.');
        }

        $catatan = $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 255) : null;

        if ($photo === null && $catatan === null) {
            throw WasteRuleException::field('BR-GEN-01', 'evidence', 'Bukti wajib: unggah foto berita acara atau isi nomor/keterangan BA bertanda tangan.');
        }

        $wst->loadMissing('warehouse');

        if ($wst->disposition->returnsToStock()) {
            $this->periksaBinTujuan($wst);
        }

        $this->waste->assertAvailable($wst->warehouse, $wst->lines()->get()->map(fn (WasteDisposalLine $l) => [
            'item_id' => (int) $l->item_id,
            'bin_id' => (int) $l->bin_id,
            'lot_id' => $l->lot_id === null ? null : (int) $l->lot_id,
            'serial_id' => $l->serial_id === null ? null : (int) $l->serial_id,
            'piece_id' => $l->piece_id === null ? null : (int) $l->piece_id,
            'stock_status' => $l->stock_status,
            'qty_base' => (float) $l->qty_base,
        ])->all(), (int) $wst->id);

        $path = null;

        if ($photo !== null) {
            try {
                $path = $this->files->handle($photo, 'waste-disposals', (string) $wst->id);
            } catch (RuntimeException $e) {
                throw WasteRuleException::field('BR-GEN-01', 'evidence', $e->getMessage());
            }
        }

        try {
            return DB::transaction(function () use ($wst, $path, $catatan, $actor) {
                $kunci = WasteDisposal::withoutGlobalScopes()->lockForUpdate()->findOrFail($wst->id);

                if ($kunci->status !== WasteDisposalStatus::Approved) {
                    throw WasteRuleException::rule('BR-GEN-01', 'BA waste ini sudah tidak berstatus Disetujui.');
                }

                $this->poster->post($kunci, $actor);

                $kunci->forceFill([
                    'status' => WasteDisposalStatus::Closed,
                    'closed_by' => $actor?->id,
                    'closed_at' => now(),
                    'evidence_path' => $path,
                    'evidence_note' => $catatan,
                ])->save();

                activity('waste')->performedOn($kunci)->causedBy($actor)
                    ->withProperties(['bukti_foto' => $path !== null, 'bukti_catatan' => $catatan])
                    ->log('BA waste ditutup; '.$kunci->disposition->label().' (waste_disposed)');

                return $kunci->refresh();
            });
        } catch (Throwable $e) {
            $this->files->delete($path);

            throw $e;
        }
    }

    private function periksaBinTujuan(WasteDisposal $wst): void
    {
        $sah = Bin::query()->withoutGlobalScopes()
            ->whereKey((int) $wst->target_bin_id)
            ->where('warehouse_id', $wst->warehouse_id)
            ->where('bin_type', BinType::Storage->value)
            ->where('bin_status', BinStatus::Active->value)
            ->exists();

        if (! $sah) {
            throw WasteRuleException::rule('BR-STK-02', 'Bin tujuan pakai ulang sudah tidak aktif; batalkan dan ajukan BA waste baru.');
        }
    }
}

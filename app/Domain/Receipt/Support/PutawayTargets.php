<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Support;

use App\Domain\Label\Models\PackageLabelMove;
use App\Domain\Master\Support\ScanCode;
use App\Domain\Receipt\Enums\PutawayTaskStatus;
use App\Domain\Receipt\Models\PutawayTask;
use App\Domain\Receipt\Models\PutawayTaskLine;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Support\BinCode;
use App\Domain\Warehouse\Support\StorageLocationPlanner;
use Illuminate\Support\Collection;

/**
 * Bahan layar put-away pindai (K-F, A-375–A-377): daftar **Menunggu
 * Dimasukkan**, mengenali label barang yang dipindai, mengenali bin tujuan
 * dari QR/kode, dan tanda **penuh** bila tempat simpan barang itu penuh saat
 * saran dibuat (keputusan #3).
 */
class PutawayTargets
{
    public const MAKS = 100;

    /**
     * Baris put-away yang belum ditaruh di tugas *Menunggu*, dalam cakupan
     * gudang pengguna (global scope `PutawayTask`, BR-ACC-05).
     *
     * @return Collection<int, PutawayTaskLine>
     */
    public function menunggu(?int $gudangId = null, int $maks = self::MAKS): Collection
    {
        $tugas = PutawayTask::query()->where('status', PutawayTaskStatus::Pending->value)
            ->when($gudangId !== null, fn ($q) => $q->where('warehouse_id', $gudangId))
            ->select('id');

        return PutawayTaskLine::query()
            ->whereNull('scanned_at')
            ->whereIn('putaway_task_id', $tugas)
            ->with('task:id,number,warehouse_id,goods_receipt_id', 'item:id,code,name,base_uom_id,tracking_mode',
                'item.baseUom:id,code', 'item.activeConversions.uom', 'lot:id,lot_no', 'serial:id,serial_no', 'piece:id,piece_no',
                'suggestedBin', 'fromBin:id,code')
            ->orderBy('putaway_task_id')->orderBy('id')
            ->limit($maks)
            ->get();
    }

    /**
     * Label barang yang dipindai → baris yang cocok: label kemasan (A-296)
     * menunjuk baris GRN-nya dan harus masih di bin asal baris; kode/barcode
     * item, nomor lot/serial/potongan lewat {@see ScanCode::resolve()}.
     *
     * @param  Collection<int, PutawayTaskLine>  $lines
     * @return Collection<int, PutawayTaskLine>
     */
    public function cocokBarang(string $kode, Collection $lines): Collection
    {
        if (($label = ScanCode::label($kode)) !== null) {
            $grn = PackageLabelMove::query()->where('package_label_id', $label->id)
                ->where('document_type', 'goods_receipt')->where('qty_change', '>', 0)
                ->pluck('document_line_id')->push($label->goods_receipt_line_id)
                ->filter()->map(fn ($v) => (int) $v)->unique()->all();

            return $lines->filter(fn (PutawayTaskLine $l) => in_array((int) $l->goods_receipt_line_id, $grn, true)
                && ($label->bin_id === null || (int) $label->bin_id === (int) $l->from_bin_id))->values();
        }

        $arti = ScanCode::resolve($kode);

        return $lines->filter(function (PutawayTaskLine $l) use ($arti) {
            foreach ($arti as $a) {
                if ((int) $a['item_id'] !== (int) $l->item_id) {
                    continue;
                }

                if (($a['lot_id'] !== null && $a['lot_id'] !== (int) $l->lot_id)
                    || ($a['serial_id'] !== null && $a['serial_id'] !== (int) $l->serial_id)
                    || ($a['piece_id'] !== null && $a['piece_id'] !== (int) $l->piece_id)) {
                    continue;
                }

                return true;
            }

            return false;
        })->values();
    }

    /**
     * Bin tujuan dari hasil pindai (QR tautan, kode lengkap, atau kode pendek).
     * Mengembalikan bin penyimpanan gudang itu, atau pesan penolakan.
     */
    public function binTujuan(string $kode, int $gudangId): Bin|string
    {
        $gudang = Warehouse::query()->withoutGlobalScopes()->findOrFail($gudangId);
        $bin = BinCode::cocokkan($kode, app(PutawaySuggester::class)->storageBins($gudang));

        if ($bin !== null) {
            return $bin;
        }

        $lain = BinCode::cocokkan($kode, Bin::query()->withoutGlobalScopes()->with('mainBin:id,code,warehouse_id')
            ->where('warehouse_id', $gudangId)->get());

        if ($lain !== null && $lain->mainBin !== null) {
            return __('Bin :bin digabung ke bin utama :utama; pindai QR bin utama.', [
                'bin' => BinCode::pendekUntuk($lain), 'utama' => BinCode::pendekUntuk($lain->mainBin),
            ]);
        }

        if ($lain !== null && $lain->bin_type !== BinType::Storage) {
            return __('Bin :bin bukan bin penyimpanan.', ['bin' => $lain->code]);
        }

        if ($lain !== null) {
            return __('Bin :bin tidak aktif.', ['bin' => BinCode::pendekUntuk($lain)]);
        }

        return __('":kode" bukan bin gudang :gudang.', ['kode' => BinCode::dariPindai($kode), 'gudang' => $gudang->code]);
    }

    /**
     * Keputusan #3 (A-377): baris yang saran binnya **bukan** dari tempat
     * simpan barang itu padahal barangnya punya tempat simpan di gudang itu —
     * artinya tempatnya penuh saat saran dibuat, jadi dipakai aturan lama.
     *
     * @param  iterable<PutawayTaskLine>  $lines
     * @return array<int, bool> line_id => penuh
     */
    public function penuh(iterable $lines): array
    {
        $planner = app(StorageLocationPlanner::class);
        $kandidat = [];
        $hasil = [];

        foreach ($lines as $l) {
            $gudang = (int) ($l->task?->warehouse_id ?? 0);
            $kunci = $l->item_id.':'.$gudang;

            if (! array_key_exists($kunci, $kandidat)) {
                $wh = Warehouse::query()->withoutGlobalScopes()->find($gudang);
                $kandidat[$kunci] = $wh === null || $l->item === null ? [] : $planner->kandidat($l->item, $wh)->pluck('id')->map(fn ($v) => (int) $v)->all();
            }

            $hasil[(int) $l->id] = $kandidat[$kunci] !== [] && ! in_array((int) $l->suggested_bin_id, $kandidat[$kunci], true);
        }

        return $hasil;
    }
}

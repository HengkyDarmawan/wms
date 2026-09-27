<?php

declare(strict_types=1);

namespace App\Domain\Label\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Label\Enums\PackageLabelStatus;
use App\Domain\Label\Exceptions\LabelRuleException;
use App\Domain\Label\Models\PackageLabel;
use App\Domain\Label\Support\LabelCode;
use App\Domain\Label\Support\PackageLabelLedger;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Receipt\Enums\GoodsReceiptStatus;
use App\Domain\Receipt\Enums\QcResult;
use App\Domain\Receipt\Enums\ReceiptType;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\GoodsReceiptLine;
use App\Domain\Stock\Support\DocumentNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `receipt.complete` — membuat label **induk** untuk GRN vendor
 * yang selesai (A-296): per baris "berapa kemasan × isi per kemasan", kemasan
 * terakhir berisi sisanya. Hanya bagian **Baik** yang lolos/tanpa QC yang
 * dilabeli; barang Rusak menunggu retur ke vendor (A-287). Item bernomor seri
 * memakai label serial (A-298), item per potong label potongan.
 *
 * Urut kode per item dipesan sekaligus lewat `document_sequences` berkunci.
 */
class CreatePackageLabels
{
    private const EPS = 0.00005;

    public function __construct(
        private readonly DocumentNumber $nomor,
        private readonly PackageLabelLedger $ledger,
    ) {}

    /**
     * @param  array<int|string, array{packages: int|string, per_package: float|string, uom_id?: int|string|null}>  $plan  goods_receipt_line_id => rencana
     * @return Collection<int, PackageLabel>
     */
    public function handle(GoodsReceipt $grn, array $plan, ?User $actor = null): Collection
    {
        if ($grn->receipt_type !== ReceiptType::Vendor || $grn->status !== GoodsReceiptStatus::Completed) {
            throw LabelRuleException::rule('BR-LBL-02', 'Label kemasan dibuat untuk penerimaan vendor yang sudah selesai.');
        }

        $lines = $grn->lines()->with('item', 'lot')->whereIn('id', array_map('intval', array_keys($plan)))->get()->keyBy('id');
        $rencana = [];

        foreach ($plan as $lineId => $r) {
            $n = (int) ($r['packages'] ?? 0);

            if ($n === 0) {
                continue; // 0 kemasan = baris ini tidak dilabeli
            }

            $line = $lines->get((int) $lineId) ?? throw LabelRuleException::rule('BR-LBL-02', 'Baris label bukan milik '.$grn->number.'.');
            $label = 'Baris '.$line->item?->code;
            $isi = round((float) ($r['per_package'] ?? 0), 4);
            $sisa = $this->belumBerlabel($line);

            if (! in_array($line->item?->tracking_mode, [TrackingMode::None, TrackingMode::Lot], true)) {
                throw LabelRuleException::rule('BR-LBL-02', $label.': item bernomor seri/per potong memakai label serial/potongan.');
            }

            if ($n < 1 || $n > 1000 || $isi <= 0) {
                throw LabelRuleException::field('BR-LBL-02', 'labels', $label.': isi jumlah kemasan (1–1000) dan isi per kemasan lebih dari nol.');
            }

            if ($sisa <= self::EPS) {
                throw LabelRuleException::field('BR-LBL-02', 'labels', $label.': tidak ada barang Baik yang belum berlabel.');
            }

            // Kemasan terakhir boleh berisi kurang, tetapi semua kemasan harus terisi.
            if (($n - 1) * $isi - $sisa > -self::EPS || $n * $isi - $sisa < -self::EPS) {
                throw LabelRuleException::field('BR-LBL-02', 'labels', $label.': '.$n.' kemasan × '.PackageLabelLedger::angka($isi)
                    .' tidak cocok dengan '.PackageLabelLedger::angka($sisa).' barang Baik yang belum berlabel.');
            }

            $rencana[] = [$line, $n, $isi, $sisa, is_numeric($r['uom_id'] ?? null) ? (int) $r['uom_id'] : null];
        }

        return DB::transaction(function () use ($grn, $rencana, $actor) {
            $dibuat = collect();

            foreach ($rencana as [$line, $n, $isi, $sisa, $uom]) {
                $pertama = $this->nomor->reserve('LBL', (string) $line->item_id, $n);

                for ($k = 0; $k < $n; $k++) {
                    $qty = $k === $n - 1 ? round($sisa - ($n - 1) * $isi, 4) : $isi;

                    $label = PackageLabel::create([
                        'code' => LabelCode::parent($line->item, $pertama + $k),
                        'sequence' => $pertama + $k,
                        'item_id' => $line->item_id,
                        'lot_id' => $line->lot_id,
                        'goods_receipt_id' => $grn->id,
                        'goods_receipt_line_id' => $line->id,
                        'package_uom_id' => $uom,
                        'qty' => $qty,
                        'qty_remaining' => $qty,
                        'status' => PackageLabelStatus::InStock,
                        'warehouse_id' => $grn->warehouse_id,
                        'bin_id' => $this->binSekarang($line),
                        'created_by' => $actor?->id,
                    ]);

                    $this->ledger->catat($label, $qty, ['type' => 'goods_receipt', 'id' => $grn->id, 'line_id' => $line->id, 'number' => $grn->number], $actor,
                        ['warehouse_id' => $grn->warehouse_id, 'to_bin_id' => $label->bin_id]);

                    $dibuat->push($label);
                }
            }

            if ($dibuat->isNotEmpty()) {
                activity('receipt')->performedOn($grn)->causedBy($actor)
                    ->withProperties(['label' => $dibuat->count()])
                    ->log('Label kemasan dibuat: '.$dibuat->count().' label');
            }

            return $dibuat;
        });
    }

    /** Baris GRN vendor Barang biasa/berkedaluwarsa yang masih punya bagian Baik tanpa label. */
    public function labelable(GoodsReceiptLine $line): bool
    {
        $line->loadMissing('receipt', 'item');

        return $line->receipt?->receipt_type === ReceiptType::Vendor
            && in_array($line->item?->tracking_mode, [TrackingMode::None, TrackingMode::Lot], true)
            && $this->belumBerlabel($line) > self::EPS;
    }

    /** Bagian Baik baris yang boleh dilabeli dikurangi label induk yang sudah ada. */
    public function belumBerlabel(GoodsReceiptLine $line): float
    {
        $baik = (float) $line->qty_received;
        $bisa = $baik > 0 && ($line->qc_result === null || $line->qc_result === QcResult::Passed);

        if (! $bisa) {
            return 0.0;
        }

        $sudah = (float) PackageLabel::query()->where('goods_receipt_line_id', $line->id)->whereNull('parent_id')
            ->where('status', '!=', PackageLabelStatus::Cancelled->value)->sum('qty');

        return max(0.0, round($baik - $sudah, 4));
    }

    /**
     * Usulan layar: jumlah kemasan & isi per kemasan dari satuan yang diketik di
     * GRN (A-291), atau kemasan terbesar item, atau satu kemasan berisi semua.
     *
     * @return array{packages: int, per_package: float, uom_id: ?int}
     */
    public function defaults(GoodsReceiptLine $line): array
    {
        $sisa = $this->belumBerlabel($line);

        if ($sisa <= self::EPS) {
            return ['packages' => 0, 'per_package' => 0.0, 'uom_id' => null];
        }

        if ($line->uom_id !== null && (float) $line->uom_qty_base > 0) {
            return ['packages' => (int) ceil($sisa / (float) $line->uom_qty_base - 1e-6), 'per_package' => (float) $line->uom_qty_base, 'uom_id' => (int) $line->uom_id];
        }

        $kemasan = $line->item?->activeConversions
            ->first(fn ($k) => ! $k->is_nominal_piece && (float) $k->qty_base > 1 && (float) $k->qty_base <= $sisa + self::EPS);

        if ($kemasan !== null) {
            return ['packages' => (int) ceil($sisa / (float) $kemasan->qty_base - 1e-6), 'per_package' => (float) $kemasan->qty_base, 'uom_id' => (int) $kemasan->uom_id];
        }

        return ['packages' => 1, 'per_package' => $sisa, 'uom_id' => null];
    }

    /**
     * Lokasi awal: bin tujuan put-away yang sudah selesai, bin asal put-away
     * yang masih menunggu (Penerimaan, juga untuk barang lolos QC), atau bin terima.
     */
    private function binSekarang(GoodsReceiptLine $line): ?int
    {
        $put = $line->putawayLines()->with('task:id,status')->latest('id')->first();

        if ($put !== null) {
            return (int) ($put->task?->status?->value === 'completed' && $put->bin_id !== null ? $put->bin_id : $put->from_bin_id);
        }

        return $line->receiving_bin_id === null ? null : (int) $line->receiving_bin_id;
    }
}

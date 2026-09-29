<?php

declare(strict_types=1);

namespace App\Domain\Label\Support;

use App\Domain\Access\Models\User;
use App\Domain\Label\Enums\PackageLabelStatus;
use App\Domain\Label\Exceptions\LabelRuleException;
use App\Domain\Label\Models\PackageLabel;
use App\Domain\Label\Models\PackageLabelMove;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Stock\Models\StockBalance;
use Illuminate\Support\Collection;

/**
 * Satu-satunya penulis status & isi label kemasan (A-296, A-299–A-301).
 *
 * Label adalah lapisan penelusuran: tidak ada metode di sini yang menyentuh
 * kartu stok (P-01). Aksi PCK, ISU, GRN, put-away, dan pilah retur memanggil
 * kelas ini setelah/bersama gerakan stoknya, di transaksi yang sama.
 *
 * `$doc` = ['type' => …, 'id' => …, 'line_id' => …, 'number' => …], sama
 * dengan penunjuk dokumen di `stock_movements`.
 */
class PackageLabelLedger
{
    private const EPS = 0.00005;

    /** Isi label yang masih bisa diambil; menolak label yang bukan Di gudang. */
    public function claimable(PackageLabel $label): float
    {
        if ($label->status !== PackageLabelStatus::InStock) {
            throw LabelRuleException::rule('BR-LBL-03', 'Label '.$label->code.' berstatus '.$label->status->label().'; tidak bisa dipindai keluar.');
        }

        $sisa = (float) $label->qty_remaining;

        if ($label->isParent() && $sisa <= self::EPS
            && PackageLabel::query()->where('parent_id', $label->id)->inStock()->exists()) {
            throw LabelRuleException::rule('BR-LBL-03', 'Isi kemasan '.$label->code.' sudah berlabel isi; pindai label isinya.');
        }

        if ($sisa <= self::EPS) {
            throw LabelRuleException::rule('BR-LBL-03', 'Label '.$label->code.' tidak berisi lagi.');
        }

        return $sisa;
    }

    /**
     * Klaim pindai (label + jumlah) → label yang sah untuk gudang, item, dan lot baris.
     *
     * @param  array<int, array{id: int|string, qty: float|string}>  $claims
     * @return array<int, array{label: PackageLabel, qty: float}>
     */
    public function resolveClaims(array $claims, int $warehouseId, int $itemId, ?int $lotId, string $konteks, bool $lock = false): array
    {
        $perLabel = [];

        foreach ($claims as $c) {
            $id = (int) ($c['id'] ?? 0);
            $perLabel[$id] = round(($perLabel[$id] ?? 0) + (float) ($c['qty'] ?? 0), 4);
        }

        $label = PackageLabel::query()->whereIn('id', array_keys($perLabel))
            ->when($lock, fn ($q) => $q->lockForUpdate())->get()->keyBy('id');
        $hasil = [];

        foreach ($perLabel as $id => $qty) {
            $l = $label->get($id) ?? throw LabelRuleException::rule('BR-LBL-03', $konteks.': label yang dipindai tidak ditemukan.');

            if ((int) $l->warehouse_id !== $warehouseId) {
                throw LabelRuleException::rule('BR-LBL-03', $konteks.': label '.$l->code.' tidak tercatat di gudang ini.');
            }

            if ((int) $l->item_id !== $itemId || ($lotId !== null && (int) $l->lot_id !== $lotId)) {
                throw LabelRuleException::rule('BR-LBL-03', $konteks.': label '.$l->code.' bukan untuk barang/lot baris ini.');
            }

            if ($qty <= 0 || $qty - $this->claimable($l) > self::EPS) {
                throw LabelRuleException::rule('BR-LBL-03', $konteks.': jumlah dari label '.$l->code.' melebihi isinya ('.self::angka((float) $l->qty_remaining).').');
            }

            $hasil[] = ['label' => $l, 'qty' => $qty];
        }

        return $hasil;
    }

    /** Isi label Di gudang untuk item (+ lot) di gudang itu — dasar kewajiban pindai (A-299). */
    public function labelled(int $warehouseId, int $itemId, ?int $lotId = null): float
    {
        return round((float) PackageLabel::query()->inStock()
            ->where('warehouse_id', $warehouseId)->where('item_id', $itemId)
            ->when($lotId !== null, fn ($q) => $q->where('lot_id', $lotId))
            ->sum('qty_remaining'), 4);
    }

    /**
     * A-299: barang berlabel yang keluar gudang wajib dipindai. Per item + lot:
     * wajib = min(jumlah keluar, isi label Di gudang, saldo gudang) — stok lama
     * tanpa label boleh keluar tanpa pindai, dan label yatim (stok sudah keluar
     * lewat ADJ/opname) tidak menghalangi.
     *
     * @param  array<int, array{item_id: int, lot_id: ?int, qty: float, claimed: float, label: string}>  $groups
     */
    public function assertCoverage(int $warehouseId, array $groups): void
    {
        foreach ($groups as $g) {
            $wajib = $this->required($warehouseId, (int) $g['item_id'], $g['lot_id'], (float) $g['qty']);

            if ($wajib > self::EPS && (float) $g['claimed'] + self::EPS < $wajib) {
                throw LabelRuleException::rule('BR-LBL-04', $g['label'].': barang berlabel wajib dipindai saat keluar gudang — baru '
                    .self::angka((float) $g['claimed']).' dari '.self::angka($wajib).'; pindai label untuk '.self::angka($wajib - (float) $g['claimed']).' lagi.');
            }
        }
    }

    /**
     * Klaim yang sudah diperiksa → label berkurang isinya; habis = Keluar.
     *
     * @param  array<int, array{label: PackageLabel, qty: float}>  $resolved
     * @param  array{type: string, id: int, line_id: ?int, number: ?string}  $doc
     */
    /**
     * A-299: jumlah yang wajib tertutup pindaian label bila $qty item+lot ini
     * keluar dari gudang = min(jumlah keluar, isi label Di gudang, saldo gudang).
     * Stok lama tanpa label dan label yatim (saldo sudah habis) tidak menahan.
     */
    public function required(int $warehouseId, int $itemId, ?int $lotId, float $qty): float
    {
        $berlabel = $this->labelled($warehouseId, $itemId, $lotId);

        if ($berlabel <= self::EPS || $qty <= self::EPS) {
            return 0.0;
        }

        $saldo = (float) StockBalance::query()->withoutGlobalScopes()
            ->where('item_id', $itemId)
            ->when($lotId !== null, fn ($q) => $q->where('lot_id', $lotId))
            ->whereHas('bin', fn ($b) => $b->withoutGlobalScopes()->where('warehouse_id', $warehouseId))
            ->sum('qty_base');

        return round(min($qty, $berlabel, max(0.0, $saldo)), 4);
    }

    public function issue(array $resolved, array $doc, ?User $actor): void
    {
        foreach ($resolved as ['label' => $l, 'qty' => $qty]) {
            $l = PackageLabel::query()->lockForUpdate()->findOrFail($l->id);
            $this->claimable($l);

            $sisa = round((float) $l->qty_remaining - $qty, 4);

            if ($sisa < -self::EPS) {
                throw LabelRuleException::rule('BR-LBL-03', 'Label '.$l->code.' sudah diambil dokumen lain; pindai ulang.');
            }

            $l->forceFill([
                'qty_remaining' => max(0.0, $sisa),
                'status' => $sisa <= self::EPS ? PackageLabelStatus::Issued : PackageLabelStatus::InStock,
            ])->save();

            $this->catat($l, -$qty, $doc, $actor, ['warehouse_id' => $l->warehouse_id, 'from_bin_id' => $l->bin_id]);
            $this->segarkanInduk($l->parent_id);
        }
    }

    /** A-299: nomor SJ ditempel ke riwayat label yang dipetik untuk SJ itu. */
    public function attachShipment(Shipment $sj): void
    {
        $baris = $sj->lines()->whereNotNull('pick_task_line_id')->pluck('pick_task_line_id')->all();

        if ($baris === []) {
            return;
        }

        PackageLabelMove::query()->where('document_type', 'pick_task')->whereIn('document_line_id', $baris)
            ->whereNull('shipment_id')->update(['shipment_id' => $sj->id]);
    }

    /**
     * A-300: label yang dipetik utuh untuk SJ ke gudang/Gudang Site lain (atau SJ
     * balik retur) menjadi Di gudang lagi di gudang tujuan saat GRN menerimanya,
     * urut pindai sampai jumlah yang diterima.
     *
     * @param  array{type: string, id: int, line_id: ?int, number: ?string}  $doc
     */
    public function reopenFromPickLine(int $pickLineId, int $warehouseId, ?int $binId, float $qty, array $doc, ?User $actor): void
    {
        $sisa = round($qty, 4);

        $keluar = PackageLabelMove::query()->where('document_type', 'pick_task')
            ->where('document_line_id', $pickLineId)->where('qty_change', '<', 0)->orderBy('id')->get();

        foreach ($keluar as $m) {
            $l = PackageLabel::query()->lockForUpdate()->find($m->package_label_id);
            $diambil = -(float) $m->qty_change;

            if ($l === null || $l->status !== PackageLabelStatus::Issued || $diambil - $sisa > self::EPS) {
                continue;
            }

            $l->forceFill([
                'qty_remaining' => $diambil,
                'status' => PackageLabelStatus::InStock,
                'warehouse_id' => $warehouseId,
                'bin_id' => $binId,
            ])->save();

            $this->catat($l, $diambil, $doc, $actor, ['warehouse_id' => $warehouseId, 'to_bin_id' => $binId, 'notes' => 'transfer']);
            $sisa = round($sisa - $diambil, 4);

            if ($sisa <= self::EPS) {
                break;
            }
        }
    }

    /**
     * Pembalik pemakaian: label yang keluar lewat baris ISU asal kembali berisi.
     *
     * @param  array{type: string, id: int, line_id: ?int, number: ?string}  $doc
     */
    public function reopenForIssueLine(int $originalLineId, array $doc, ?User $actor): void
    {
        $keluar = PackageLabelMove::query()->where('document_type', 'material_issue')
            ->where('document_line_id', $originalLineId)->where('qty_change', '<', 0)->orderBy('id')->get();

        foreach ($keluar as $m) {
            $l = PackageLabel::query()->lockForUpdate()->find($m->package_label_id);

            if ($l === null || $l->status === PackageLabelStatus::Cancelled) {
                continue;
            }

            $kembali = -(float) $m->qty_change;

            $l->forceFill([
                'qty_remaining' => round((float) $l->qty_remaining + $kembali, 4),
                'status' => PackageLabelStatus::InStock,
            ])->save();

            $this->catat($l, $kembali, $doc, $actor, ['warehouse_id' => $l->warehouse_id, 'to_bin_id' => $l->bin_id]);
            $this->segarkanInduk($l->parent_id);
        }
    }

    /**
     * Put-away memindah lokasi label yang masih di bin terima baris GRN itu.
     *
     * @param  array{type: string, id: int, line_id: ?int, number: ?string}  $doc
     */
    public function putAway(int $receiptLineId, int $fromBinId, int $toBinId, array $doc, ?User $actor): void
    {
        $dariGrn = PackageLabelMove::query()->where('document_type', 'goods_receipt')
            ->where('document_line_id', $receiptLineId)->where('qty_change', '>', 0)->pluck('package_label_id');

        $label = PackageLabel::query()->inStock()->where('bin_id', $fromBinId)
            ->where(fn ($q) => $q->where('goods_receipt_line_id', $receiptLineId)->orWhereIn('id', $dariGrn))
            ->lockForUpdate()->get();

        foreach ($label as $l) {
            $l->forceFill(['bin_id' => $toBinId])->save();
            $this->catat($l, 0.0, $doc, $actor, ['warehouse_id' => $l->warehouse_id, 'from_bin_id' => $fromBinId, 'to_bin_id' => $toBinId]);
        }
    }

    /**
     * Keputusan #11 (A-378): barang retur yang dipilah **layak** ke bin
     * penyimpanan — label kemasan yang dibuka lagi oleh GRN retur baris itu dan
     * masih di bin Retur ikut pindah bin, utuh per label sampai jumlahnya.
     *
     * @param  array<int, int>  $receiptLineIds  baris GRN retur untuk baris RET itu
     * @param  array{type: string, id: int, line_id: ?int, number: ?string}  $doc
     */
    public function moveReturn(array $receiptLineIds, int $fromBinId, int $toBinId, float $qty, array $doc, ?User $actor): void
    {
        if ($receiptLineIds === [] || $qty <= self::EPS || $fromBinId === $toBinId) {
            return;
        }

        $ids = PackageLabelMove::query()->where('document_type', 'goods_receipt')
            ->whereIn('document_line_id', $receiptLineIds)->where('qty_change', '>', 0)->orderBy('id')->pluck('package_label_id')->unique();
        $sisa = round($qty, 4);

        foreach ($ids as $id) {
            $l = PackageLabel::query()->inStock()->where('bin_id', $fromBinId)->lockForUpdate()->find($id);

            if ($l === null || (float) $l->qty_remaining <= self::EPS || (float) $l->qty_remaining - $sisa > self::EPS) {
                continue;
            }

            $l->forceFill(['bin_id' => $toBinId])->save();
            $this->catat($l, 0.0, $doc, $actor, ['warehouse_id' => $l->warehouse_id, 'from_bin_id' => $fromBinId, 'to_bin_id' => $toBinId]);
            $sisa = round($sisa - (float) $l->qty_remaining, 4);

            if ($sisa <= self::EPS) {
                break;
            }
        }
    }

    /**
     * A-301: barang retur yang dipilah rusak/waste — label yang dibuka lagi oleh
     * GRN retur baris itu dibatalkan sebesar jumlahnya (asal vendornya tetap
     * terbaca untuk laporan barang bermasalah).
     *
     * @param  array<int, int>  $receiptLineIds  baris GRN retur untuk baris RET itu
     * @param  array{type: string, id: int, line_id: ?int, number: ?string}  $doc
     */
    public function writeOffReturn(array $receiptLineIds, float $qty, ?int $reasonId, array $doc, ?User $actor): void
    {
        if ($receiptLineIds === [] || $qty <= self::EPS) {
            return;
        }

        $ids = PackageLabelMove::query()->where('document_type', 'goods_receipt')
            ->whereIn('document_line_id', $receiptLineIds)->where('qty_change', '>', 0)->orderBy('id')->pluck('package_label_id');
        $sisa = round($qty, 4);

        foreach ($ids as $id) {
            $l = PackageLabel::query()->inStock()->lockForUpdate()->find($id);

            if ($l === null || (float) $l->qty_remaining <= self::EPS) {
                continue;
            }

            $ambil = min((float) $l->qty_remaining, $sisa);
            $tinggal = round((float) $l->qty_remaining - $ambil, 4);

            $l->forceFill(array_merge(
                ['qty_remaining' => $tinggal],
                $tinggal <= self::EPS ? ['status' => PackageLabelStatus::Cancelled, 'cancel_reason_id' => $reasonId, 'cancelled_at' => now(), 'cancelled_by' => $actor?->id] : [],
            ))->save();

            $this->catat($l, -$ambil, $doc, $actor, ['warehouse_id' => $l->warehouse_id, 'reason_code_id' => $reasonId]);
            $sisa = round($sisa - $ambil, 4);

            if ($sisa <= self::EPS) {
                break;
            }
        }
    }

    /**
     * Induk ikut status isinya: Keluar bila tidak berisi dan tidak ada label isi
     * Di gudang di gudangnya; Di gudang lagi bila salah satunya kembali.
     */
    public function segarkanInduk(?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        $induk = PackageLabel::query()->lockForUpdate()->find($parentId);

        if ($induk === null || $induk->status === PackageLabelStatus::Cancelled) {
            return;
        }

        $masihAda = (float) $induk->qty_remaining > self::EPS
            || PackageLabel::query()->where('parent_id', $induk->id)->inStock()->where('warehouse_id', $induk->warehouse_id)->exists();

        $status = $masihAda ? PackageLabelStatus::InStock : PackageLabelStatus::Issued;

        if ($induk->status !== $status) {
            $induk->forceFill(['status' => $status])->save();
        }
    }

    /**
     * @param  array{type: ?string, id: ?int, line_id: ?int, number: ?string}  $doc
     * @param  array<string, mixed>  $extra
     */
    public function catat(PackageLabel $label, float $change, array $doc, ?User $actor, array $extra = []): PackageLabelMove
    {
        return PackageLabelMove::create($extra + [
            'package_label_id' => $label->id,
            'qty_change' => round($change, 4),
            'document_type' => $doc['type'] ?? null,
            'document_id' => $doc['id'] ?? null,
            'document_line_id' => $doc['line_id'] ?? null,
            'document_number' => $doc['number'] ?? null,
            'performed_by' => $actor?->id,
            'occurred_at' => now(),
        ]);
    }

    /** @return Collection<int, PackageLabel> label Di gudang untuk item di gudang, tertua dulu (untuk layar) */
    public function inStockFor(int $warehouseId, int $itemId): Collection
    {
        return PackageLabel::query()->inStock()->where('warehouse_id', $warehouseId)->where('item_id', $itemId)
            ->where('qty_remaining', '>', 0)->orderBy('id')->get();
    }

    public static function angka(float $n): string
    {
        return rtrim(rtrim(number_format($n, 4, ',', '.'), '0'), ',');
    }
}

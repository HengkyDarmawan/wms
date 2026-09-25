<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Support;

use App\Domain\Conversion\Enums\ConversionOutputKind;
use App\Domain\Conversion\Enums\ConversionType;
use App\Domain\Conversion\Exceptions\ConversionRuleException;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Enums\TrackingMode;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\Uom;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Collection;

/**
 * Baris hasil CNV — output, offcut, waste, kerf (A-154, A-156).
 *
 * - Sisa (offcut/waste/kerf) selalu item input induknya; induk kosong = input
 *   pertama. Offcut hanya untuk item per potong; offcut di bawah
 *   `min_offcut_length` otomatis menjadi waste (BR-CNV-03, `auto_waste`).
 * - Output boleh item lain (habis pakai, bukan serial). Item per potong: satu
 *   baris = satu potongan; `count` menggandakan baris (maks 100).
 * - Output berlot mewarisi lot input item yang sama, selain itu `lot_no` wajib
 *   (lot dibuat saat CNV selesai).
 * - Output & offcut masuk bin penyimpanan (bawaan: bin input induk); waste ke
 *   bin Waste gudang berkondisi Rusak; kerf tanpa bin dan tanpa pergerakan.
 *
 * Neraca ukuran (BR-CNV-02) wajib untuk potong dan ganti kemasan: semua item
 * satu satuan dasar, |Σ input − Σ hasil| ≤ max(0,00005; pembulatan satuan / 2).
 */
class ConversionLines
{
    public const MAKS_GANDA = 100;

    /**
     * @param  array<string, array<string, mixed>>  $inputs  hasil ConvertibleStock::normalize (kunci => baris)
     * @param  array<int, array<string, mixed>>  $outputs  isian form
     * @return array<int, array<string, mixed>> baris `conversion_outputs` + `parent_key`
     */
    public function normalize(Warehouse $gudang, ConversionType $type, array $inputs, array $outputs): array
    {
        if ($inputs === []) {
            throw ConversionRuleException::field('BR-CNV-02', 'inputs', 'Pilih minimal satu input dari stok Tersedia gudang ini.');
        }

        $items = Item::query()->with('baseUom')->whereIn('id', array_unique(array_merge(
            array_map(fn (array $i) => (int) $i['item_id'], $inputs),
            array_map(fn (array $o) => is_numeric($o['item_id'] ?? null) ? (int) $o['item_id'] : 0, $outputs),
        )))->get()->keyBy('id');

        $kunciPertama = (string) array_key_first($inputs);
        $binWaste = null;
        $baris = [];

        foreach (array_values($outputs) as $n => $isian) {
            $jenis = ConversionOutputKind::tryFrom((string) ($isian['kind'] ?? ''));
            $jumlah = is_numeric($isian['qty_base'] ?? null) ? round((float) $isian['qty_base'], 4) : 0.0;
            $label = 'Hasil baris '.($n + 1);

            if ($jenis === null && $jumlah == 0.0 && trim((string) ($isian['item_id'] ?? '')) === '') {
                continue;
            }

            if ($jenis === null) {
                throw ConversionRuleException::field('BR-GEN-01', 'outputs', $label.': jenis hasil wajib dipilih.');
            }

            if ($jumlah <= 0) {
                throw ConversionRuleException::field('BR-LED-02', 'outputs', $label.': jumlah harus lebih besar dari nol.');
            }

            $kunciInduk = trim((string) ($isian['parent'] ?? '')) ?: $kunciPertama;
            $induk = $inputs[$kunciInduk] ?? throw ConversionRuleException::field('BR-CNV-04', 'outputs', $label.': input induk tidak ada di daftar input CNV ini.');
            $itemInduk = $items->get((int) $induk['item_id']);

            $item = $jenis->isRemainder()
                ? $itemInduk
                : $items->get(is_numeric($isian['item_id'] ?? null) ? (int) $isian['item_id'] : 0);

            if ($item === null) {
                throw ConversionRuleException::field('BR-CNV-02', 'outputs', $label.': item output wajib dipilih.');
            }

            $this->periksaItem($item, $label);

            $ganda = $item->tracksPiece() && $jenis !== ConversionOutputKind::Kerf
                ? max(1, is_numeric($isian['count'] ?? null) ? (int) $isian['count'] : 1)
                : 1;

            if ($ganda > self::MAKS_GANDA) {
                throw ConversionRuleException::field('BR-CNV-02', 'outputs', $label.': maksimal '.self::MAKS_GANDA.' potongan per baris.');
            }

            $autoWaste = false;

            if ($jenis === ConversionOutputKind::Offcut) {
                if (! $item->tracksPiece()) {
                    throw ConversionRuleException::field('BR-CNV-03', 'outputs', $label.': offcut hanya untuk item per potong; sisa item lain dicatat sebagai waste atau kerf.');
                }

                // BR-CNV-03: sisa di bawah panjang minimum menjadi waste.
                if ($item->min_offcut_length !== null && $jumlah < (float) $item->min_offcut_length) {
                    $jenis = ConversionOutputKind::Waste;
                    $autoWaste = true;
                }
            }

            [$lotId, $lotNo] = $this->lot($jenis, $item, $induk, $isian, $label);

            $bin = match ($jenis) {
                ConversionOutputKind::Kerf => null,
                ConversionOutputKind::Waste => $binWaste ??= $this->binWaste($gudang),
                default => $this->binSimpan($gudang, $isian['bin_id'] ?? null, (int) $induk['bin_id'], $label),
            };

            $alasan = $jenis === ConversionOutputKind::Waste ? $this->alasanWaste($isian['reason_code_id'] ?? null, $label) : null;

            for ($i = 0; $i < $ganda; $i++) {
                $baris[] = [
                    'output_kind' => $jenis,
                    'item_id' => (int) $item->id,
                    'bin_id' => $bin?->id,
                    'stock_status' => $jenis->stockStatus(),
                    'qty_base' => $jumlah,
                    'lot_id' => $lotId,
                    'lot_no' => $lotNo,
                    'reason_code_id' => $alasan,
                    'auto_waste' => $autoWaste,
                    'parent_key' => $kunciInduk,
                ];
            }
        }

        $this->assertBalanced($type, array_values($inputs), $baris);

        return $baris;
    }

    /**
     * BR-CNV-02 dan A-156. Dipanggil saat simpan dan diulang saat CNV selesai.
     *
     * @param  array<int, array<string, mixed>>  $inputs  item_id, qty_base
     * @param  array<int, array<string, mixed>>  $outputs  output_kind, item_id, qty_base
     */
    public function assertBalanced(ConversionType $type, array $inputs, array $outputs): void
    {
        $hasil = array_filter($outputs, fn (array $o) => in_array(self::jenis($o), [ConversionOutputKind::Output, ConversionOutputKind::Offcut], true));

        if ($hasil === []) {
            throw ConversionRuleException::field('BR-CNV-02', 'outputs', 'CNV wajib punya minimal satu output atau offcut.');
        }

        if (! $type->requiresSizeBalance()) {
            return;
        }

        $itemIds = array_unique(array_merge(
            array_map(fn (array $r) => (int) $r['item_id'], $inputs),
            array_map(fn (array $r) => (int) $r['item_id'], $outputs),
        ));
        $satuan = Item::query()->whereIn('id', $itemIds)->pluck('base_uom_id')->unique()->values();

        if ($satuan->count() > 1) {
            throw ConversionRuleException::field('BR-CNV-02', 'outputs', 'Konversi '.$type->label().' menuntut semua input dan hasil dalam satu satuan dasar; rakit/bongkar antar satuan menunggu resep konversi (A-156).');
        }

        $masuk = round(array_sum(array_map(fn (array $r) => (float) $r['qty_base'], $inputs)), 4);
        $keluar = round(array_sum(array_map(fn (array $r) => (float) $r['qty_base'], $outputs)), 4);
        $toleransi = $this->toleransi((int) $satuan->first());

        if (abs($masuk - $keluar) > $toleransi) {
            $kode = Uom::query()->whereKey((int) $satuan->first())->value('code');

            throw ConversionRuleException::field('BR-CNV-02', 'outputs', 'Neraca ukuran tidak seimbang: input '.ConvertibleStock::angka($masuk).' '.$kode
                .' ≠ output + offcut + waste + kerf '.ConvertibleStock::angka($keluar).' '.$kode.' (selisih '.ConvertibleStock::angka(round($masuk - $keluar, 4)).').');
        }
    }

    /**
     * @param  iterable<int, array<string, mixed>|object>  $inputs
     * @param  iterable<int, array<string, mixed>|object>  $outputs
     * @return array<string, float>
     */
    public function totals(iterable $inputs, iterable $outputs): array
    {
        $total = ['total_input' => 0.0, 'total_output' => 0.0, 'total_offcut' => 0.0, 'total_waste' => 0.0, 'total_kerf' => 0.0];

        foreach ($inputs as $i) {
            $total['total_input'] += (float) data_get($i, 'qty_base');
        }

        foreach ($outputs as $o) {
            $total['total_'.self::jenis($o)->value] += (float) data_get($o, 'qty_base');
        }

        return array_map(fn (float $n) => round($n, 4), $total);
    }

    /** Bin Waste sistem gudang (BR-WH-02). */
    public function binWaste(Warehouse $gudang): Bin
    {
        return Bin::query()->withoutGlobalScopes()
            ->where('warehouse_id', $gudang->id)
            ->where('bin_type', BinType::Waste->value)
            ->orderBy('id')->first()
            ?? throw ConversionRuleException::rule('BR-WH-02', 'Gudang '.$gudang->code.' belum punya bin Waste. Simpan ulang gudangnya untuk membuatnya.');
    }

    /** @return Collection<int, Bin> bin penyimpanan aktif gudang, tujuan output & offcut */
    public function storageBins(Warehouse $gudang): Collection
    {
        return Bin::query()->withoutGlobalScopes()
            ->where('warehouse_id', $gudang->id)
            ->where('bin_type', BinType::Storage->value)
            ->where('bin_status', BinStatus::Active->value)
            ->orderBy('code')->get(['id', 'code', 'warehouse_id', 'bin_type', 'bin_status']);
    }

    private function periksaItem(Item $item, string $label): void
    {
        if ($item->isAsset()) {
            throw ConversionRuleException::field('BR-STK-08', 'outputs', $label.': item '.$item->code.' adalah aset; aset tidak dikonversi.');
        }

        if ($item->tracksSerial()) {
            throw ConversionRuleException::field('BR-LED-03', 'outputs', $label.': item berserial '.$item->code.' tidak menjadi hasil konversi di Fase 1 (A-154).');
        }

        if ($item->status !== ItemStatus::Active) {
            throw ConversionRuleException::field('BR-MST-05', 'outputs', $label.': item '.$item->code.' tidak aktif.');
        }
    }

    /**
     * @param  array<string, mixed>  $induk
     * @param  array<string, mixed>  $isian
     * @return array{0: ?int, 1: ?string}
     */
    private function lot(ConversionOutputKind $jenis, Item $item, array $induk, array $isian, string $label): array
    {
        if ($item->tracking_mode !== TrackingMode::Lot || $jenis === ConversionOutputKind::Kerf) {
            return [null, null];
        }

        // Item sama dengan induk berlot: lotnya diwarisi.
        if ((int) $item->id === (int) $induk['item_id'] && $induk['lot_id'] !== null) {
            return [(int) $induk['lot_id'], null];
        }

        $nomor = trim((string) ($isian['lot_no'] ?? ''));

        if ($nomor === '') {
            throw ConversionRuleException::field('BR-LED-03', 'outputs', $label.': nomor lot output '.$item->code.' wajib diisi.');
        }

        return [null, mb_substr($nomor, 0, 60)];
    }

    private function binSimpan(Warehouse $gudang, mixed $binId, int $binInduk, string $label): Bin
    {
        $pilih = is_numeric($binId) && (int) $binId > 0 ? (int) $binId : $binInduk;
        $bin = $this->storageBins($gudang)->firstWhere('id', $pilih);

        if ($bin === null) {
            throw ConversionRuleException::field('BR-STK-02', 'outputs', $label.': bin tujuan harus bin penyimpanan aktif di gudang '.$gudang->code.'.');
        }

        return $bin;
    }

    private function alasanWaste(mixed $id, string $label): ?int
    {
        if (! is_numeric($id) || (int) $id <= 0) {
            return null;
        }

        $sah = ReasonCode::query()->whereKey((int) $id)->where('context', ReasonContext::Waste->value)->exists();

        if (! $sah) {
            throw ConversionRuleException::field('BR-GEN-02', 'outputs', $label.': alasan waste harus dari master Alasan konteks Waste.');
        }

        return (int) $id;
    }

    private function toleransi(int $uomId): float
    {
        $pembulatan = (float) (Uom::query()->whereKey($uomId)->value('rounding') ?? 0);

        return max(0.00005, $pembulatan / 2);
    }

    /** @param  array<string, mixed>|object  $baris */
    private static function jenis(array|object $baris): ConversionOutputKind
    {
        $nilai = data_get($baris, 'output_kind');

        return $nilai instanceof ConversionOutputKind ? $nilai : ConversionOutputKind::from((string) $nilai);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Master\Livewire\Concerns;

use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Support\QtyFormat;
use App\Domain\Master\Support\UnitInput;
use Illuminate\Support\Collection;

/**
 * A-291/A-292: pemilih satuan di baris dokumen (GRN, Permintaan material,
 * Retur). Baris layar memakai kunci `uom` ('' = satuan dasar, id kemasan,
 * atau 'lain'), `uom_lain` (satuan untuk kemasan baru), `uom_factor` (jumlah
 * isi), `uom_isi` (satuan isi: '' = satuan dasar atau id kemasan aktif item —
 * kalimat "1 DUS berisi 40 PACK", A-357), dan `ingat` (simpan ke kemasan item).
 *
 * Pasangannya: partial `livewire.master.partials.unit-picker` dan
 * {@see UnitInput} di aksi dokumen.
 */
trait PicksItemUnit
{
    /**
     * Pilihan satuan per item: satuan dasar + kemasan aktif. `per_unit` =
     * serial/potongan, yang selalu diisi per unit tanpa kemasan.
     *
     * @param  array<int, int|string>  $itemIds
     * @return array<int, array{base: string, per_unit: bool, codes: array<int, string>, factors: array<int, float>}>
     */
    protected function opsiSatuan(array $itemIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $itemIds))));

        if ($ids === []) {
            return [];
        }

        return Item::query()->with('baseUom:id,code', 'activeConversions.uom:id,code')->whereIn('id', $ids)->get()
            ->mapWithKeys(function (Item $item) {
                $kemasan = collect($item->unitOptions())->filter(fn (array $o) => $o['uom_id'] !== null);

                return [(int) $item->id => [
                    'base' => (string) $item->baseUom?->code,
                    'per_unit' => $item->tracksPiece() || $item->tracksSerial(),
                    'codes' => $kemasan->mapWithKeys(fn (array $o) => [$o['uom_id'] => $o['code']])->all(),
                    'factors' => $kemasan->mapWithKeys(fn (array $o) => [$o['uom_id'] => $o['factor']])->all(),
                ]];
            })->all();
    }

    /** @return Collection<int, Uom> satuan yang bisa dipakai sebagai kemasan baru */
    protected function satuanKemasan(): Collection
    {
        return Uom::query()->active()->orderBy('code')->get(['id', 'code', 'name']);
    }

    /**
     * Isian baris layar → kunci aksi (`uom_id`, `uom_factor` dalam satuan dasar,
     * `remember_uom`, dan kalimat isi `uom_content_qty`/`uom_content_uom_id`
     * untuk kemasan yang diingat, A-357).
     *
     * @param  array<string, mixed>  $row
     * @param  array{base: string, per_unit: bool, codes: array<int, string>, factors: array<int, float>}|null  $opsi
     * @return array{uom_id: ?int, uom_factor: ?string, remember_uom: bool, uom_content_qty?: ?string, uom_content_uom_id?: ?int}
     */
    protected function isianSatuan(array $row, ?array $opsi = null): array
    {
        $pilih = (string) ($row['uom'] ?? '');
        $uom = $pilih === 'lain' ? (string) ($row['uom_lain'] ?? '') : $pilih;
        $isi = $pilih === 'lain' ? $this->isiKemasanLain($row, $opsi) : null;

        return [
            'uom_id' => is_numeric($uom) ? (int) $uom : null,
            // Belum lengkap → kosong, supaya aksi menolak dengan pesan "Isi berapa …".
            'uom_factor' => $pilih === 'lain' ? ($isi === null ? '' : (string) $isi['faktor']) : null,
            'remember_uom' => $pilih === 'lain' && (bool) ($row['ingat'] ?? false),
        ] + ($isi === null || $isi['uom_id'] === null ? [] : ['uom_content_qty' => (string) $isi['qty'], 'uom_content_uom_id' => $isi['uom_id']]);
    }

    /**
     * A-357: isian "Kemasan lain" → isi 1 kemasan dalam satuan dasar. Satuan
     * isi kemasan aktif item dikalikan isinya; null bila belum lengkap.
     *
     * @param  array<string, mixed>  $row
     * @param  array{base: string, per_unit: bool, codes: array<int, string>, factors: array<int, float>}|null  $opsi
     * @return array{qty: float, uom_id: ?int, faktor: float}|null
     */
    protected function isiKemasanLain(array $row, ?array $opsi): ?array
    {
        $faktor = UnitInput::faktorLain($row, $opsi);

        if ($faktor <= 0) {
            return null;
        }

        return [
            'qty' => (float) str_replace(',', '.', trim((string) $row['uom_factor'])),
            'uom_id' => is_numeric($row['uom_isi'] ?? null) ? (int) $row['uom_isi'] : null,
            'faktor' => $faktor,
        ];
    }

    /**
     * Faktor satuan terpilih (1 = satuan dasar), atau null bila belum lengkap.
     *
     * @param  array<string, mixed>  $row
     * @param  array{base: string, per_unit: bool, codes: array<int, string>, factors: array<int, float>}|null  $opsi
     */
    protected function faktorSatuan(array $row, ?array $opsi): ?float
    {
        $pilih = (string) ($row['uom'] ?? '');

        if ($pilih === '') {
            return 1.0;
        }

        if ($pilih === 'lain') {
            return $this->isiKemasanLain($row, $opsi)['faktor'] ?? null;
        }

        return $opsi['factors'][(int) $pilih] ?? null;
    }

    /**
     * Teks hasil untuk layar: "10 DUS = 120 BOX", atau null bila satuan dasar.
     *
     * @param  array<string, mixed>  $row
     * @param  array{base: string, per_unit: bool, codes: array<int, string>, factors: array<int, float>}|null  $opsi
     */
    protected function hasilSatuan(array $row, mixed $qty, ?array $opsi): ?string
    {
        $pilih = (string) ($row['uom'] ?? '');
        $faktor = $this->faktorSatuan($row, $opsi);

        if ($pilih === '' || $opsi === null || $faktor === null || ! is_numeric($qty)) {
            return null;
        }

        $kode = $pilih === 'lain'
            ? Uom::query()->whereKey((int) ($row['uom_lain'] ?? 0))->value('code')
            : ($opsi['codes'][(int) $pilih] ?? null);

        return $kode === null ? null
            : QtyFormat::withUnit($qty, $kode).' = '.QtyFormat::withUnit(round((float) $qty * $faktor, 4), $opsi['base']);
    }

    /**
     * Isian satuan dari baris tersimpan (ubah draf): kemasan aktif dipilih
     * langsung; kemasan yang kini tidak aktif dibuka sebagai "Kemasan lain".
     *
     * @return array{uom: string, uom_lain: string, uom_factor: string, ingat: bool}
     */
    protected function satuanTersimpan(?int $uomId, mixed $faktor, ?array $opsi): array
    {
        if ($uomId === null) {
            return ['uom' => '', 'uom_lain' => '', 'uom_factor' => '', 'uom_isi' => '', 'ingat' => false];
        }

        if (isset($opsi['factors'][$uomId])) {
            return ['uom' => (string) $uomId, 'uom_lain' => '', 'uom_factor' => '', 'uom_isi' => '', 'ingat' => false];
        }

        return ['uom' => 'lain', 'uom_lain' => (string) $uomId, 'uom_factor' => (string) (float) $faktor, 'uom_isi' => '', 'ingat' => false];
    }
}

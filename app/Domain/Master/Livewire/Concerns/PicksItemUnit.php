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
 * atau 'lain'), `uom_lain` (satuan untuk kemasan baru), `uom_factor`
 * ("1 DUS = … BOX"), dan `ingat` (simpan ke kemasan item).
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
     * Isian baris layar → kunci aksi (`uom_id`, `uom_factor`, `remember_uom`).
     *
     * @param  array<string, mixed>  $row
     * @return array{uom_id: ?int, uom_factor: ?string, remember_uom: bool}
     */
    protected function isianSatuan(array $row): array
    {
        $pilih = (string) ($row['uom'] ?? '');
        $uom = $pilih === 'lain' ? (string) ($row['uom_lain'] ?? '') : $pilih;

        return [
            'uom_id' => is_numeric($uom) ? (int) $uom : null,
            'uom_factor' => $pilih === 'lain' ? (string) ($row['uom_factor'] ?? '') : null,
            'remember_uom' => $pilih === 'lain' && (bool) ($row['ingat'] ?? false),
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
            return is_numeric($row['uom_factor'] ?? null) && (float) $row['uom_factor'] > 0 ? (float) $row['uom_factor'] : null;
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
            return ['uom' => '', 'uom_lain' => '', 'uom_factor' => '', 'ingat' => false];
        }

        if (isset($opsi['factors'][$uomId])) {
            return ['uom' => (string) $uomId, 'uom_lain' => '', 'uom_factor' => '', 'ingat' => false];
        }

        return ['uom' => 'lain', 'uom_lain' => (string) $uomId, 'uom_factor' => (string) (float) $faktor, 'ingat' => false];
    }
}

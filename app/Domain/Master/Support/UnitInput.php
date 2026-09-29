<?php

declare(strict_types=1);

namespace App\Domain\Master\Support;

use App\Domain\Access\Models\User;
use App\Domain\Master\Actions\RememberItemPackaging;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\ItemUomConversion;
use App\Domain\Master\Models\Uom;
use DomainException;

/**
 * A-291: jumlah yang diketik staf dalam satuan dasar atau kemasan item
 * (mis. 10 DUS) → jumlah satuan dasar. Faktor diambil dari kemasan aktif
 * item, atau dari isian di tempat "1 DUS = … BOX" untuk kemasan yang belum
 * ada (opsional disimpan lewat {@see RememberItemPackaging}, A-292).
 *
 * Galat dilempar sebagai {@see DomainException}; aksi pemanggil
 * menerjemahkannya ke exception domainnya sendiri beserta nama field.
 */
class UnitInput
{
    /**
     * @param  array{qty: float, uom_id: int}|null  $isi  kalimat isi kemasan baru yang diingat (A-357)
     * @return array{uom_id: ?int, qty_input: float, uom_qty_base: float, qty_base: float}
     */
    public static function resolve(
        Item $item,
        mixed $qty,
        mixed $uomId = null,
        mixed $inlineFactor = null,
        bool $remember = false,
        ?User $actor = null,
        ?string $sumber = null,
        ?array $isi = null,
    ): array {
        $jumlah = is_numeric($qty) ? (float) $qty : 0.0;
        $uom = is_numeric($uomId) ? (int) $uomId : null;

        if ($uom === null || $uom === (int) $item->base_uom_id) {
            return ['uom_id' => null, 'qty_input' => $jumlah, 'uom_qty_base' => 1.0, 'qty_base' => round($jumlah, 4)];
        }

        // BR-STK-09: item per potong dicatat per panjang; kemasan tidak dipakai di baris dokumen.
        if ($item->tracksPiece()) {
            throw new DomainException('Item per potong hanya diisi dalam satuan dasar ('.$item->baseUom?->code.').');
        }

        $kemasan = ItemUomConversion::query()->where('item_id', $item->id)->where('uom_id', $uom)->where('is_active', true)->first();
        $faktor = $kemasan !== null ? (float) $kemasan->qty_base : (is_numeric($inlineFactor) ? (float) $inlineFactor : 0.0);

        if ($faktor <= 0) {
            throw new DomainException('Isi berapa '.$item->baseUom?->code.' dalam 1 kemasan untuk item '.$item->code.'.');
        }

        if ($kemasan === null) {
            $satuan = Uom::query()->whereKey($uom)->where('is_active', true)->first()
                ?? throw new DomainException('Satuan kemasan tidak dikenal.');

            if ($remember) {
                app(RememberItemPackaging::class)->handle($item, $satuan, $faktor, $actor, $sumber, $isi);
            }
        }

        return ['uom_id' => $uom, 'qty_input' => $jumlah, 'uom_qty_base' => round($faktor, 4), 'qty_base' => round($jumlah * $faktor, 4)];
    }

    /**
     * Tampilan (A-291): kode satuan yang sedang dipilih di baris layar —
     * satuan dasar, kemasan aktif, atau satuan "Kemasan lain…"; null bila
     * "Kemasan lain" belum memilih satuan.
     *
     * @param  array<string, mixed>  $row  kunci `uom`, `uom_lain`
     * @param  array{base: string, codes: array<int, string>}|null  $opsi
     * @param  iterable<Uom>  $satuanLain
     */
    public static function selectedCode(array $row, ?array $opsi, iterable $satuanLain = []): ?string
    {
        $pilih = (string) ($row['uom'] ?? '');

        if ($opsi === null) {
            return null;
        }

        if ($pilih === '') {
            return $opsi['base'];
        }

        if ($pilih === 'lain') {
            foreach ($satuanLain as $u) {
                if ((string) $u->id === (string) ($row['uom_lain'] ?? '')) {
                    return (string) $u->code;
                }
            }

            return null;
        }

        return $opsi['codes'][(int) $pilih] ?? null;
    }

    /**
     * Tampilan: "1 DUS = 100 PCS" untuk $qty dalam satuan terpilih, atau null
     * bila satuan dasar, isi kemasan belum lengkap, atau jumlah kosong.
     *
     * @param  array<string, mixed>  $row
     * @param  array{base: string, codes: array<int, string>, factors: array<int, float>}|null  $opsi
     * @param  iterable<Uom>  $satuanLain
     */
    public static function baseText(array $row, ?array $opsi, iterable $satuanLain, mixed $qty): ?string
    {
        $pilih = (string) ($row['uom'] ?? '');
        $kode = self::selectedCode($row, $opsi, $satuanLain);

        if ($pilih === '' || $kode === null || ! is_numeric($qty) || (float) $qty <= 0) {
            return null;
        }

        $faktor = $pilih === 'lain'
            ? self::faktorLain($row, $opsi)
            : (float) ($opsi['factors'][(int) $pilih] ?? 0);

        return $faktor <= 0 ? null
            : QtyFormat::withUnit($qty, $kode).' = '.QtyFormat::withUnit(round((float) $qty * $faktor, 4), $opsi['base']);
    }

    /**
     * A-357: isi 1 "Kemasan lain" dalam satuan dasar — `uom_factor` dikali isi
     * satuan isi (`uom_isi`, kemasan aktif item) bila bukan satuan dasar; 0 bila belum lengkap.
     *
     * @param  array<string, mixed>  $row
     * @param  array{factors: array<int, float>}|null  $opsi
     */
    public static function faktorLain(array $row, ?array $opsi): float
    {
        $qty = str_replace(',', '.', trim((string) ($row['uom_factor'] ?? '')));

        if (! is_numeric($qty) || (float) $qty <= 0) {
            return 0.0;
        }

        $satuanIsi = is_numeric($row['uom_isi'] ?? null) ? (int) $row['uom_isi'] : null;

        return round((float) $qty * ($satuanIsi === null ? 1.0 : (float) ($opsi['factors'][$satuanIsi] ?? 0)), 4);
    }

    /**
     * A-357: kalimat isi kemasan yang diingat dari baris aksi dokumen
     * (`uom_content_qty` + `uom_content_uom_id`), atau null = isi dalam satuan dasar.
     *
     * @param  array<string, mixed>  $line
     * @return array{qty: float, uom_id: int}|null
     */
    public static function contentOf(array $line): ?array
    {
        $qty = $line['uom_content_qty'] ?? null;
        $uom = $line['uom_content_uom_id'] ?? null;

        return is_numeric($qty) && is_numeric($uom) && (float) $qty > 0 ? ['qty' => (float) $qty, 'uom_id' => (int) $uom] : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Support;

use App\Domain\Conversion\Actions\CreateConversion;
use App\Domain\Conversion\Enums\ConversionOutputKind;
use App\Domain\Conversion\Enums\ConversionType;
use Illuminate\Support\Collection;

/**
 * Mengubah isian sederhana per jenis konversi menjadi baris hasil lengkap
 * untuk {@see CreateConversion} (A-229).
 * Dipakai form untuk pratinjau **dan** saat menyimpan, jadi angka di layar
 * selalu sama dengan yang diperiksa {@see ConversionLines::assertBalanced}.
 *
 * - **Potong** (`cut`): satu atau beberapa batang (potongan utuh, A-253) →
 *   daftar ukuran × jumlah per batang (baris merujuk batangnya lewat
 *   `parent`). Kerf dihitung per batang dari master item (`kerf` per
 *   potongan), sisa = panjang − dipakai − kerf; sisa menjadi offcut (server
 *   menurunkannya ke waste bila di bawah `min_offcut_length`, BR-CNV-03).
 * - **Ganti kemasan** (`repack`): Σ input − Σ output = susut → waste otomatis.
 * - **Rakit/Bongkar**: hasil bebas, tanpa neraca (A-156).
 *
 * Tidak menyentuh basis data: meta item hasil dikirim pemanggil.
 */
class ConversionPlanner
{
    public const TOLERANSI = 0.00005;

    /**
     * @param  array<int, array<string, mixed>>  $inputs  calon ConvertibleStock + `qty` (kunci `key`)
     * @param  array<int, array<string, mixed>>  $rows  isian hasil: item_id, length|qty, count, bin_id, lot_no, kind
     * @param  Collection<int, object>  $items  item hasil: id, code, base_uom_id, tracking_mode, baseUom->code
     * @param  array<string, mixed>  $opsi  bin_id (bin hasil bersama), offcut_reason_id, spoil_reason_id
     * @return array{outputs: array<int, array<string, mixed>>, summary: array<string, mixed>, errors: array<string, string>}
     */
    public function plan(ConversionType $type, array $inputs, array $rows, Collection $items, array $opsi = []): array
    {
        return match ($type) {
            ConversionType::Cut => $this->potong($inputs, $rows, $items, $opsi),
            ConversionType::Repack => $this->kemas($inputs, $rows, $items, $opsi),
            default => $this->bebas($inputs, $rows, $opsi),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $inputs
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, object>  $items
     * @param  array<string, mixed>  $opsi
     * @return array{outputs: array<int, array<string, mixed>>, summary: array<string, mixed>, errors: array<string, string>}
     */
    private function potong(array $inputs, array $rows, Collection $items, array $opsi): array
    {
        $kosong = ['jenis' => 'cut', 'panjang' => 0.0, 'dipakai' => 0.0, 'potongan' => 0, 'kerf_per_potong' => null, 'kerf' => 0.0,
            'sisa' => 0.0, 'sisa_jenis' => null, 'min_offcut' => null, 'uom' => null, 'peringatan' => [], 'kalimat' => '',
            'batang' => [], 'jumlah_batang' => 0];

        if ($inputs === []) {
            return ['outputs' => [], 'summary' => $kosong, 'errors' => ['batang' => 'Pilih satu batang yang akan dipotong.']];
        }

        // A-253: satu CNV boleh memotong beberapa batang; tiap baris ukuran
        // merujuk batangnya lewat `parent` (kosong = batang pertama).
        $pertama = (string) $inputs[0]['key'];
        $perBatang = [];

        foreach (array_values($rows) as $n => $r) {
            $perBatang[(string) (($r['parent'] ?? '') ?: $pertama)][$n] = $r;
        }

        $outputs = [];
        $errors = [];
        $bagian = [];
        $dipakai = [];

        foreach (array_values($inputs) as $i => $batang) {
            if (in_array((string) $batang['key'], $dipakai, true)) {
                $errors[$i === 0 ? 'batang' : 'batang@'.$batang['key']] = 'Batang yang sama dipilih dua kali.';

                continue;
            }

            $dipakai[] = (string) $batang['key'];
            $hasil = $this->potongSatu($batang, $perBatang[(string) $batang['key']] ?? [], $items, $opsi, $i === 0 ? '' : '@'.$batang['key']);
            $outputs = array_merge($outputs, $hasil['outputs']);
            $errors += $hasil['errors'];
            $bagian[] = $hasil['summary'] + ['key' => (string) $batang['key'], 'tracking' => $batang['tracking'] ?? '', 'item_code' => $batang['item_code'] ?? ''];
        }

        if (count($bagian) === 1) {
            return ['outputs' => $outputs, 'summary' => $bagian[0] + ['batang' => $bagian, 'jumlah_batang' => 1], 'errors' => $errors];
        }

        $jumlah = fn (string $k) => round(array_sum(array_column($bagian, $k)), 4);
        $ringkas = [
            'jenis' => 'cut',
            'panjang' => $jumlah('panjang'),
            'dipakai' => $jumlah('dipakai'),
            'potongan' => (int) array_sum(array_column($bagian, 'potongan')),
            'kerf_per_potong' => $bagian[0]['kerf_per_potong'] ?? null,
            'kerf' => $jumlah('kerf'),
            'sisa' => $jumlah('sisa'),
            'sisa_jenis' => null,
            'min_offcut' => $bagian[0]['min_offcut'] ?? null,
            'uom' => $bagian[0]['uom'] ?? null,
            'peringatan' => array_values(array_unique(array_merge(...array_column($bagian, 'peringatan')))),
            'batang' => $bagian,
            'jumlah_batang' => count($bagian),
        ];
        $ringkas['kalimat'] = $this->kalimatBanyakBatang($ringkas, $outputs, $items);

        return ['outputs' => $outputs, 'summary' => $ringkas, 'errors' => $errors];
    }

    /**
     * Satu batang → ukuran × jumlah; kerf dan sisa batang itu sendiri.
     *
     * @param  array<string, mixed>  $batang
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, object>  $items
     * @param  array<string, mixed>  $opsi
     * @return array{outputs: array<int, array<string, mixed>>, summary: array<string, mixed>, errors: array<string, string>}
     */
    private function potongSatu(array $batang, array $rows, Collection $items, array $opsi, string $akhiran): array
    {
        $errors = [];
        $ringkas = ['jenis' => 'cut', 'panjang' => 0.0, 'dipakai' => 0.0, 'potongan' => 0, 'kerf_per_potong' => null, 'kerf' => 0.0,
            'sisa' => 0.0, 'sisa_jenis' => null, 'min_offcut' => null, 'uom' => null, 'peringatan' => [], 'kalimat' => ''];

        if ($batang['piece_id'] === null) {
            $errors['batang'.$akhiran] = 'Mode Potong memotong batang (item per potong); pilih potongan utuh.';
        }

        $panjang = round((float) ($batang['qty'] ?? $batang['balance'] ?? 0), 4);
        $kerfSatuan = $batang['kerf'] ?? null;
        $ringkas['panjang'] = $panjang;
        $ringkas['kerf_per_potong'] = $kerfSatuan === null ? null : (float) $kerfSatuan;
        $ringkas['min_offcut'] = $batang['min_offcut'] ?? null;
        $ringkas['uom'] = $batang['uom'] ?? null;
        $toleransi = max(self::TOLERANSI, ((float) ($batang['rounding'] ?? 0)) / 2);

        $outputs = [];
        $dipakai = 0.0;
        $potongan = 0;

        foreach (array_values($rows) as $n => $r) {
            $kunciBaris = 'potong.'.($r['id'] ?? $n);
            $panjangBaris = is_numeric($r['length'] ?? null) ? round((float) $r['length'], 4) : 0.0;
            $jumlah = is_numeric($r['count'] ?? null) ? max(0, (int) $r['count']) : 0;
            $itemId = is_numeric($r['item_id'] ?? null) && (int) $r['item_id'] > 0 ? (int) $r['item_id'] : (int) $batang['item_id'];

            if ($panjangBaris <= 0 && $jumlah === 0) {
                continue;
            }

            if ($panjangBaris <= 0) {
                $errors[$kunciBaris] = 'Panjang harus lebih dari nol.';

                continue;
            }

            if ($jumlah <= 0) {
                $errors[$kunciBaris] = 'Jumlah potongan minimal 1.';

                continue;
            }

            $item = $items->get($itemId);

            if ($item !== null && (int) $item->base_uom_id !== (int) $batang['base_uom_id']) {
                $errors[$kunciBaris] = 'Item hasil harus memakai satuan dasar yang sama dengan batang ('.$batang['uom'].').';

                continue;
            }

            $dipakai = round($dipakai + $panjangBaris * $jumlah, 4);
            $potongan += $jumlah;

            $outputs[] = [
                'kind' => ConversionOutputKind::Output->value,
                'item_id' => (string) $itemId,
                'parent' => $batang['key'],
                'qty_base' => (string) $panjangBaris,
                'count' => (string) $jumlah,
                'lot_no' => '',
                'bin_id' => (string) ($opsi['bin_id'] ?? ''),
                'reason_code_id' => null,
            ];
        }

        $kerf = $kerfSatuan === null ? 0.0 : round((float) $kerfSatuan * $potongan, 4);
        $sisa = round($panjang - $dipakai - $kerf, 4);

        $ringkas['dipakai'] = $dipakai;
        $ringkas['potongan'] = $potongan;
        $ringkas['kerf'] = $kerf;
        $ringkas['sisa'] = $sisa;

        if ($kerfSatuan === null && $potongan > 0) {
            $ringkas['peringatan'][] = 'Item ini belum punya susut mata potong (kerf) di master item; rugi potong dihitung 0.';
        }

        $label = $akhiran === '' ? '' : 'Batang '.($batang['tracking'] ?? '').': ';

        if ($outputs === []) {
            $errors['potong'.$akhiran] = $errors['potong'.$akhiran] ?? $label.'Isi minimal satu ukuran potongan.';
        } elseif ($sisa < -$toleransi) {
            $errors['potong'.$akhiran] = $label.'Total potongan + kerf ('.self::angka($dipakai + $kerf).' '.$batang['uom'].') melebihi panjang batang '.self::angka($panjang).' '.$batang['uom'].'.';
        }

        if ($kerf > $toleransi) {
            $outputs[] = [
                'kind' => ConversionOutputKind::Kerf->value,
                'item_id' => '',
                'parent' => $batang['key'],
                'qty_base' => (string) $kerf,
                'count' => '1',
                'lot_no' => '',
                'bin_id' => '',
                'reason_code_id' => null,
            ];
        }

        if ($sisa > $toleransi) {
            $minimum = $batang['min_offcut'] ?? null;
            $ringkas['sisa_jenis'] = $minimum !== null && $sisa < (float) $minimum ? 'waste' : 'offcut';

            // Selalu dikirim sebagai offcut: ConversionLines menurunkannya ke waste (auto_waste) bila di bawah minimum (BR-CNV-03).
            $outputs[] = [
                'kind' => ConversionOutputKind::Offcut->value,
                'item_id' => '',
                'parent' => $batang['key'],
                'qty_base' => (string) $sisa,
                'count' => '1',
                'lot_no' => '',
                'bin_id' => (string) ($opsi['bin_id'] ?? ''),
                'reason_code_id' => $ringkas['sisa_jenis'] === 'waste' ? ($opsi['offcut_reason_id'] ?? null) : null,
            ];
        } elseif (abs($sisa) <= $toleransi) {
            $ringkas['sisa'] = 0.0;
        }

        $ringkas['kalimat'] = $this->kalimatPotong($batang, $rows, $items, $ringkas);

        return ['outputs' => $outputs, 'summary' => $ringkas, 'errors' => $errors];
    }

    /**
     * "3 batang (18 m) → 10 × 1,5 m + 2 × 2 m + offcut 0,5 m + kerf 0,12 m" (A-253).
     *
     * @param  array<string, mixed>  $r
     * @param  array<int, array<string, mixed>>  $outputs
     * @param  Collection<int, object>  $items
     */
    private function kalimatBanyakBatang(array $r, array $outputs, Collection $items): string
    {
        $uom = (string) ($r['uom'] ?? '');
        $grup = [];

        foreach ($outputs as $o) {
            if ((string) $o['kind'] !== ConversionOutputKind::Output->value) {
                continue;
            }

            $qty = round((float) $o['qty_base'], 4);
            $kunci = 'output|'.(int) $o['item_id'].'|'.$qty;
            $grup[$kunci] ??= ['n' => 0, 'qty' => $qty, 'label' => ''];
            $grup[$kunci]['n'] += (int) $o['count'];
        }

        // Sisa per batang mengikuti jenisnya (offcut, atau waste bila di bawah minimum).
        foreach ($r['batang'] as $b) {
            if ($b['sisa_jenis'] !== null) {
                $qty = round((float) $b['sisa'], 4);
                $kunci = $b['sisa_jenis'].'|'.$qty;
                $grup[$kunci] ??= ['n' => 0, 'qty' => $qty, 'label' => ' '.$b['sisa_jenis']];
                $grup[$kunci]['n']++;
            }
        }

        $bagian = array_map(fn (array $g) => ($g['n'] > 1 ? $g['n'].' × ' : '').self::angka($g['qty']).' '.$uom.$g['label'], array_values($grup));

        if ($r['kerf'] > 0) {
            $bagian[] = 'kerf '.self::angka($r['kerf']).' '.$uom;
        }

        return $r['jumlah_batang'].' batang ('.self::angka($r['panjang']).' '.$uom.') → '.($bagian === [] ? '…' : implode(' + ', $bagian));
    }

    /**
     * @param  array<int, array<string, mixed>>  $inputs
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, object>  $items
     * @param  array<string, mixed>  $opsi
     * @return array{outputs: array<int, array<string, mixed>>, summary: array<string, mixed>, errors: array<string, string>}
     */
    private function kemas(array $inputs, array $rows, Collection $items, array $opsi): array
    {
        $errors = [];
        $masuk = round(array_sum(array_map(fn ($i) => (float) ($i['qty'] ?? 0), $inputs)), 4);
        $uomId = $inputs === [] ? null : (int) $inputs[0]['base_uom_id'];
        $uom = $inputs === [] ? null : $inputs[0]['uom'];
        $toleransi = max(self::TOLERANSI, ((float) ($inputs[0]['rounding'] ?? 0)) / 2);
        $ringkas = ['jenis' => 'repack', 'input' => $masuk, 'output' => 0.0, 'susut' => 0.0, 'uom' => $uom, 'peringatan' => [], 'kalimat' => ''];

        if ($inputs === []) {
            $errors['inputs'] = 'Pilih minimal satu input dari stok Tersedia gudang ini.';
        }

        foreach ($inputs as $i) {
            if ((int) $i['base_uom_id'] !== $uomId) {
                $errors['inputs'] = 'Ganti kemasan menuntut semua input satu satuan dasar.';
            }

            if ($i['piece_id'] !== null) {
                $errors['inputs'] = 'Potongan tidak dikemas ulang; pakai mode Potong.';
            }
        }

        [$outputs, $keluar] = $this->barisHasil($rows, $items, $inputs, $uomId, $uom, $opsi, $errors);

        $susut = round($masuk - $keluar, 4);
        $ringkas['output'] = $keluar;
        $ringkas['susut'] = $susut > $toleransi ? $susut : 0.0;

        if ($outputs === [] && ! isset($errors['hasil'])) {
            $errors['hasil'] = 'Isi minimal satu item hasil.';
        } elseif ($susut < -$toleransi) {
            $errors['hasil'] = 'Total hasil ('.self::angka($keluar).' '.$uom.') melebihi total input '.self::angka($masuk).' '.$uom.'.';
        } elseif ($susut > $toleransi && $inputs !== []) {
            $outputs[] = [
                'kind' => ConversionOutputKind::Waste->value,
                'item_id' => '',
                'parent' => $inputs[0]['key'],
                'qty_base' => (string) $susut,
                'count' => '1',
                'lot_no' => '',
                'bin_id' => '',
                'reason_code_id' => $opsi['spoil_reason_id'] ?? null,
            ];
        }

        $ringkas['kalimat'] = $inputs === [] ? '' : self::angka($masuk).' '.$uom.' → '.self::angka($keluar).' '.$uom.' hasil'
            .($ringkas['susut'] > 0 ? ' + susut '.self::angka($ringkas['susut']).' '.$uom.' (waste)' : '');

        return ['outputs' => $outputs, 'summary' => $ringkas, 'errors' => $errors];
    }

    /**
     * @param  array<int, array<string, mixed>>  $inputs
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $opsi
     * @return array{outputs: array<int, array<string, mixed>>, summary: array<string, mixed>, errors: array<string, string>}
     */
    private function bebas(array $inputs, array $rows, array $opsi): array
    {
        $errors = [];
        $ringkas = ['jenis' => 'free', 'input' => count($inputs), 'peringatan' => [], 'kalimat' => ''];

        if ($inputs === []) {
            $errors['inputs'] = 'Pilih minimal satu input dari stok Tersedia gudang ini.';
        }

        [$outputs] = $this->barisHasil($rows, collect(), $inputs, null, null, $opsi, $errors);

        if ($outputs === [] && ! isset($errors['hasil'])) {
            $errors['hasil'] = 'Isi minimal satu item hasil.';
        }

        $ringkas['kalimat'] = count($inputs).' input → '.count(array_filter($outputs, fn ($o) => $o['kind'] === 'output')).' baris hasil (tanpa neraca ukuran)';

        return ['outputs' => $outputs, 'summary' => $ringkas, 'errors' => $errors];
    }

    /**
     * Baris hasil umum (repack, rakit, bongkar): output atau waste, jumlah &
     * item; bin/lot opsional.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, object>  $items
     * @param  array<int, array<string, mixed>>  $inputs
     * @param  array<string, mixed>  $opsi
     * @param  array<string, string>  $errors
     * @return array{0: array<int, array<string, mixed>>, 1: float}
     */
    private function barisHasil(array $rows, Collection $items, array $inputs, ?int $uomId, ?string $uom, array $opsi, array &$errors): array
    {
        $outputs = [];
        $total = 0.0;

        foreach (array_values($rows) as $n => $r) {
            $kunciBaris = 'hasil.'.($r['id'] ?? $n);
            $jumlah = is_numeric($r['qty'] ?? null) ? round((float) $r['qty'], 4) : 0.0;
            $jenis = ($r['kind'] ?? 'output') === 'waste' ? ConversionOutputKind::Waste : ConversionOutputKind::Output;
            $itemId = is_numeric($r['item_id'] ?? null) ? (int) $r['item_id'] : 0;

            if ($jumlah <= 0 && $itemId === 0) {
                continue;
            }

            if ($jumlah <= 0) {
                $errors[$kunciBaris] = 'Jumlah harus lebih dari nol.';

                continue;
            }

            if ($jenis === ConversionOutputKind::Output) {
                if ($itemId === 0) {
                    $errors[$kunciBaris] = 'Pilih item hasil.';

                    continue;
                }

                $item = $items->get($itemId);

                if ($uomId !== null && $item !== null && (int) $item->base_uom_id !== $uomId) {
                    $errors[$kunciBaris] = 'Item hasil harus memakai satuan dasar yang sama dengan input ('.$uom.').';

                    continue;
                }
            }

            $total = round($total + $jumlah, 4);
            $outputs[] = [
                'kind' => $jenis->value,
                'item_id' => $jenis === ConversionOutputKind::Output ? (string) $itemId : '',
                'parent' => (string) ($r['parent'] ?? ($inputs[0]['key'] ?? '')),
                'qty_base' => (string) $jumlah,
                'count' => '1',
                'lot_no' => (string) ($r['lot_no'] ?? ''),
                'bin_id' => (string) ($r['bin_id'] ?? ($opsi['bin_id'] ?? '')),
                'reason_code_id' => $jenis === ConversionOutputKind::Waste ? ($opsi['spoil_reason_id'] ?? null) : null,
            ];
        }

        return [$outputs, $total];
    }

    /**
     * "6 m → 2 × 2,5 m + offcut 0,99 m + kerf 0,01 m".
     *
     * @param  array<string, mixed>  $batang
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, object>  $items
     * @param  array<string, mixed>  $r
     */
    private function kalimatPotong(array $batang, array $rows, Collection $items, array $r): string
    {
        $uom = (string) ($batang['uom'] ?? '');
        $bagian = [];

        foreach ($rows as $row) {
            $p = is_numeric($row['length'] ?? null) ? (float) $row['length'] : 0.0;
            $n = is_numeric($row['count'] ?? null) ? (int) $row['count'] : 0;

            if ($p <= 0 || $n <= 0) {
                continue;
            }

            $itemId = is_numeric($row['item_id'] ?? null) && (int) $row['item_id'] > 0 ? (int) $row['item_id'] : (int) $batang['item_id'];
            $kode = $itemId === (int) $batang['item_id'] ? '' : ' '.($items->get($itemId)?->code ?? '');
            $bagian[] = $n.' × '.self::angka($p).' '.$uom.$kode;
        }

        if ($r['sisa_jenis'] !== null) {
            $bagian[] = ($r['sisa_jenis'] === 'waste' ? 'waste' : 'offcut').' '.self::angka($r['sisa']).' '.$uom;
        }

        if ($r['kerf'] > 0) {
            $bagian[] = 'kerf '.self::angka($r['kerf']).' '.$uom;
        }

        return self::angka($r['panjang']).' '.$uom.' → '.($bagian === [] ? '…' : implode(' + ', $bagian));
    }

    /**
     * Kalimat ringkas dari dokumen tersimpan: "6 m PIPA-PVC → 2 × 2,5 m + offcut 0,99 m + kerf 0,01 m".
     *
     * @param  iterable<int, object>  $inputs  item, qty_base
     * @param  iterable<int, object>  $outputs  output_kind, item, qty_base
     */
    public static function kalimatDokumen(iterable $inputs, iterable $outputs): string
    {
        $kiri = [];
        $uom = null;

        // A-253: batang sama panjang dari item yang sama dikelompokkan ("3 × 6 m PIPA").
        foreach ($inputs as $i) {
            $uom ??= $i->item?->baseUom?->code;
            $kunci = $i->item_id.'|'.round((float) $i->qty_base, 4);
            $kiri[$kunci] ??= ['n' => 0, 'teks' => self::angka((float) $i->qty_base).' '.($i->item?->baseUom?->code ?? '').' '.($i->item?->code ?? '')];
            $kiri[$kunci]['n']++;
        }

        $kiri = array_map(fn (array $k) => ($k['n'] > 1 ? $k['n'].' × ' : '').$k['teks'], $kiri);

        $grup = [];

        foreach ($outputs as $o) {
            $jenis = $o->output_kind instanceof ConversionOutputKind ? $o->output_kind : ConversionOutputKind::from((string) $o->output_kind);
            $kunci = $jenis->value.'|'.$o->item_id.'|'.round((float) $o->qty_base, 4);
            $grup[$kunci] ??= ['jenis' => $jenis, 'item' => $o->item, 'qty' => (float) $o->qty_base, 'n' => 0];
            $grup[$kunci]['n']++;
        }

        $kanan = [];

        foreach ($grup as $g) {
            $label = match ($g['jenis']) {
                ConversionOutputKind::Output => trim((string) ($g['item']?->code ?? '')),
                ConversionOutputKind::Offcut => 'offcut',
                ConversionOutputKind::Waste => 'waste',
                ConversionOutputKind::Kerf => 'kerf',
            };
            $kanan[] = ($g['n'] > 1 ? $g['n'].' × ' : '').self::angka($g['qty']).' '.($g['item']?->baseUom?->code ?? $uom ?? '').($label !== '' ? ' '.$label : '');
        }

        return trim(implode(' + ', $kiri)).' → '.($kanan === [] ? '…' : implode(' + ', $kanan));
    }

    public static function angka(float $n): string
    {
        return rtrim(rtrim(number_format($n, 4, ',', '.'), '0'), ',');
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Support;

use App\Domain\Master\Support\QtyFormat;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Rack;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\Zone;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Data denah gudang 2D (A-254): zona → rak → level → bin → isi.
 *
 * - Ukuran & posisi opsional: rak tanpa posisi ditata otomatis per baris
 *   (4 rak per baris, jarak 0,5 m); zona tanpa ukuran = kotak pembungkus raknya.
 * - Status rak (warna): beku › terpakai (bin ikut terpakai / rak area terisi)
 *   › penuh (kapasitas jumlah tercapai) › terisi › kosong.
 * - Umur isi = hari sejak pergerakan masuk terakhir ke baris saldo itu
 *   (pola A-241); per item, baris dengan tanggal masuk tertua di gudang ini
 *   ditandai **tertua** — ambil dulu (FIFO, A-185).
 * - Pencarian: kode bin, kode/nama item, nomor lot/serial/potongan.
 * - Tampilan (A-281): rak digambar sebagai kotak berisi petak bin per level
 *   (level teratas di atas, label level di luar kotak). Ukuran gambar =
 *   ukuran fisik, diperbesar bila petak bin tidak muat (`w`/`h`, meter
 *   gambar); `len`/`wid` tetap ukuran fisik untuk geser & batas zona.
 */
class WarehouseLayoutData
{
    public const RAK_PANJANG = 2.0;

    public const RAK_LEBAR = 1.0;

    public const JARAK = 0.5;

    public const PER_BARIS = 4;

    /** Tata otomatis pindah baris bila lebar baris gambar melewati ini (meter). */
    public const LEBAR_BARIS = 12.0;

    /** Piksel per meter gambar (A-281). */
    public const SKALA = 80;

    /** Ukuran minimum satu petak bin & bingkai dalam rak, dalam meter gambar. */
    public const SEL_LEBAR = 0.8;

    public const SEL_TINGGI = 0.5;

    public const BINGKAI = 0.1;

    /** @return array{zones: array<int, array<string, mixed>>, hasil: array<int, array<string, mixed>>} */
    public function build(Warehouse $gudang, string $cari = ''): array
    {
        $zona = Zone::query()->where('warehouse_id', $gudang->id)->where('is_active', true)->orderBy('code')
            ->with(['racks' => fn ($q) => $q->where('is_active', true)->orderBy('code'),
                'racks.levels' => fn ($q) => $q->where('is_active', true)->orderBy('code')])
            ->get();

        $levelIds = $zona->flatMap(fn (Zone $z) => $z->racks->flatMap(fn (Rack $r) => $r->levels->pluck('id')));
        $bins = Bin::query()->withoutGlobalScopes()->with('occupiedBy:id,code')
            ->whereIn('rack_level_id', $levelIds)->orderBy('code')->get()->groupBy('rack_level_id');

        $isi = $this->isi($bins->flatten()->pluck('id')->all());
        $q = mb_strtolower(trim($cari));
        $hasil = [];

        $zones = $zona->map(function (Zone $z) use ($bins, $isi, $q, &$hasil) {
            $racks = $z->racks->values()->map(function (Rack $r) use ($bins, $isi, $q, &$hasil) {
                $panjang = (float) ($r->length_m ?? self::RAK_PANJANG);
                $lebar = (float) ($r->width_m ?? self::RAK_LEBAR);

                if ($r->orientation === 'v') {
                    [$panjang, $lebar] = [$lebar, $panjang];
                }

                $levels = $r->levels->sortByDesc('code')->values()->map(function ($l) use ($bins, $isi, $q, $r, &$hasil) {
                    return [
                        'id' => (int) $l->id,
                        'code' => (string) $l->code,
                        'height_m' => $l->height_m !== null ? (float) $l->height_m : null,
                        'bins' => ($bins->get($l->id) ?? collect())->map(function (Bin $b) use ($isi, $q, $r, &$hasil) {
                            $baris = $isi[$b->id] ?? [];
                            $total = round(array_sum(array_column($baris, 'qty')), 4);
                            $cocok = $q !== '' && (str_contains(mb_strtolower($b->code), $q)
                                || collect($baris)->contains(fn ($s) => str_contains(mb_strtolower($s['cari']), $q)));

                            if ($cocok) {
                                $hasil[] = ['bin_id' => (int) $b->id, 'bin_code' => (string) $b->code, 'rack_id' => (int) $r->id,
                                    'isi' => collect($baris)->map(fn ($s) => $s['item_code'].($s['tracking'] !== '' ? ' '.$s['tracking'] : ''))->implode(', ')];
                            }

                            $penuh = $b->capacity_qty !== null && $total + 0.00005 >= (float) $b->capacity_qty && $total > 0;
                            $beku = $b->bin_status === BinStatus::Frozen;

                            return [
                                'id' => (int) $b->id,
                                'code' => (string) $b->code,
                                'short' => Str::afterLast((string) $b->code, '-'),
                                'total' => $total,
                                'capacity_qty' => $b->capacity_qty !== null ? (float) $b->capacity_qty : null,
                                'penuh' => $penuh,
                                'beku' => $beku,
                                'nonaktif' => $b->bin_status === BinStatus::Inactive,
                                'terpakai_oleh' => $b->occupiedBy?->code,
                                'occupied_reason' => $b->occupied_reason,
                                'status' => match (true) {
                                    $beku => 'beku',
                                    $b->occupiedBy !== null => 'terpakai',
                                    $penuh => 'penuh',
                                    $total > 0 => 'terisi',
                                    default => 'kosong',
                                },
                                'isi' => $baris,
                                'umur' => $baris === [] ? null : max(array_column($baris, 'umur')),
                                'cocok' => $cocok,
                            ];
                        })->values()->all(),
                    ];
                })->all();

                $semuaBin = collect($levels)->flatMap(fn ($l) => $l['bins']);
                $kolom = max(1, (int) collect($levels)->max(fn ($l) => count($l['bins'])));
                $baris = max(1, count($levels));

                return [
                    'id' => (int) $r->id,
                    'code' => (string) $r->code,
                    'name' => $r->name,
                    'is_area' => (bool) $r->is_area,
                    'pos_x' => $r->pos_x, 'pos_y' => $r->pos_y,
                    'len' => $panjang, 'wid' => $lebar,
                    // A-281: ukuran gambar — petak bin selalu terbaca.
                    'w' => $r->is_area ? $panjang : max($panjang, $kolom * self::SEL_LEBAR + 2 * self::BINGKAI),
                    'h' => $r->is_area ? $lebar : max($lebar, $baris * self::SEL_TINGGI + 2 * self::BINGKAI),
                    'kolom' => $kolom,
                    'height_m' => $r->height_m !== null ? (float) $r->height_m : null,
                    'orientation' => $r->orientation,
                    'otomatis' => $r->pos_x === null || $r->pos_y === null,
                    'status' => $this->status($r, $semuaBin),
                    'umur' => $semuaBin->pluck('umur')->filter(fn ($u) => $u !== null)->max(),
                    'cocok' => $semuaBin->contains('cocok', true) || ($q !== '' && str_contains(mb_strtolower($r->code.' '.$r->name), $q)),
                    'jumlah_bin' => $semuaBin->count(),
                    'levels' => $levels,
                ];
            })->all();

            // Tata otomatis per baris memakai ukuran gambar agar rak tidak bertumpuk.
            $kursorX = self::JARAK;
            $kursorY = self::JARAK;
            $tinggiBaris = 0.0;
            $n = 0;

            foreach ($racks as $i => $r) {
                if ($r['otomatis']) {
                    if ($n > 0 && ($n >= self::PER_BARIS || $kursorX + $r['w'] > self::LEBAR_BARIS)) {
                        $kursorX = self::JARAK;
                        $kursorY += $tinggiBaris + self::JARAK * 2;
                        $tinggiBaris = 0.0;
                        $n = 0;
                    }

                    $racks[$i]['x'] = $kursorX;
                    $racks[$i]['y'] = $kursorY;
                    $kursorX += $r['w'] + self::JARAK;
                    $tinggiBaris = max($tinggiBaris, $r['h']);
                    $n++;
                } else {
                    $racks[$i]['x'] = (float) $r['pos_x'];
                    $racks[$i]['y'] = (float) $r['pos_y'];
                }

                unset($racks[$i]['pos_x'], $racks[$i]['pos_y']);
            }

            $racks = collect($racks);

            // Gambar zona selalu memuat semua rak (ukuran gambar bisa > ukuran fisik).
            $kanan = (float) ($racks->max(fn ($r) => $r['x'] + $r['w']) ?? 0) + self::JARAK;
            $bawah = (float) ($racks->max(fn ($r) => $r['y'] + $r['h']) ?? 0) + self::JARAK;
            $lebarZona = max($z->length_m !== null ? (float) $z->length_m : 6.0, $kanan);
            $tinggiZona = max($z->width_m !== null ? (float) $z->width_m : 2.5, $bawah);

            return [
                'id' => (int) $z->id,
                'code' => (string) $z->code,
                'name' => (string) $z->name,
                'length_m' => $z->length_m !== null ? (float) $z->length_m : null,
                'width_m' => $z->width_m !== null ? (float) $z->width_m : null,
                'w' => $lebarZona,
                'h' => $tinggiZona,
                'racks' => $racks->all(),
            ];
        })->all();

        return ['zones' => $zones, 'hasil' => $hasil];
    }

    /** @param  Collection<int, array<string, mixed>>  $bins */
    private function status(Rack $r, Collection $bins): string
    {
        return match (true) {
            $bins->contains('beku', true) => 'beku',
            $bins->contains(fn ($b) => $b['terpakai_oleh'] !== null) || ($r->is_area && $bins->sum('total') > 0) => 'terpakai',
            $bins->contains('penuh', true) => 'penuh',
            $bins->sum('total') > 0 => 'terisi',
            default => 'kosong',
        };
    }

    /**
     * Isi per bin dengan tanggal masuk & umur; baris tertua per item ditandai.
     *
     * @param  array<int, int>  $binIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function isi(array $binIds): array
    {
        if ($binIds === []) {
            return [];
        }

        $saldo = StockBalance::query()->withoutGlobalScopes()
            ->with('item:id,code,name,base_uom_id,tracking_mode', 'item.baseUom:id,code', 'item.activeConversions.uom:id,code', 'lot:id,lot_no', 'serial:id,serial_no', 'piece:id,piece_no')
            ->whereIn('bin_id', $binIds)->where('qty_base', '>', 0.00005)
            ->get();

        $masuk = StockMovement::query()
            ->whereIn('to_bin_id', $binIds)
            ->selectRaw('to_bin_id, item_id, lot_id, serial_id, piece_id, MAX(occurred_at) as terakhir')
            ->groupBy('to_bin_id', 'item_id', 'lot_id', 'serial_id', 'piece_id')
            ->get()
            ->keyBy(fn ($m) => implode(':', [$m->to_bin_id, $m->item_id, $m->lot_id ?? 0, $m->serial_id ?? 0, $m->piece_id ?? 0]));

        $zona = tenant()?->timezone ?? 'Asia/Jakarta';
        $hasil = [];

        foreach ($saldo as $s) {
            $kunci = implode(':', [$s->bin_id, $s->item_id, $s->lot_id ?? 0, $s->serial_id ?? 0, $s->piece_id ?? 0]);
            $tanggal = ($t = $masuk->get($kunci)?->terakhir) !== null ? Carbon::parse($t) : $s->created_at;
            $tracking = (string) ($s->lot?->lot_no ?? $s->serial?->serial_no ?? $s->piece?->piece_no ?? '');

            $hasil[(int) $s->bin_id][] = [
                'item_id' => (int) $s->item_id,
                'item_code' => (string) $s->item?->code,
                'item_name' => (string) $s->item?->name,
                'uom' => $s->item?->baseUom?->code,
                'qty' => (float) $s->qty_base,
                // A-293: uraian kemasan, mis. "9 DUS 8 BOX"; null bila item tanpa kemasan.
                'kemasan' => QtyFormat::packaging($s->item, $s->qty_base),
                'status' => $s->stock_status?->label(),
                'tracking' => $tracking,
                'masuk' => $tanggal,
                'umur' => $tanggal instanceof CarbonInterface
                    ? (int) $tanggal->copy()->setTimezone($zona)->startOfDay()->diffInDays(now()->setTimezone($zona)->startOfDay())
                    : 0,
                'tertua' => false,
                'cari' => implode(' ', [$s->item?->code, $s->item?->name, $tracking]),
            ];
        }

        // FIFO: per item, baris dengan tanggal masuk paling lama.
        $tertua = [];

        foreach ($hasil as $bin => $baris) {
            foreach ($baris as $i => $b) {
                $lama = $tertua[$b['item_id']] ?? null;

                if ($lama === null || $b['masuk'] < $hasil[$lama[0]][$lama[1]]['masuk']) {
                    $tertua[$b['item_id']] = [$bin, $i];
                }
            }
        }

        foreach ($tertua as [$bin, $i]) {
            $hasil[$bin][$i]['tertua'] = true;
        }

        return $hasil;
    }
}

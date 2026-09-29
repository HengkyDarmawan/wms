<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Support;

use App\Domain\Master\Support\QtyFormat;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\FloorPlanObject;
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
 * - Denah gedung (A-320): satu kanvas bergaris luar gedung (bila ukurannya
 *   diisi); zona diletakkan di koordinat gedung (`x`/`y`, kosong = ditumpuk
 *   otomatis di bawah zona berposisi), rak relatif zonanya, objek denah
 *   relatif gedung. Tumpukan (rak↔rak, rak↔objek padat, zona↔zona, benda di
 *   luar gedung) hanya diperingatkan (A-321).
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

    /** @return array{zones: array<int, array<string, mixed>>, hasil: array<int, array<string, mixed>>, gedung: ?array{p: float, l: float}, objects: array<int, array<string, mixed>>, tumpukan: array<int, string>, tumpukanId: array<int, string>, kanvas: array{w: float, h: float}} */
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
        // K-I: awalan zona pada kode pendek hanya bila kode rak kembar antar-zona.
        $kembar = $zona->flatMap(fn (Zone $z) => $z->racks->pluck('code'))->countBy()->filter(fn ($n) => $n > 1)->all();

        $zones = $zona->map(function (Zone $z) use ($bins, $isi, $q, $kembar, &$hasil) {
            $racks = $z->racks->values()->map(function (Rack $r) use ($bins, $isi, $q, $z, $kembar, &$hasil) {
                $panjang = (float) ($r->length_m ?? self::RAK_PANJANG);
                $lebar = (float) ($r->width_m ?? self::RAK_LEBAR);

                if ($r->orientation === 'v') {
                    [$panjang, $lebar] = [$lebar, $panjang];
                }

                $awalan = isset($kembar[$r->code]) ? (string) $z->code : null;
                $levels = $r->levels->sortByDesc('code')->values()->map(function ($l) use ($bins, $isi, $q, $r, $awalan, &$hasil) {
                    return [
                        'id' => (int) $l->id,
                        'code' => (string) $l->code,
                        'height_m' => $l->height_m !== null ? (float) $l->height_m : null,
                        'bins' => ($bins->get($l->id) ?? collect())->map(function (Bin $b) use ($isi, $q, $r, $l, $awalan, &$hasil) {
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
                                'pendek' => BinCode::dari($awalan, (string) $r->code, (string) $l->code, Str::afterLast((string) $b->code, '-'), (bool) $r->is_area),
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
                                // K-H: indeks cari di browser — item, lot/serial/potongan (kode bin dicocokkan terpisah).
                                'cari' => mb_strtolower(implode(' ', array_column($baris, 'cari'))),
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
            // Satu meter pertama untuk judul zona (A-320).
            $kursorY = self::JARAK * 2;
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
                'pos_x' => $z->pos_x !== null ? (float) $z->pos_x : null,
                'pos_y' => $z->pos_y !== null ? (float) $z->pos_y : null,
                'w' => $lebarZona,
                'h' => $tinggiZona,
                'racks' => $racks->all(),
            ];
        })->all();

        $zones = $this->tataZona($zones);
        $objects = FloorPlanObject::query()->active()->where('warehouse_id', $gudang->id)->orderBy('id')->get()
            ->map(function (FloorPlanObject $o) {
                [$p, $l] = $o->footprint();
                [$isi, $garis] = $o->object_type->colors();

                return [
                    'id' => (int) $o->id,
                    'type' => $o->object_type->value,
                    'label' => $o->object_type->label(),
                    'name' => (string) $o->name,
                    'x' => (float) $o->pos_x, 'y' => (float) $o->pos_y,
                    'p' => $p, 'l' => $l,
                    'length_m' => (float) $o->length_m, 'width_m' => (float) $o->width_m,
                    'rotation' => (int) $o->rotation,
                    'solid' => $o->object_type->solid(),
                    'fill' => $isi, 'stroke' => $garis,
                ];
            })->all();

        $gedung = $gudang->length_m !== null && $gudang->width_m !== null
            ? ['p' => (float) $gudang->length_m, 'l' => (float) $gudang->width_m] : null;
        [$tumpukan, $tumpukanId] = $this->tumpukan($zones, $objects, $gedung);

        $kanan = max([$gedung['p'] ?? 0, ...array_map(fn ($z) => $z['x'] + $z['w'], $zones), ...array_map(fn ($o) => $o['x'] + $o['p'], $objects)]);
        $bawah = max([$gedung['l'] ?? 0, ...array_map(fn ($z) => $z['y'] + $z['h'], $zones), ...array_map(fn ($o) => $o['y'] + $o['l'], $objects)]);

        return [
            'zones' => $zones,
            'hasil' => $hasil,
            'gedung' => $gedung,
            'objects' => $objects,
            'tumpukan' => $tumpukan,
            'tumpukanId' => $tumpukanId,
            'kanvas' => ['w' => max(6.0, $kanan + self::JARAK), 'h' => max(3.0, $bawah + self::JARAK)],
        ];
    }

    /**
     * K-H (A-353): muatan denah untuk browser, dimuat sekali saat halaman dibuka.
     * Isi per bin (baris saldo) **tidak** ikut — diambil saat rak diklik
     * ({@see rakDetail}); yang ikut hanya status, umur, jumlah, dan indeks cari.
     *
     * @return array<string, mixed>
     */
    public function payload(Warehouse $gudang): array
    {
        $d = $this->build($gudang);
        unset($d['hasil']);

        foreach ($d['zones'] as $zi => $z) {
            foreach ($z['racks'] as $ri => $r) {
                foreach ($r['levels'] as $li => $l) {
                    foreach ($l['bins'] as $bi => $b) {
                        unset($d['zones'][$zi]['racks'][$ri]['levels'][$li]['bins'][$bi]['isi'], $d['zones'][$zi]['racks'][$ri]['levels'][$li]['bins'][$bi]['cocok']);
                    }
                }

                unset($d['zones'][$zi]['racks'][$ri]['cocok']);
            }
        }

        $d['jumlah_bin'] = collect($d['zones'])->sum(fn ($z) => collect($z['racks'])->sum('jumlah_bin'));

        return $d;
    }

    /**
     * K-H (A-353): isi satu rak — per bin: baris saldo dengan tanggal masuk,
     * umur, uraian kemasan (A-293), dan tanda **tertua** yang dihitung atas
     * seluruh gudang (FIFO, A-185), bukan hanya rak ini.
     *
     * @return array<string, mixed>
     */
    public function rakDetail(Warehouse $gudang, int $rakId): array
    {
        $rak = Rack::query()->with(['zone', 'levels' => fn ($q) => $q->where('is_active', true)->orderBy('code')])
            ->whereHas('zone', fn ($q) => $q->where('warehouse_id', $gudang->id))
            ->findOrFail($rakId);

        $kembar = Rack::query()->where('code', $rak->code)->whereKeyNot($rak->id)
            ->whereHas('zone', fn ($q) => $q->where('warehouse_id', $gudang->id))->exists();
        $awalan = $kembar ? (string) $rak->zone->code : null;

        $bins = Bin::query()->withoutGlobalScopes()->with('occupiedBy:id,code')
            ->whereIn('rack_level_id', $rak->levels->pluck('id'))->orderBy('code')->get()->groupBy('rack_level_id');
        $isi = $this->isi($bins->flatten()->pluck('id')->all(), (int) $gudang->id);

        $levels = $rak->levels->sortByDesc('code')->values()->map(fn ($l) => [
            'id' => (int) $l->id,
            'code' => (string) $l->code,
            'bins' => ($bins->get($l->id) ?? collect())->map(function (Bin $b) use ($isi, $rak, $l, $awalan) {
                $baris = $isi[$b->id] ?? [];

                return [
                    'id' => (int) $b->id,
                    'code' => (string) $b->code,
                    'pendek' => BinCode::dari($awalan, (string) $rak->code, (string) $l->code, Str::afterLast((string) $b->code, '-'), (bool) $rak->is_area),
                    'total' => round(array_sum(array_column($baris, 'qty')), 4),
                    'capacity_qty' => $b->capacity_qty !== null ? (float) $b->capacity_qty : null,
                    'nonaktif' => $b->bin_status === BinStatus::Inactive,
                    'beku' => $b->bin_status === BinStatus::Frozen,
                    'terpakai_oleh' => $b->occupiedBy?->code,
                    'occupied_reason' => $b->occupied_reason,
                    'isi' => array_map(fn ($s) => [
                        'item_code' => $s['item_code'],
                        'item_name' => $s['item_name'],
                        'qty' => $s['qty'],
                        'uom' => $s['uom'],
                        'kemasan' => $s['kemasan'],
                        'status' => $s['status'],
                        'tracking' => $s['tracking'],
                        'masuk' => $s['masuk'] instanceof CarbonInterface ? $s['masuk']->lokal()->format('d/m/Y') : null,
                        'umur' => $s['umur'],
                        'tertua' => $s['tertua'],
                    ], $baris),
                ];
            })->values()->all(),
        ])->all();

        return [
            'id' => (int) $rak->id,
            'code' => (string) $rak->code,
            'name' => $rak->name,
            'is_area' => (bool) $rak->is_area,
            'zona' => (string) $rak->zone->code,
            'zona_nama' => (string) $rak->zone->name,
            'levels' => $levels,
        ];
    }

    /**
     * Zona berposisi memakai `pos_x/pos_y`; sisanya ditumpuk di bawahnya
     * (x = 0,5 m, jarak 1 m) seperti kartu per zona sebelumnya.
     *
     * @param  array<int, array<string, mixed>>  $zones
     * @return array<int, array<string, mixed>>
     */
    private function tataZona(array $zones): array
    {
        $berposisi = array_filter($zones, fn ($z) => $z['pos_x'] !== null && $z['pos_y'] !== null);
        $kursor = $berposisi === [] ? self::JARAK : max(array_map(fn ($z) => $z['pos_y'] + $z['h'], $berposisi)) + 1.0;

        foreach ($zones as $i => $z) {
            $otomatis = $z['pos_x'] === null || $z['pos_y'] === null;
            $zones[$i]['otomatis'] = $otomatis;

            if ($otomatis) {
                $zones[$i]['x'] = self::JARAK;
                $zones[$i]['y'] = $kursor + 0.5;
                $kursor += $z['h'] + 1.5;
            } else {
                $zones[$i]['x'] = $z['pos_x'];
                $zones[$i]['y'] = $z['pos_y'];
            }
        }

        return $zones;
    }

    /**
     * A-321: pasangan benda yang bertumpuk — hanya peringatan. Rak memakai
     * ukuran fisik (bukan ukuran gambar yang diperbesar agar petak terbaca).
     *
     * @param  array<int, array<string, mixed>>  $zones
     * @param  array<int, array<string, mixed>>  $objects
     * @param  ?array{p: float, l: float}  $gedung
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function tumpukan(array $zones, array $objects, ?array $gedung): array
    {
        $kotak = [];

        foreach ($zones as $z) {
            foreach ($z['racks'] as $r) {
                $kotak[] = ['id' => 'rak:'.$r['id'], 'jenis' => 'rak', 'nama' => 'Rak '.$z['code'].'-'.$r['code'],
                    'x' => $z['x'] + $r['x'], 'y' => $z['y'] + $r['y'], 'p' => $r['len'], 'l' => $r['wid']];
            }
        }

        $padat = array_values(array_filter($objects, fn ($o) => $o['solid']));
        $pesan = [];
        $ids = [];
        $catat = function (array $a, array $b) use (&$pesan, &$ids) {
            $pesan[] = $a['nama'].' ↔ '.$b['nama'];
            $ids[] = $a['id'];
            $ids[] = $b['id'];
        };

        foreach ($kotak as $i => $a) {
            foreach (array_slice($kotak, $i + 1) as $b) {
                if (self::bertumpuk($a, $b)) {
                    $catat($a, $b);
                }
            }

            foreach ($padat as $o) {
                $ob = ['id' => 'obj:'.$o['id'], 'nama' => $o['name'], 'x' => $o['x'], 'y' => $o['y'], 'p' => $o['p'], 'l' => $o['l']];

                if (self::bertumpuk($a, $ob)) {
                    $catat($a, $ob);
                }
            }
        }

        $zona = array_map(fn ($z) => ['id' => 'zona:'.$z['id'], 'nama' => 'Zona '.$z['code'], 'x' => $z['x'], 'y' => $z['y'],
            'p' => $z['length_m'] ?? $z['w'], 'l' => $z['width_m'] ?? $z['h']], $zones);

        foreach ($zona as $i => $a) {
            foreach (array_slice($zona, $i + 1) as $b) {
                if (self::bertumpuk($a, $b)) {
                    $catat($a, $b);
                }
            }
        }

        if ($gedung !== null) {
            $luar = array_merge($zona, array_map(fn ($o) => ['id' => 'obj:'.$o['id'], 'nama' => $o['name'], 'x' => $o['x'], 'y' => $o['y'], 'p' => $o['p'], 'l' => $o['l']], $objects));

            foreach ($luar as $a) {
                if ($a['x'] + $a['p'] > $gedung['p'] + 0.001 || $a['y'] + $a['l'] > $gedung['l'] + 0.001) {
                    $pesan[] = $a['nama'].' keluar dari garis gedung';
                    $ids[] = $a['id'];
                }
            }
        }

        return [$pesan, array_values(array_unique($ids))];
    }

    /** @param  array{x: float, y: float, p: float, l: float}  $a */
    public static function bertumpuk(array $a, array $b): bool
    {
        $e = 0.001;

        return $a['x'] + $e < $b['x'] + $b['p'] && $b['x'] + $e < $a['x'] + $a['p']
            && $a['y'] + $e < $b['y'] + $b['l'] && $b['y'] + $e < $a['y'] + $a['l'];
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
    private function isi(array $binIds, ?int $gudangId = null): array
    {
        if ($binIds === []) {
            return [];
        }

        $muat = fn (callable $saring) => $saring(StockBalance::query()->withoutGlobalScopes()
            ->with('item:id,code,name,base_uom_id,tracking_mode', 'item.baseUom:id,code', 'item.activeConversions.uom:id,code', 'lot:id,lot_no', 'serial:id,serial_no', 'piece:id,piece_no')
            ->where('qty_base', '>', 0.00005))->get();

        $saldo = $muat(fn ($q) => $q->whereIn('bin_id', $binIds));
        $diminta = array_flip($binIds);

        // Detail satu rak: "tertua" dihitung atas seluruh gudang untuk item yang sama.
        if ($gudangId !== null && $saldo->isNotEmpty()) {
            $saldo = $muat(fn ($q) => $q->whereIn('item_id', $saldo->pluck('item_id')->unique()->all())
                ->whereIn('bin_id', Bin::query()->withoutGlobalScopes()->where('warehouse_id', $gudangId)->select('id')));
        }

        $masuk = StockMovement::query()
            ->whereIn('to_bin_id', $saldo->pluck('bin_id')->unique()->all())
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

        return array_intersect_key($hasil, $diminta);
    }
}

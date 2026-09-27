@php
    $warnaStatus = ['kosong' => '#f1f3f5', 'terisi' => '#b2f2bb', 'penuh' => '#ffc9c9', 'beku' => '#a5d8ff', 'terpakai' => '#d0bfff'];
    $labelStatus = ['kosong' => __('Kosong'), 'terisi' => __('Terisi'), 'penuh' => __('Penuh'), 'beku' => __('Dibekukan (opname)'), 'terpakai' => __('Terpakai barang besar / area')];
    $warnaUmur = fn (?int $u) => match (true) { $u === null => '#f1f3f5', $u < 30 => '#b2f2bb', $u < 90 => '#ffec99', $u < 180 => '#ffd8a8', default => '#ffc9c9' };
    $angka = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
@endphp
<div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ __('Denah gudang') }} {{ $gudang->code }}</h1>
            <p class="text-muted mb-0">{{ $gudang->name }} · {{ __('tampak atas: kotak besar = rak, petak di dalamnya = bin, L1/L2 di kiri = tingkat rak (L1 paling bawah). Klik rak untuk melihat isinya.') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if ($bolehUbah)
                <button class="btn {{ $edit ? 'btn-warning' : 'btn-outline-warning' }}" type="button" wire:click="aturEdit({{ $edit ? 'false' : 'true' }})">
                    <i class="bi bi-pencil-square"></i> {{ $edit ? __('Selesai mengatur') : __('Atur denah') }}
                </button>
            @endif
            <a class="btn btn-outline-secondary" href="{{ route('warehouses.show', $gudang) }}">{{ __('Kembali ke gudang') }}</a>
        </div>
    </div>

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">{{ $ruleError }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Warna denah') }}">
                <button class="btn {{ $mode === 'status' ? 'btn-primary' : 'btn-outline-primary' }}" type="button" wire:click="$set('mode', 'status')">{{ __('Warna: status') }}</button>
                <button class="btn {{ $mode === 'umur' ? 'btn-primary' : 'btn-outline-primary' }}" type="button" wire:click="$set('mode', 'umur')">{{ __('Warna: umur stok') }}</button>
            </div>
            <input class="form-control form-control-sm" style="max-width: 20rem" type="search" wire:model.live.debounce.400ms="cari" placeholder="{{ __('Cari bin, item, lot, serial, potongan…') }}" aria-label="{{ __('Cari di denah') }}">
            <div class="small d-flex flex-wrap gap-2">
                @if ($mode === 'status')
                    @foreach ($warnaStatus as $k => $w)
                        <span><span class="d-inline-block border rounded-1 align-middle" style="width: 1rem; height: 1rem; background: {{ $w }}"></span> {{ $labelStatus[$k] }}</span>
                    @endforeach
                @else
                    @foreach ([[10, '< 30 '.__('hari')], [60, '30–89'], [120, '90–179'], [200, '≥ 180']] as [$u, $t])
                        <span><span class="d-inline-block border rounded-1 align-middle" style="width: 1rem; height: 1rem; background: {{ $warnaUmur($u) }}"></span> {{ $t }}</span>
                    @endforeach
                @endif
                <span><span class="d-inline-block rounded-1 align-middle" style="width: 1rem; height: 1rem; border: 3px solid #f76707"></span> {{ __('Cocok pencarian') }}</span>
            </div>
        </div>
        @if ($cari !== '')
            <div class="card-footer small">
                @forelse ($denah['hasil'] as $h)
                    <button class="btn btn-sm btn-outline-secondary py-0 me-1 mb-1" type="button" wire:click="pilihRak({{ $h['rack_id'] }})">{{ $h['bin_code'] }}@if ($h['isi'] !== '') · {{ $h['isi'] }} @endif</button>
                @empty
                    <span class="text-muted">{{ __('Tidak ada bin yang cocok.') }}</span>
                @endforelse
            </div>
        @endif
    </div>

    <div class="row g-3">
        <div class="{{ $rak ? 'col-xl-7' : 'col-12' }}">
            @forelse ($denah['zones'] as $z)
                <div class="card mb-3" wire:key="zona-{{ $z['id'] }}">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <strong>{{ __('Zona') }} {{ $z['code'] }} — {{ $z['name'] }}</strong>
                        <span class="small text-muted">
                            {{ $z['length_m'] !== null ? $angka($z['length_m']).' × '.$angka($z['width_m'] ?? 0).' m' : __('ukuran belum diisi, ditata otomatis') }}
                        </span>
                    </div>
                    <div class="card-body overflow-auto">
                        {{-- A-254/A-281: SVG ringan, satu kotak per rak berisi petak bin; geser di grid saat mode atur. --}}
                        <svg width="{{ $z['w'] * $skala }}" height="{{ $z['h'] * $skala }}" role="img" aria-label="{{ __('Denah zona') }} {{ $z['code'] }}"
                             style="background-image: linear-gradient(#e9ecef 1px, transparent 1px), linear-gradient(90deg, #e9ecef 1px, transparent 1px); background-size: {{ $skala / 2 }}px {{ $skala / 2 }}px; touch-action: none"
                             x-data="{ drag: null, sx: 0, sy: 0, skala: {{ $skala }},
                                mulai(e, id, x, y) { this.drag = { id, x, y, el: e.currentTarget }; this.sx = e.clientX; this.sy = e.clientY; e.currentTarget.setPointerCapture?.(e.pointerId); },
                                gerak(e) { if (! this.drag) return; this.drag.el.setAttribute('transform', `translate(${e.clientX - this.sx},${e.clientY - this.sy})`); },
                                lepas(e) { if (! this.drag) return; const d = this.drag; this.drag = null; const dx = (e.clientX - this.sx) / this.skala, dy = (e.clientY - this.sy) / this.skala;
                                    if (Math.abs(dx) + Math.abs(dy) < 0.1) { d.el.removeAttribute('transform'); this.$wire.pilihRak(d.id); return; }
                                    this.$wire.pindahRak(d.id, Math.max(0, d.x + dx), Math.max(0, d.y + dy)); } }"
                             x-on:pointermove="gerak($event)" x-on:pointerup="lepas($event)">
                            @foreach ($z['racks'] as $r)
                                @php($isiWarna = $mode === 'umur' ? $warnaUmur($r['umur']) : $warnaStatus[$r['status']])
                                @php([$X, $Y, $W, $H] = [$r['x'] * $skala, $r['y'] * $skala, $r['w'] * $skala, $r['h'] * $skala])
                                @php($dipilih = $rakId === $r['id'])
                                <g wire:key="rak-{{ $r['id'] }}" style="cursor: {{ $edit ? 'move' : 'pointer' }}" data-rak="{{ $r['code'] }}"
                                   @if ($edit) x-on:pointerdown.prevent="mulai($event, {{ $r['id'] }}, {{ $r['x'] }}, {{ $r['y'] }})" @else wire:click="pilihRak({{ $r['id'] }})" @endif>
                                    {{-- A-281: nama rak di atas kotak --}}
                                    <text x="{{ $X }}" y="{{ $Y - 7 }}" font-size="13" font-weight="600" fill="currentColor">{{ $r['code'] }}{{ $r['is_area'] ? ' ▦' : '' }}@if ($r['name']) <tspan font-weight="400" fill="currentColor" fill-opacity="0.65">· {{ \Illuminate\Support\Str::limit($r['name'], 18) }}</tspan>@endif</text>
                                    @if ($r['is_area'])
                                        <rect x="{{ $X }}" y="{{ $Y }}" width="{{ $W }}" height="{{ $H }}" rx="6" fill="{{ $isiWarna }}"
                                              stroke="{{ $r['cocok'] ? '#f76707' : ($dipilih ? '#1c7ed6' : '#868e96') }}" stroke-width="{{ $r['cocok'] || $dipilih ? 3 : 1.5 }}" stroke-dasharray="6 3" />
                                        <text x="{{ $X + $W / 2 }}" y="{{ $Y + $H / 2 + 4 }}" font-size="12" text-anchor="middle" fill="#495057">{{ __('Area') }} · {{ $r['jumlah_bin'] }} {{ __('bin') }}</text>
                                    @else
                                        {{-- A-281: kotak rak berisi petak bin per level; label level di luar (kiri) --}}
                                        <rect x="{{ $X }}" y="{{ $Y }}" width="{{ $W }}" height="{{ $H }}" rx="6" fill="#ffffff"
                                              stroke="{{ $r['cocok'] ? '#f76707' : ($dipilih ? '#1c7ed6' : '#495057') }}" stroke-width="{{ $r['cocok'] || $dipilih ? 3 : 1.5 }}" />
                                        @php($p = \App\Domain\Warehouse\Support\WarehouseLayoutData::BINGKAI * $skala)
                                        @php($jumlahLevel = max(1, count($r['levels'])))
                                        @php($tinggiBaris = ($H - 2 * $p) / $jumlahLevel)
                                        @php($lebarSel = ($W - 2 * $p) / $r['kolom'])
                                        @forelse ($r['levels'] as $i => $l)
                                            @php($yb = $Y + $p + $i * $tinggiBaris)
                                            <text x="{{ $X - 5 }}" y="{{ $yb + $tinggiBaris / 2 + 4 }}" font-size="11" font-weight="600" text-anchor="end" fill="currentColor">{{ $l['code'] }}</text>
                                            @if ($i > 0)
                                                <line x1="{{ $X + 2 }}" x2="{{ $X + $W - 2 }}" y1="{{ $yb }}" y2="{{ $yb }}" stroke="#adb5bd" stroke-width="1" />
                                            @endif
                                            @forelse ($l['bins'] as $j => $b)
                                                @php($warnaBin = $b['nonaktif'] ? '#dee2e6' : ($mode === 'umur' ? $warnaUmur($b['umur']) : $warnaStatus[$b['status']]))
                                                <rect x="{{ $X + $p + $j * $lebarSel + 2 }}" y="{{ $yb + 2 }}" width="{{ max(1, $lebarSel - 4) }}" height="{{ max(1, $tinggiBaris - 4) }}" rx="3"
                                                      fill="{{ $warnaBin }}" stroke="{{ $b['cocok'] ? '#f76707' : '#ced4da' }}" stroke-width="{{ $b['cocok'] ? 2.5 : 1 }}"><title>{{ $b['code'] }}{{ $b['total'] > 0 ? ' · '.$angka($b['total']) : ' · '.__('kosong') }}</title></rect>
                                                @if ($lebarSel >= 28 && $tinggiBaris >= 16)
                                                    <text x="{{ $X + $p + ($j + 0.5) * $lebarSel }}" y="{{ $yb + $tinggiBaris / 2 + 4 }}" font-size="{{ $lebarSel >= 44 ? 12 : 10 }}" text-anchor="middle" fill="#212529" pointer-events="none">{{ $b['short'] }}</text>
                                                @endif
                                            @empty
                                                <text x="{{ $X + $W / 2 }}" y="{{ $yb + $tinggiBaris / 2 + 4 }}" font-size="10" font-style="italic" text-anchor="middle" fill="#868e96">{{ __('belum ada bin') }}</text>
                                            @endforelse
                                        @empty
                                            <text x="{{ $X + $W / 2 }}" y="{{ $Y + $H / 2 + 4 }}" font-size="10" font-style="italic" text-anchor="middle" fill="#868e96">{{ __('belum ada level') }}</text>
                                        @endforelse
                                    @endif
                                </g>
                            @endforeach
                        </svg>
                        @if ($z['racks'] === [])
                            <p class="small text-muted mb-0">{{ __('Zona ini belum punya rak.') }}</p>
                        @endif
                    </div>
                    @if ($edit)
                        <div class="card-footer d-flex flex-wrap align-items-end gap-2 small">
                            <div>
                                <label class="form-label mb-0" for="zona-n-{{ $z['id'] }}">{{ __('Nama zona') }}</label>
                                <input class="form-control form-control-sm @error('formZona.'.$z['id'].'.name') is-invalid @enderror" id="zona-n-{{ $z['id'] }}" type="text" maxlength="60" wire:model="formZona.{{ $z['id'] }}.name">
                                @error('formZona.'.$z['id'].'.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div><label class="form-label mb-0" for="zona-p-{{ $z['id'] }}">{{ __('Panjang zona (m)') }}</label><input class="form-control form-control-sm" id="zona-p-{{ $z['id'] }}" type="number" step="0.5" min="0" wire:model="formZona.{{ $z['id'] }}.length_m" placeholder="{{ __('opsional') }}"></div>
                            <div><label class="form-label mb-0" for="zona-l-{{ $z['id'] }}">{{ __('Lebar zona (m)') }}</label><input class="form-control form-control-sm" id="zona-l-{{ $z['id'] }}" type="number" step="0.5" min="0" wire:model="formZona.{{ $z['id'] }}.width_m" placeholder="{{ __('opsional') }}"></div>
                            <button class="btn btn-sm btn-outline-primary" type="button" wire:click="simpanZona({{ $z['id'] }})">{{ __('Simpan zona') }}</button>
                            <span class="text-muted">{{ __('Seret rak untuk memindahkan (kelipatan 0,5 m).') }}</span>
                        </div>
                    @endif
                </div>
            @empty
                <div class="alert alert-info">{{ $bolehUbah ? __('Gudang ini belum punya zona. Klik Atur denah lalu Tambah zona.') : __('Gudang ini belum punya zona.') }}</div>
            @endforelse

            @if ($edit)
                {{-- A-271: bangun struktur langsung dari denah — zona, lalu rak dengan level & bin sekaligus. --}}
                <div class="card mb-3" id="tambah-struktur">
                    <div class="card-header"><strong>{{ __('Tambah zona & rak') }}</strong> <span class="small text-muted">{{ __('kode tidak bisa diubah setelah dibuat karena membentuk kode bin') }}</span></div>
                    <div class="card-body small">
                        <div class="fw-semibold mb-1">{{ __('Zona baru') }}</div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-3">
                                <label class="form-label mb-0" for="zb-kode">{{ __('Kode zona') }} <span class="wajib">*</span></label>
                                <input class="form-control form-control-sm @error('formZonaBaru.code') is-invalid @enderror" id="zb-kode" type="text" maxlength="10" wire:model="formZonaBaru.code" placeholder="mis. C">
                                @error('formZonaBaru.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-5">
                                <label class="form-label mb-0" for="zb-nama">{{ __('Nama zona') }} <span class="wajib">*</span></label>
                                <input class="form-control form-control-sm @error('formZonaBaru.name') is-invalid @enderror" id="zb-nama" type="text" maxlength="60" wire:model="formZonaBaru.name" placeholder="{{ __('mis. Zona besi') }}">
                                @error('formZonaBaru.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4 d-flex align-items-end"><button class="btn btn-sm btn-primary" type="button" wire:click="tambahZona">{{ __('Tambah zona') }}</button></div>
                        </div>

                        <div class="fw-semibold mb-1">{{ __('Rak baru') }}</div>
                        <div class="row g-2">
                            <div class="col-md-2">
                                <label class="form-label mb-0" for="rb-zona">{{ __('Zona') }} <span class="wajib">*</span></label>
                                <select class="form-select form-select-sm @error('formRakBaru.zone_id') is-invalid @enderror" id="rb-zona" wire:model="formRakBaru.zone_id">
                                    <option value="">—</option>
                                    @foreach ($denah['zones'] as $z) <option value="{{ $z['id'] }}">{{ $z['code'] }}</option> @endforeach
                                </select>
                                @error('formRakBaru.zone_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0" for="rb-kode">{{ __('Kode rak') }} <span class="wajib">*</span></label>
                                <input class="form-control form-control-sm @error('formRakBaru.code') is-invalid @enderror" id="rb-kode" type="text" maxlength="10" wire:model="formRakBaru.code" placeholder="mis. R05">
                                @error('formRakBaru.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3"><label class="form-label mb-0" for="rb-nama">{{ __('Nama rak') }}</label><input class="form-control form-control-sm" id="rb-nama" type="text" maxlength="60" wire:model="formRakBaru.name" placeholder="{{ __('opsional') }}"></div>
                            <div class="col-md-1">
                                <label class="form-label mb-0" for="rb-level">{{ __('Level') }}</label>
                                <input class="form-control form-control-sm @error('formRakBaru.levels') is-invalid @enderror" id="rb-level" type="number" min="1" max="{{ \App\Domain\Warehouse\Actions\SaveWarehouseLayout::MAKS_LEVEL }}" wire:model="formRakBaru.levels">
                                @error('formRakBaru.levels') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0" for="rb-bin">{{ __('Bin per level') }}</label>
                                <input class="form-control form-control-sm @error('formRakBaru.bins_per_level') is-invalid @enderror" id="rb-bin" type="number" min="0" max="{{ \App\Domain\Warehouse\Actions\SaveWarehouseLayout::MAKS_BIN_PER_LEVEL }}" wire:model="formRakBaru.bins_per_level">
                                @error('formRakBaru.bins_per_level') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-2"><label class="form-label mb-0" for="rb-kap">{{ __('Kapasitas bin') }}</label><input class="form-control form-control-sm" id="rb-kap" type="number" min="0" step="any" wire:model="formRakBaru.capacity_qty" placeholder="{{ __('opsional') }}"></div>
                        </div>
                        <div class="text-muted mt-1">{{ __('Level diberi kode L1, L2, …; bin B01, B02, … per level. Rak baru ditata otomatis — geser untuk memindahkan.') }}</div>
                    </div>
                    <div class="card-footer"><button class="btn btn-sm btn-primary" type="button" wire:click="tambahRak">{{ __('Tambah rak') }}</button></div>
                </div>

                {{-- A-255: rak area untuk alat berat — satu bin mewakili seluruh rak atau zona. --}}
                <div class="card mb-3">
                    <div class="card-header"><strong>{{ __('Rak area untuk barang besar (alat berat)') }}</strong> <span class="small text-muted">{{ __('satu bin untuk seluruh rak atau seluruh zona; isi kedua ditolak') }}</span></div>
                    <div class="card-body row g-2 small">
                        <div class="col-md-3">
                            <label class="form-label mb-0" for="area-zona">{{ __('Zona') }}</label>
                            <select class="form-select form-select-sm @error('formArea.zone_id') is-invalid @enderror" id="area-zona" wire:model="formArea.zone_id">
                                <option value="">—</option>
                                @foreach ($denah['zones'] as $z) <option value="{{ $z['id'] }}">{{ $z['code'] }}</option> @endforeach
                            </select>
                            @error('formArea.zone_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0" for="area-kode">{{ __('Kode rak') }}</label>
                            <input class="form-control form-control-sm @error('formArea.code') is-invalid @enderror" id="area-kode" type="text" maxlength="10" wire:model="formArea.code" placeholder="mis. AB1">
                            @error('formArea.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3"><label class="form-label mb-0" for="area-nama">{{ __('Nama') }}</label><input class="form-control form-control-sm" id="area-nama" type="text" maxlength="60" wire:model="formArea.name" placeholder="{{ __('mis. Parkir excavator') }}"></div>
                        <div class="col-md-2"><label class="form-label mb-0" for="area-kap">{{ __('Kapasitas (unit)') }}</label><input class="form-control form-control-sm" id="area-kap" type="number" min="1" step="1" wire:model="formArea.capacity_qty"></div>
                        <div class="col-md-2 d-flex align-items-end">
                            <label class="form-check mb-1"><input class="form-check-input" type="checkbox" value="1" wire:model="formArea.seluruh_zona"> <span class="form-check-label">{{ __('Seluruh zona') }}</span></label>
                        </div>
                    </div>
                    <div class="card-footer"><button class="btn btn-sm btn-primary" type="button" wire:click="buatArea">{{ __('Buat rak area') }}</button></div>
                </div>
            @endif
        </div>

        @if ($rak)
            <div class="col-xl-5">
                <div class="card mb-3 border-primary">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>{{ __('Rak') }} {{ $rak['zona'] }}-{{ $rak['code'] }} @if ($rak['name']) — {{ $rak['name'] }} @endif @if ($rak['is_area']) <span class="badge text-bg-secondary">{{ __('area') }}</span> @endif</strong>
                        <button class="btn-close" type="button" wire:click="tutupRak" aria-label="{{ __('Tutup') }}"></button>
                    </div>
                    <div class="card-body small">
                        <p class="text-muted mb-2">
                            {{ $rak['otomatis'] ? __('Posisi ditata otomatis') : __('Posisi').' '.$angka($rak['x']).', '.$angka($rak['y']).' m' }}
                            · {{ $angka($rak['len']) }} × {{ $angka($rak['wid']) }} m @if ($rak['height_m']) · {{ __('tinggi') }} {{ $angka($rak['height_m']) }} m @endif
                        </p>
                        @foreach ($rak['levels'] as $lv)
                            <div class="mb-3" wire:key="lv-{{ $lv['id'] }}">
                                <div class="fw-semibold">{{ __('Level') }} {{ $lv['code'] }} @if ($lv['height_m']) <span class="text-muted">· {{ $angka($lv['height_m']) }} m</span> @endif</div>
                                @forelse ($lv['bins'] as $b)
                                    <div @class(['border rounded p-2 mt-1', 'border-warning border-2' => $b['cocok']]) wire:key="bin-{{ $b['id'] }}">
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <strong>{{ $b['code'] }}</strong>
                                            @if ($b['capacity_qty'] !== null) <span class="text-muted">{{ $angka($b['total']) }} / {{ $angka($b['capacity_qty']) }}</span> @endif
                                            @if ($b['penuh']) <span class="badge text-bg-danger">{{ __('penuh') }}</span> @endif
                                            @if ($b['beku']) <span class="badge text-bg-info">{{ __('dibekukan') }}</span> @endif
                                            @if ($b['nonaktif']) <span class="badge text-bg-secondary">{{ __('nonaktif') }}</span> @endif
                                            @if ($b['terpakai_oleh'])
                                                <span class="badge" style="background: #d0bfff; color: #212529">{{ __('ikut terpakai oleh') }} {{ $b['terpakai_oleh'] }}</span>
                                                @if ($edit) <button class="btn btn-sm btn-link p-0" type="button" wire:click="lepasTerpakai({{ $b['id'] }})">{{ __('lepas') }}</button> @endif
                                            @endif
                                        </div>
                                        @if ($b['terpakai_oleh'] && $b['occupied_reason']) <div class="text-muted">{{ $b['occupied_reason'] }}</div> @endif
                                        @foreach ($b['isi'] as $s)
                                            <div class="d-flex flex-wrap gap-2 mt-1">
                                                <span>{{ $s['item_code'] }}</span>
                                                <span class="text-muted">{{ $s['item_name'] }}</span>
                                                <span>{{ $angka($s['qty']) }} {{ $s['uom'] }}@if ($s['kemasan'] ?? null) <span class="text-muted">({{ $s['kemasan'] }})</span>@endif</span>
                                                @if ($s['tracking'] !== '') <span class="text-muted">{{ $s['tracking'] }}</span> @endif
                                                @if ($s['status'] && $s['status'] !== __('Tersedia')) <span class="badge text-bg-light border">{{ $s['status'] }}</span> @endif
                                                <span class="text-muted">{{ __('masuk') }} {{ $s['masuk']?->lokal()->format('d/m/Y') }} · {{ $s['umur'] }} {{ __('hari') }}</span>
                                                @if ($s['tertua']) <span class="badge text-bg-success" title="{{ __('Masuk paling lama untuk item ini di gudang — ambil dulu (FIFO)') }}">{{ __('tertua — ambil dulu') }}</span> @endif
                                            </div>
                                        @endforeach
                                        @if ($b['isi'] === [] && ! $b['terpakai_oleh']) <div class="text-muted">{{ __('kosong') }}</div> @endif
                                    </div>
                                @empty
                                    <div class="text-muted">{{ __('Belum ada bin di level ini.') }}</div>
                                @endforelse
                                @if ($edit && ! $rak['is_area'])
                                    {{-- A-271: tambah bin di level ini (nomor berikutnya). --}}
                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        <input class="form-control form-control-sm @error('formBinBaru.'.$lv['id']) is-invalid @enderror" style="max-width: 5rem" type="number" min="1" max="{{ \App\Domain\Warehouse\Actions\SaveWarehouseLayout::MAKS_BIN_PER_LEVEL }}" wire:model="formBinBaru.{{ $lv['id'] }}" placeholder="1" aria-label="{{ __('Jumlah bin baru di level') }} {{ $lv['code'] }}">
                                        <button class="btn btn-sm btn-outline-primary" type="button" wire:click="tambahBin({{ $lv['id'] }})">{{ __('Tambah bin') }}</button>
                                        @error('formBinBaru.'.$lv['id']) <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if ($edit)
                        <div class="card-footer small">
                            <div class="fw-semibold mb-1">{{ __('Ukuran & posisi rak (meter, opsional)') }}</div>
                            <div class="row g-2">
                                <div class="col-6"><input class="form-control form-control-sm" type="text" maxlength="60" wire:model="formRak.name" placeholder="{{ __('Nama rak') }}" aria-label="{{ __('Nama rak') }}"></div>
                                <div class="col-6">
                                    <select class="form-select form-select-sm" wire:model="formRak.orientation" aria-label="{{ __('Arah rak') }}">
                                        <option value="h">{{ __('Memanjang ke samping') }}</option>
                                        <option value="v">{{ __('Memanjang ke bawah') }}</option>
                                    </select>
                                </div>
                                @foreach (['length_m' => __('Panjang'), 'width_m' => __('Lebar'), 'height_m' => __('Tinggi'), 'pos_x' => __('Posisi X'), 'pos_y' => __('Posisi Y')] as $k => $t)
                                    <div class="col-4">
                                        <input class="form-control form-control-sm @error('formRak.'.$k) is-invalid @enderror" type="number" step="0.5" min="0" wire:model="formRak.{{ $k }}" placeholder="{{ $t }}" aria-label="{{ $t }}">
                                        @error('formRak.'.$k) <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                @endforeach
                            </div>
                            <button class="btn btn-sm btn-primary mt-2" type="button" wire:click="simpanRak">{{ __('Simpan rak') }}</button>

                            @if (! $rak['is_area'])
                                {{-- A-271: level baru di rak ini. --}}
                                <div class="fw-semibold mt-3 mb-1">{{ __('Tambah level') }}</div>
                                <div class="row g-2">
                                    <div class="col-4">
                                        <input class="form-control form-control-sm @error('formLevelBaru.code') is-invalid @enderror" type="text" maxlength="10" wire:model="formLevelBaru.code" placeholder="{{ __('Kode (kosong = otomatis)') }}" aria-label="{{ __('Kode level') }}">
                                        @error('formLevelBaru.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-4">
                                        <input class="form-control form-control-sm @error('formLevelBaru.bins') is-invalid @enderror" type="number" min="0" max="{{ \App\Domain\Warehouse\Actions\SaveWarehouseLayout::MAKS_BIN_PER_LEVEL }}" wire:model="formLevelBaru.bins" aria-label="{{ __('Jumlah bin di level baru') }}">
                                        @error('formLevelBaru.bins') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-4"><button class="btn btn-sm btn-outline-primary w-100" type="button" wire:click="tambahLevel">{{ __('Tambah level') }}</button></div>
                                </div>
                            @endif

                            {{-- A-255: barang besar tak terduga memakan bin sebelahnya. --}}
                            <div class="fw-semibold mt-3 mb-1">{{ __('Tandai bin ikut terpakai barang besar') }}</div>
                            @php($binRak = collect($rak['levels'])->flatMap(fn ($l) => $l['bins']))
                            <div class="row g-2">
                                <div class="col-6">
                                    <select class="form-select form-select-sm @error('tandai.occupied_by') is-invalid @enderror" wire:model="tandai.utama" aria-label="{{ __('Bin utama') }}">
                                        <option value="">{{ __('Bin utama (tempat barang dicatat)…') }}</option>
                                        @foreach ($binRak->where('total', '>', 0) as $b) <option value="{{ $b['id'] }}">{{ $b['code'] }}</option> @endforeach
                                    </select>
                                    @error('tandai.occupied_by') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-6">
                                    <input class="form-control form-control-sm @error('tandai.occupied_reason') is-invalid @enderror" type="text" maxlength="255" wire:model="tandai.alasan" placeholder="{{ __('Alasan, mis. genset besar') }}" aria-label="{{ __('Alasan') }}">
                                    @error('tandai.occupied_reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-12">
                                    @foreach ($binRak->where('total', 0)->whereNull('terpakai_oleh') as $b)
                                        <label class="form-check form-check-inline"><input class="form-check-input" type="checkbox" value="{{ $b['id'] }}" wire:model="tandai.bins"> <span class="form-check-label">{{ $b['code'] }}</span></label>
                                    @endforeach
                                    @error('tandai.bins') <div class="text-danger">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline-primary mt-2" type="button" wire:click="tandaiTerpakai">{{ __('Tandai ikut terpakai') }}</button>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

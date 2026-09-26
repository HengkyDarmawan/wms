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
            <p class="text-muted mb-0">{{ $gudang->name }} · {{ __('klik rak untuk melihat level, bin, dan isinya') }}</p>
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
                        {{-- A-254: SVG ringan, satu kotak per rak; geser di grid saat mode atur. --}}
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
                                <g wire:key="rak-{{ $r['id'] }}" style="cursor: {{ $edit ? 'move' : 'pointer' }}"
                                   @if ($edit) x-on:pointerdown.prevent="mulai($event, {{ $r['id'] }}, {{ $r['x'] }}, {{ $r['y'] }})" @else wire:click="pilihRak({{ $r['id'] }})" @endif>
                                    <rect x="{{ $r['x'] * $skala }}" y="{{ $r['y'] * $skala }}" width="{{ $r['len'] * $skala }}" height="{{ $r['wid'] * $skala }}" rx="3"
                                          fill="{{ $isiWarna }}" stroke="{{ $r['cocok'] ? '#f76707' : ($rakId === $r['id'] ? '#1c7ed6' : '#868e96') }}"
                                          stroke-width="{{ $r['cocok'] || $rakId === $r['id'] ? 3 : 1 }}" @if ($r['is_area']) stroke-dasharray="6 3" @endif />
                                    <text x="{{ ($r['x'] + 0.1) * $skala }}" y="{{ ($r['y'] + 0.4) * $skala }}" font-size="12" fill="#212529">{{ $r['code'] }}{{ $r['is_area'] ? ' ▦' : '' }}</text>
                                    <text x="{{ ($r['x'] + 0.1) * $skala }}" y="{{ ($r['y'] + 0.75) * $skala }}" font-size="10" fill="#495057">{{ $r['jumlah_bin'] }} {{ __('bin') }}@if ($mode === 'umur' && $r['umur'] !== null) · {{ $r['umur'] }} {{ __('hr') }}@endif</text>
                                </g>
                            @endforeach
                        </svg>
                        @if ($z['racks'] === [])
                            <p class="small text-muted mb-0">{{ __('Zona ini belum punya rak.') }}</p>
                        @endif
                    </div>
                    @if ($edit)
                        <div class="card-footer d-flex flex-wrap align-items-end gap-2 small">
                            <div><label class="form-label mb-0" for="zona-p-{{ $z['id'] }}">{{ __('Panjang zona (m)') }}</label><input class="form-control form-control-sm" id="zona-p-{{ $z['id'] }}" type="number" step="0.5" min="0" wire:model="formZona.{{ $z['id'] }}.length_m" placeholder="{{ __('opsional') }}"></div>
                            <div><label class="form-label mb-0" for="zona-l-{{ $z['id'] }}">{{ __('Lebar zona (m)') }}</label><input class="form-control form-control-sm" id="zona-l-{{ $z['id'] }}" type="number" step="0.5" min="0" wire:model="formZona.{{ $z['id'] }}.width_m" placeholder="{{ __('opsional') }}"></div>
                            <button class="btn btn-sm btn-outline-primary" type="button" wire:click="simpanZona({{ $z['id'] }})">{{ __('Simpan ukuran zona') }}</button>
                            <span class="text-muted">{{ __('Seret rak untuk memindahkan (kelipatan 0,5 m).') }}</span>
                        </div>
                    @endif
                </div>
            @empty
                <div class="alert alert-info">{{ __('Gudang ini belum punya zona. Tambahkan zona, rak, dan level di halaman gudang (tab Zona & rak).') }}</div>
            @endforelse

            @if ($edit)
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
                                                <span>{{ $angka($s['qty']) }} {{ $s['uom'] }}</span>
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

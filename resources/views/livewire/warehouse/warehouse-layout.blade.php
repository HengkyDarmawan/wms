@php
    $warnaStatus = ['kosong' => '#f1f3f5', 'terisi' => '#b2f2bb', 'penuh' => '#ffc9c9', 'beku' => '#a5d8ff', 'terpakai' => '#d0bfff'];
    $labelStatus = ['kosong' => __('Kosong'), 'terisi' => __('Terisi'), 'penuh' => __('Penuh'), 'beku' => __('Dibekukan (opname)'), 'terpakai' => __('Terpakai barang besar / area')];
    $warnaUmur = fn (?int $u) => match (true) { $u === null => '#f1f3f5', $u < 30 => '#b2f2bb', $u < 90 => '#ffec99', $u < 180 => '#ffd8a8', default => '#ffc9c9' };
    $angka = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
    $S = $skala;
    $K = $denah['kanvas'];
    $tumpuk = array_flip($denah['tumpukanId']);
    $adaPanel = $rak !== null || $zonaPilih !== null || $objekPilih !== null;
    // Teks ikut membesar pada gedung lebar supaya tetap terbaca saat "pas layar".
    $F = round(max(1, min(3, $K['w'] / 15)), 2);
@endphp
<div>
    @unless ($ringkas)
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h1 class="h3 mb-1">{{ __('Denah gudang') }} {{ $gudang->code }}</h1>
                <p class="text-muted mb-0">
                    {{ $gudang->name }} ·
                    {{ $denah['gedung'] ? __('Gedung :p × :l m', ['p' => $angka($denah['gedung']['p']), 'l' => $angka($denah['gedung']['l'])]) : __('ukuran gedung belum diisi') }}
                    · {{ __('tampak atas; klik rak untuk melihat isinya.') }}
                </p>
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
    @endunless

    @if ($ruleError !== '')
        <div class="alert alert-danger" role="alert">{{ $ruleError }} @if ($ruleCode !== '') <span class="badge text-bg-dark ms-1">{{ $ruleCode }}</span> @endif</div>
    @endif

    <div x-data="denahGedung({ skala: {{ $S }} })" x-on:keydown.window="tombol($event)">
        <div class="card mb-3">
            <div class="card-body d-flex flex-wrap align-items-center gap-2">
                <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Warna denah') }}">
                    <button class="btn {{ $mode === 'status' ? 'btn-primary' : 'btn-outline-primary' }}" type="button" wire:click="$set('mode', 'status')">{{ __('Warna: status') }}</button>
                    <button class="btn {{ $mode === 'umur' ? 'btn-primary' : 'btn-outline-primary' }}" type="button" wire:click="$set('mode', 'umur')">{{ __('Warna: umur stok') }}</button>
                </div>
                <input class="form-control form-control-sm" style="max-width: 16rem" type="search" wire:model.live.debounce.400ms="cari" placeholder="{{ __('Cari bin, item, lot, serial, potongan…') }}" aria-label="{{ __('Cari di denah') }}">
                <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Perbesaran') }}">
                    <button class="btn btn-outline-secondary" type="button" x-on:click="perkecil()" title="{{ __('Perkecil') }}" aria-label="{{ __('Perkecil') }}">−</button>
                    <button class="btn btn-outline-secondary" type="button" x-on:click="pas()" data-pas-layar>{{ __('Pas layar') }}</button>
                    <button class="btn btn-outline-secondary" type="button" x-on:click="perbesar()" title="{{ __('Perbesar') }}" aria-label="{{ __('Perbesar') }}">+</button>
                </div>
                <span class="small text-muted" x-text="persen()"></span>

                @if ($edit)
                    <div class="ms-auto d-flex flex-wrap gap-2">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-plus-lg"></i> {{ __('Tambah objek') }}</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><h6 class="dropdown-header">{{ __('Objek denah (tanpa stok)') }}</h6></li>
                                @foreach ($jenisObjek as $nilai => $label)
                                    <li><button class="dropdown-item" type="button" wire:click="tambahObjek('{{ $nilai }}')" data-objek-baru="{{ $nilai }}">{{ $label }}</button></li>
                                @endforeach
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#tambah-struktur">{{ __('Zona / rak / rak area…') }}</a></li>
                            </ul>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="$toggle('formGedungBuka')"><i class="bi bi-bounding-box"></i> {{ __('Ukuran gedung') }}</button>
                    </div>
                @endif
            </div>

            @if ($edit && $formGedungBuka)
                <div class="card-footer d-flex flex-wrap align-items-end gap-2 small">
                    <div>
                        <label class="form-label mb-0" for="gedung-p">{{ __('Panjang gedung (m)') }}</label>
                        <input class="form-control form-control-sm @error('formGedung.length_m') is-invalid @enderror" id="gedung-p" type="text" inputmode="decimal" wire:model="formGedung.length_m" placeholder="{{ __('mis. 40') }}">
                        @error('formGedung.length_m') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="form-label mb-0" for="gedung-l">{{ __('Lebar gedung (m)') }}</label>
                        <input class="form-control form-control-sm @error('formGedung.width_m') is-invalid @enderror" id="gedung-l" type="text" inputmode="decimal" wire:model="formGedung.width_m" placeholder="{{ __('mis. 24') }}">
                        @error('formGedung.width_m') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <button class="btn btn-sm btn-primary" type="button" wire:click="simpanGedung">{{ __('Simpan ukuran gedung') }}</button>
                    <span class="text-muted">{{ __('Kosongkan keduanya bila denah cukup digambar dari zona.') }}</span>
                </div>
            @endif

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

        @if ($denah['tumpukan'] !== [])
            {{-- A-321: hanya peringatan; penyimpanan tidak ditolak. --}}
            <div class="alert alert-warning py-2 small" role="status" data-tumpukan>
                <i class="bi bi-exclamation-triangle"></i>
                <strong>{{ __(':n tumpukan', ['n' => count($denah['tumpukan'])]) }}:</strong> {{ implode('; ', $denah['tumpukan']) }}.
                <span class="text-muted">{{ __('Geser salah satunya; peringatan ini tidak menolak simpan.') }}</span>
            </div>
        @endif

        <div class="row g-3">
            <div class="{{ $adaPanel ? 'col-xl-8' : 'col-12' }}">
                <div class="card mb-3">
                    <div class="card-body p-2">
                        <div class="overflow-auto" x-ref="wrap" style="max-height: 70vh">
                            {{-- A-320: satu kanvas gedung; satuan SVG = meter × skala. --}}
                            @php($M = 40 * $F)
                            <svg x-ref="svg" viewBox="{{ -$M }} {{ -$M }} {{ $K['w'] * $S + 2 * $M }} {{ $K['h'] * $S + 2 * $M }}"
                                 :width="lebarAsli() * zoom" :height="{{ $K['h'] * $S + 2 * $M }} / {{ $K['w'] * $S + 2 * $M }} * lebarAsli() * zoom"
                                 width="{{ $K['w'] * $S + 2 * $M }}" height="{{ $K['h'] * $S + 2 * $M }}"
                                 role="img" aria-label="{{ __('Denah gedung') }} {{ $gudang->code }}" data-denah-gedung
                                 style="touch-action: none; user-select: none"
                                 x-on:pointerdown="mulai($event)" x-on:pointermove="gerak($event)" x-on:pointerup="lepas($event)">
                                <defs>
                                    <pattern id="grid-{{ $gudang->id }}" width="{{ $S / 2 }}" height="{{ $S / 2 }}" patternUnits="userSpaceOnUse">
                                        <path d="M {{ $S / 2 }} 0 L 0 0 0 {{ $S / 2 }}" fill="none" stroke="#eef0f3" stroke-width="1" />
                                    </pattern>
                                </defs>
                                <rect x="0" y="0" width="{{ $K['w'] * $S }}" height="{{ $K['h'] * $S }}" fill="url(#grid-{{ $gudang->id }})" />
                                @if ($denah['gedung'])
                                    @php([$GP, $GL] = [$denah['gedung']['p'] * $S, $denah['gedung']['l'] * $S])
                                    <rect x="0" y="0" width="{{ $GP }}" height="{{ $GL }}" fill="none" stroke="#212529" stroke-width="6" data-gedung />
                                    <text x="0" y="{{ -10 * $F }}" font-size="{{ 20 * $F }}" font-weight="600" fill="currentColor">{{ __('Gedung') }} {{ $gudang->code }} — {{ $angka($denah['gedung']['p']) }} × {{ $angka($denah['gedung']['l']) }} m</text>
                                    @for ($m = 0; $m <= $denah['gedung']['p']; $m += 5)
                                        <text x="{{ $m * $S }}" y="{{ $GL + 22 * $F }}" font-size="{{ 14 * $F }}" text-anchor="middle" fill="#868e96">{{ $m }}</text>
                                    @endfor
                                @endif

                                {{-- Objek lantai (jalur forklift, area bebas) digambar di bawah zona. --}}
                                @foreach (collect($denah['objects'])->where('solid', false) as $o)
                                    @include('livewire.warehouse.partials.floor-plan-object', ['o' => $o])
                                @endforeach

                                @foreach ($denah['zones'] as $z)
                                    @php($pilihZ = $terpilih === 'zona:'.$z['id'])
                                    <g wire:key="zona-{{ $z['id'] }}" data-jenis="zona" data-id="{{ $z['id'] }}" data-zona="{{ $z['code'] }}"
                                       data-x="{{ $z['x'] }}" data-y="{{ $z['y'] }}" data-p="{{ $z['length_m'] ?? $z['w'] }}" data-l="{{ $z['width_m'] ?? $z['h'] }}"
                                       transform="translate({{ $z['x'] * $S }},{{ $z['y'] * $S }})" style="cursor: {{ $edit ? 'move' : 'default' }}">
                                        <rect data-badan x="0" y="0" width="{{ $z['w'] * $S }}" height="{{ $z['h'] * $S }}" rx="8" fill="#eef2ff" fill-opacity="0.55"
                                              stroke="{{ isset($tumpuk['zona:'.$z['id']]) ? '#e03131' : '#6366f1' }}" stroke-width="{{ $pilihZ ? 5 : 2 }}" @if (isset($tumpuk['zona:'.$z['id']])) stroke-dasharray="10 5" @endif />
                                        <text x="10" y="{{ 22 * $F }}" font-size="{{ 17 * $F }}" font-weight="600" fill="#4338ca">{{ __('Zona') }} {{ $z['code'] }} — {{ \Illuminate\Support\Str::limit($z['name'], 28) }}</text>
                                        <text x="{{ $z['w'] * $S - 8 }}" y="{{ $z['h'] * $S - 8 }}" font-size="{{ 13 * $F }}" text-anchor="end" fill="#6366f1">
                                            {{ $z['length_m'] !== null ? $angka($z['length_m']).' × '.$angka($z['width_m'] ?? 0).' m' : __('ukuran otomatis') }}
                                        </text>

                                        @foreach ($z['racks'] as $r)
                                            @php($isiWarna = $mode === 'umur' ? $warnaUmur($r['umur']) : $warnaStatus[$r['status']])
                                            @php([$W, $H] = [$r['w'] * $S, $r['h'] * $S])
                                            @php($dipilih = $rakId === $r['id'])
                                            @php($merah = isset($tumpuk['rak:'.$r['id']]))
                                            <g wire:key="rak-{{ $r['id'] }}" data-jenis="rak" data-id="{{ $r['id'] }}" data-rak="{{ $r['code'] }}"
                                               data-x="{{ $r['x'] }}" data-y="{{ $r['y'] }}" data-p="{{ $r['len'] }}" data-l="{{ $r['wid'] }}"
                                               transform="translate({{ $r['x'] * $S }},{{ $r['y'] * $S }})" style="cursor: {{ $edit ? 'move' : 'pointer' }}">
                                                <text x="0" y="{{ -7 * $F }}" font-size="{{ 13 * $F }}" font-weight="600" fill="currentColor">{{ $r['code'] }}{{ $r['is_area'] ? ' ▦' : '' }}@if ($r['name']) <tspan font-weight="400" fill-opacity="0.65">· {{ \Illuminate\Support\Str::limit($r['name'], 18) }}</tspan>@endif</text>
                                                @if ($r['is_area'])
                                                    <rect data-badan x="0" y="0" width="{{ $W }}" height="{{ $H }}" rx="6" fill="{{ $isiWarna }}"
                                                          stroke="{{ $merah ? '#e03131' : ($r['cocok'] ? '#f76707' : ($dipilih ? '#1c7ed6' : '#868e96')) }}" stroke-width="{{ $r['cocok'] || $dipilih || $merah ? 3 : 1.5 }}" stroke-dasharray="6 3" />
                                                    <text x="{{ $W / 2 }}" y="{{ $H / 2 + 4 }}" font-size="12" text-anchor="middle" fill="#495057" pointer-events="none">{{ __('Area') }} · {{ $r['jumlah_bin'] }} {{ __('bin') }}</text>
                                                @else
                                                    <rect data-badan x="0" y="0" width="{{ $W }}" height="{{ $H }}" rx="6" fill="#ffffff"
                                                          stroke="{{ $merah ? '#e03131' : ($r['cocok'] ? '#f76707' : ($dipilih ? '#1c7ed6' : '#495057')) }}" stroke-width="{{ $r['cocok'] || $dipilih || $merah ? 3 : 1.5 }}" @if ($merah) stroke-dasharray="6 3" @endif />
                                                    @php($p = \App\Domain\Warehouse\Support\WarehouseLayoutData::BINGKAI * $S)
                                                    @php($tinggiBaris = ($H - 2 * $p) / max(1, count($r['levels'])))
                                                    @php($lebarSel = ($W - 2 * $p) / $r['kolom'])
                                                    @forelse ($r['levels'] as $i => $l)
                                                        @php($yb = $p + $i * $tinggiBaris)
                                                        <text x="-5" y="{{ $yb + $tinggiBaris / 2 + 4 }}" font-size="{{ 11 * $F }}" font-weight="600" text-anchor="end" fill="currentColor">{{ $l['code'] }}</text>
                                                        @foreach ($l['bins'] as $j => $b)
                                                            @php($warnaBin = $b['nonaktif'] ? '#dee2e6' : ($mode === 'umur' ? $warnaUmur($b['umur']) : $warnaStatus[$b['status']]))
                                                            <rect x="{{ $p + $j * $lebarSel + 2 }}" y="{{ $yb + 2 }}" width="{{ max(1, $lebarSel - 4) }}" height="{{ max(1, $tinggiBaris - 4) }}" rx="3"
                                                                  fill="{{ $warnaBin }}" stroke="{{ $b['cocok'] ? '#f76707' : ($binId === $b['id'] && $dipilih ? '#1c7ed6' : '#ced4da') }}" stroke-width="{{ $b['cocok'] || ($binId === $b['id'] && $dipilih) ? 2.5 : 1 }}"><title>{{ $b['code'] }}{{ $b['total'] > 0 ? ' · '.$angka($b['total']) : ' · '.__('kosong') }}</title></rect>
                                                            @if ($lebarSel >= 28 && $tinggiBaris >= 16)
                                                                <text x="{{ $p + ($j + 0.5) * $lebarSel }}" y="{{ $yb + $tinggiBaris / 2 + 4 }}" font-size="{{ $lebarSel >= 44 ? 12 : 10 }}" text-anchor="middle" fill="#212529" pointer-events="none">{{ $b['short'] }}</text>
                                                            @endif
                                                        @endforeach
                                                    @empty
                                                        <text x="{{ $W / 2 }}" y="{{ $H / 2 + 4 }}" font-size="10" font-style="italic" text-anchor="middle" fill="#868e96">{{ __('belum ada level') }}</text>
                                                    @endforelse
                                                @endif
                                                @if ($edit && $dipilih)
                                                    <rect data-handle x="{{ $r['len'] * $S - 7 }}" y="{{ $r['wid'] * $S - 7 }}" width="14" height="14" fill="#6366f1" style="cursor: nwse-resize" />
                                                @endif
                                            </g>
                                        @endforeach

                                        @if ($edit && $pilihZ)
                                            <rect data-handle x="{{ ($z['length_m'] ?? $z['w']) * $S - 9 }}" y="{{ ($z['width_m'] ?? $z['h']) * $S - 9 }}" width="18" height="18" fill="#6366f1" style="cursor: nwse-resize" />
                                        @endif
                                    </g>
                                @endforeach

                                {{-- Objek padat (pintu, dock, pilar, kantor) di atas zona. --}}
                                @foreach (collect($denah['objects'])->where('solid', true) as $o)
                                    @include('livewire.warehouse.partials.floor-plan-object', ['o' => $o])
                                @endforeach
                            </svg>
                        </div>
                    </div>
                    <div class="card-footer d-flex flex-wrap justify-content-between gap-2 small">
                        <div class="d-flex flex-wrap gap-2">
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
                        @if ($edit)
                            <span class="text-muted">{{ __('Seret untuk memindah (kelipatan 0,5 m) · tarik kotak di sudut kanan-bawah untuk ukuran · panah geser 0,5 m (Shift 0,1 m) · R putar 90°') }}</span>
                        @endif
                    </div>
                </div>

                @if ($denah['zones'] === [])
                    <div class="alert alert-info">{{ $bolehUbah ? __('Gudang ini belum punya zona. Klik Atur denah lalu Tambah zona.') : __('Gudang ini belum punya zona.') }}</div>
                @endif

                @if ($edit)
                    @include('livewire.warehouse.partials.layout-structure-forms')
                @endif
            </div>

            @if ($adaPanel)
                <div class="col-xl-4">
                    @if ($rak)
                        @include('livewire.warehouse.partials.rack-panel')
                    @elseif ($zonaPilih)
                        @include('livewire.warehouse.partials.zone-panel')
                    @elseif ($objekPilih)
                        @include('livewire.warehouse.partials.object-panel')
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

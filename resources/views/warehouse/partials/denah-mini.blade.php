{{--
    Denah mini (A-400, A-401): tampak atas satu gudang digambar di server dari muatan denah yang
    sama dengan layar Denah (WarehouseLayoutData::payload) — zona, rak dengan petak per tingkat,
    area lantai, objek denah. Bentuk kotak rak memperlihatkan arahnya (memanjang ke samping /
    ke bawah). Tanpa stok, tanpa harga.

    Variabel: $d (payload), $sorot (kunci 'rak:<id>' / 'bin:<id>' yang disorot), $tolak (rak id =>
    kode barang yang menguasai rak itu — tidak bisa diklik), $klik (bool: rak bisa diklik →
    wire:click="pilihRakDenah(id)"), $rakAktif (id rak terpilih), $tinggi (CSS max-height).
--}}
@php
    $S = \App\Domain\Warehouse\Support\WarehouseLayoutData::SKALA;
    $K = $d['kanvas'];
    $M = 28;
    $sorot = array_flip($sorot ?? []);
    $tolak = $tolak ?? [];
    $klik = $klik ?? false;
    $rakAktif = $rakAktif ?? null;
    $pendek = fn ($s, $n) => mb_strlen((string) $s) > $n ? mb_substr((string) $s, 0, $n - 1).'…' : (string) $s;
    $angka = fn ($n) => str_replace('.', ',', rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.'));
@endphp
<svg viewBox="{{ -$M }} {{ -$M }} {{ $K['w'] * $S + 2 * $M }} {{ $K['h'] * $S + 2 * $M }}" role="img"
     aria-label="{{ __('Denah mini') }}" data-denah-mini style="width: 100%; height: auto; max-height: {{ $tinggi ?? '60vh' }}; display: block">
    @if ($d['gedung'])
        <rect x="0" y="0" width="{{ $d['gedung']['p'] * $S }}" height="{{ $d['gedung']['l'] * $S }}" fill="none" stroke="#212529" stroke-width="4"/>
    @endif
    @foreach ($d['objects'] as $o)
        <rect x="{{ $o['x'] * $S }}" y="{{ $o['y'] * $S }}" width="{{ $o['p'] * $S }}" height="{{ $o['l'] * $S }}" rx="4" fill="{{ $o['fill'] }}" stroke="{{ $o['stroke'] }}" stroke-width="1.5"><title>{{ $o['label'] }} · {{ $o['name'] }}</title></rect>
    @endforeach
    @foreach ($d['zones'] as $z)
        <g transform="translate({{ $z['x'] * $S }},{{ $z['y'] * $S }})" data-mini-zona="{{ $z['code'] }}">
            <rect x="0" y="0" width="{{ $z['w'] * $S }}" height="{{ $z['h'] * $S }}" rx="8" fill="#eef2ff" fill-opacity="0.55" stroke="#6366f1" stroke-width="2"/>
            <text x="10" y="22" font-size="17" font-weight="600" fill="#4338ca">Zona {{ $z['code'] }} — {{ $pendek($z['name'], 28) }}</text>
            @foreach ($z['racks'] as $r)
                @php
                    $W = $r['w'] * $S;
                    $H = $r['h'] * $S;
                    $kunciRak = 'rak:'.$r['id'];
                    $rakSorot = isset($sorot[$kunciRak]);
                    $binSorot = collect($r['levels'])->flatMap(fn ($l) => $l['bins'])->contains(fn ($b) => isset($sorot['bin:'.$b['id']]));
                    $ditolak = isset($tolak[(string) $r['id']]);
                    $aktif = $rakAktif !== null && (int) $r['id'] === (int) $rakAktif;
                    $bisaKlik = $klik && ! $ditolak;
                    $garis = $aktif ? '#1c7ed6' : ($rakSorot || $binSorot ? '#1c7ed6' : ($ditolak ? '#adb5bd' : '#495057'));
                    $arah = ($r['orientation'] ?? 'h') === 'v' ? __('Memanjang ke bawah') : __('Memanjang ke samping');
                    $judul = ($r['is_area'] ? __('Area lantai') : __('Rak')).' '.$r['code'].($r['name'] ? ' — '.$r['name'] : '').' · '.$arah.' · '.$angka($r['len']).' × '.$angka($r['wid']).' m'
                        .($r['is_area'] ? '' : ' · '.count($r['levels']).' '.__('tingkat').' × '.$r['kolom'].' '.__('bin'))
                        .($ditolak ? ' · '.__('khusus barang').' '.$tolak[(string) $r['id']] : '');
                @endphp
                <g transform="translate({{ $r['x'] * $S }},{{ $r['y'] * $S }})" data-mini-rak="{{ $r['code'] }}"
                   @if ($bisaKlik) wire:click="pilihRakDenah({{ $r['id'] }})" role="button" tabindex="0" style="cursor: pointer" @endif
                   @if ($ditolak) data-mini-tolak aria-disabled="true" @endif>
                    <title>{{ $judul }}</title>
                    <text x="0" y="-6" font-size="13" font-weight="600" fill="currentColor">{{ $r['code'] }}{{ $r['is_area'] ? ' ▦' : '' }}@if ($r['name']) <tspan font-weight="400" fill-opacity="0.65">· {{ $pendek($r['name'], 16) }}</tspan>@endif</text>
                    @if ($r['is_area'])
                        <rect x="0" y="0" width="{{ $W }}" height="{{ $H }}" rx="6" fill="{{ $rakSorot ? '#d0ebff' : ($ditolak ? '#e9ecef' : '#f8f9fa') }}" stroke="{{ $garis }}" stroke-width="{{ $aktif || $rakSorot ? 3.5 : 1.5 }}" stroke-dasharray="6 3"/>
                        <text x="{{ $W / 2 }}" y="{{ $H / 2 + 4 }}" font-size="12" text-anchor="middle" fill="#495057" pointer-events="none">{{ __('Area lantai') }}</text>
                    @else
                        <rect x="0" y="0" width="{{ $W }}" height="{{ $H }}" rx="6" fill="{{ $rakSorot ? '#d0ebff' : ($ditolak ? '#e9ecef' : '#ffffff') }}" stroke="{{ $garis }}" stroke-width="{{ $aktif || $rakSorot ? 3.5 : 1.5 }}"/>
                        @php
                            $p = \App\Domain\Warehouse\Support\WarehouseLayoutData::BINGKAI * $S;
                            $tb = ($H - 2 * $p) / max(1, count($r['levels']));
                        @endphp
                        @foreach ($r['levels'] as $i => $l)
                            @php $kolom = max(1, count($l['bins'])); $lebar = ($W - 2 * $p) / $kolom; @endphp
                            <text x="-5" y="{{ $p + $i * $tb + $tb / 2 + 4 }}" font-size="11" font-weight="600" text-anchor="end" fill="currentColor">{{ $l['code'] }}</text>
                            @foreach ($l['bins'] as $j => $b)
                                @php $bs = isset($sorot['bin:'.$b['id']]); @endphp
                                <rect x="{{ $p + $j * $lebar + 2 }}" y="{{ $p + $i * $tb + 2 }}" width="{{ max(1, $lebar - 4) }}" height="{{ max(1, $tb - 4) }}" rx="3"
                                      fill="{{ $bs ? '#1c7ed6' : ($b['nonaktif'] ? '#dee2e6' : '#f1f3f5') }}" stroke="{{ $bs ? '#1864ab' : '#ced4da' }}" stroke-width="{{ $bs ? 2 : 1 }}" data-mini-bin="{{ $b['code'] }}">
                                    <title>{{ $b['pendek'] }}</title>
                                </rect>
                                @if ($lebar >= 28 && $tb >= 16)
                                    <text x="{{ $p + $j * $lebar + $lebar / 2 }}" y="{{ $p + $i * $tb + $tb / 2 + 4 }}" font-size="{{ $lebar >= 44 ? 12 : 10 }}" text-anchor="middle" fill="{{ $bs ? '#ffffff' : '#212529' }}" pointer-events="none">{{ $b['short'] }}</text>
                                @endif
                            @endforeach
                        @endforeach
                    @endif
                    @if ($ditolak)
                        <text x="{{ $W - 6 }}" y="{{ $H - 6 }}" font-size="14" text-anchor="end" fill="#c2410c" pointer-events="none">⊘</text>
                    @endif
                    @if ($rakSorot)
                        <circle cx="{{ $W - 9 }}" cy="9" r="6" fill="#1c7ed6" pointer-events="none"/>
                    @endif
                </g>
            @endforeach
        </g>
    @endforeach
</svg>

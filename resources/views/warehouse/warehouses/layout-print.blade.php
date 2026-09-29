{{--
    Cetak denah (A-370): halaman cetak mandiri (Ctrl+P / tombol Cetak), A4 mendatar.
    Tampak atas dari data denah yang sama + barang per rak dari Tempat Simpan + tips tata letak.
    Hanya membaca; tanpa harga (D-07).
--}}
@php
    $S = $skala;
    $K = $d['kanvas'];
    $M = 30;
    $pendek = fn ($s, $n) => mb_strlen((string) $s) > $n ? mb_substr((string) $s, 0, $n - 1).'…' : (string) $s;
    $barangRak = collect($d['daftar'])->keyBy(fn ($r) => $r['zona'].'|'.$r['rak']);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Denah gudang') }} {{ $gudang->code }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        body { font-family: system-ui, -apple-system, "Segoe UI", Arial, sans-serif; font-size: 11px; color: #212529; margin: 16px; background: #fff; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        h2 { font-size: 14px; margin: 16px 0 6px; }
        .muted { color: #6c757d; }
        .alat { margin-bottom: 12px; }
        .alat button { font-size: 13px; padding: 6px 14px; }
        svg { width: 100%; height: auto; border: 1px solid #dee2e6; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #adb5bd; padding: 3px 5px; vertical-align: top; text-align: left; }
        th { background: #f1f3f5; }
        .khusus { font-weight: 600; color: #c2410c; }
        .tips li { margin-bottom: 3px; }
        .pisah { page-break-before: always; }
        @media print { .alat { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="alat"><button type="button" onclick="window.print()">{{ __('Cetak') }}</button></div>
    <h1>{{ __('Denah gudang') }} {{ $gudang->code }} — {{ $gudang->name }}</h1>
    <div class="muted">{{ __('Dicetak') }} {{ now()->lokal()->format('d/m/Y H:i') }} · {{ __('tampak atas; ▣ = barang yang ditetapkan (Tempat Simpan), * = Khusus barang ini') }}</div>

    <svg viewBox="{{ -$M }} {{ -$M }} {{ $K['w'] * $S + 2 * $M }} {{ $K['h'] * $S + 2 * $M }}" role="img" aria-label="{{ __('Denah gudang') }} {{ $gudang->code }}" data-cetak-svg>
        @if ($d['gedung'])
            <rect x="0" y="0" width="{{ $d['gedung']['p'] * $S }}" height="{{ $d['gedung']['l'] * $S }}" fill="none" stroke="#212529" stroke-width="5"/>
        @endif
        @foreach ($d['objects'] as $o)
            <rect x="{{ $o['x'] * $S }}" y="{{ $o['y'] * $S }}" width="{{ $o['p'] * $S }}" height="{{ $o['l'] * $S }}" fill="{{ $o['fill'] }}" stroke="{{ $o['stroke'] }}" stroke-width="1.5"/>
            <text x="{{ $o['x'] * $S + 6 }}" y="{{ $o['y'] * $S + 16 }}" font-size="13" fill="#495057">{{ $pendek($o['name'], 24) }}</text>
        @endforeach
        @foreach ($d['zones'] as $z)
            <g transform="translate({{ $z['x'] * $S }},{{ $z['y'] * $S }})">
                <rect x="0" y="0" width="{{ $z['w'] * $S }}" height="{{ $z['h'] * $S }}" rx="8" fill="#eef2ff" fill-opacity="0.5" stroke="#6366f1" stroke-width="2"/>
                <text x="10" y="22" font-size="17" font-weight="600" fill="#4338ca">Zona {{ $z['code'] }} — {{ $pendek($z['name'], 28) }}</text>
                @foreach ($z['racks'] as $r)
                    @php
                        $brg = $barangRak->get($z['code'].'|'.$r['code'])['barang'] ?? [];
                        $kode = collect($brg)->map(fn ($b) => $b['code'].($b['khusus'] ? '*' : ''))->unique()->values();
                    @endphp
                    <g transform="translate({{ $r['x'] * $S }},{{ $r['y'] * $S }})">
                        <rect x="0" y="0" width="{{ $r['w'] * $S }}" height="{{ $r['h'] * $S }}" rx="5" fill="{{ $kode->isNotEmpty() ? '#e7f5ff' : '#ffffff' }}" stroke="#495057" stroke-width="1.5"@if ($r['is_area']) stroke-dasharray="6 3"@endif/>
                        <text x="0" y="-6" font-size="14" font-weight="600" fill="#212529">{{ $r['code'] }}{{ $r['is_area'] ? ' ▦' : '' }}</text>
                        @foreach ($kode->take(4) as $i => $k)
                            <text x="6" y="{{ 16 + $i * 14 }}" font-size="12" fill="#1c7ed6">▣ {{ $pendek($k, 18) }}</text>
                        @endforeach
                        @if ($kode->count() > 4)
                            <text x="6" y="{{ 16 + 4 * 14 }}" font-size="12" fill="#1c7ed6">+{{ $kode->count() - 4 }}</text>
                        @endif
                    </g>
                @endforeach
            </g>
        @endforeach
    </svg>

    <h2 class="pisah">{{ __('Barang per rak (Tempat Simpan)') }}</h2>
    <table data-cetak-barang>
        <thead>
            <tr><th style="width: 12%">{{ __('Zona · Rak') }}</th><th style="width: 18%">{{ __('Tempat') }}</th><th>{{ __('Barang') }}</th></tr>
        </thead>
        <tbody>
            @php $ada = false; @endphp
            @foreach ($d['daftar'] as $r)
                @foreach ($r['barang'] as $b)
                    @php $ada = true; @endphp
                    <tr>
                        <td>{{ $r['zona'] }} · {{ $r['rak'] }}{{ $r['area'] ? ' ('.__('area').')' : '' }}</td>
                        <td>{{ $b['dimana'] }}</td>
                        <td><strong>{{ $b['code'] }}</strong> — {{ $b['name'] }} @if ($b['khusus'])<span class="khusus">· {{ __('Khusus barang ini') }}</span>@endif</td>
                    </tr>
                @endforeach
            @endforeach
            @unless ($ada)
                <tr><td colspan="3" class="muted">{{ __('Belum ada tempat simpan. Atur lewat mode Tata letak barang di Denah, detail item, atau impor Excel.') }}</td></tr>
            @endunless
        </tbody>
    </table>

    <h2>{{ __('Tips tata letak (saran)') }}</h2>
    <ul class="tips" data-cetak-tips>
        @foreach ($d['tips'] as $tip)
            <li>{{ __($tip) }}</li>
        @endforeach
    </ul>
</body>
</html>

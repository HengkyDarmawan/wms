<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $laporan->title() }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #111; }
        h1 { font-size: 13pt; margin: 0 0 2pt; }
        .meta { color: #555; margin-bottom: 8pt; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 0.5pt solid #999; padding: 2.5pt 3pt; text-align: left; vertical-align: top; }
        th { background: #eee; }
        td.num { text-align: right; }
        .kosong { text-align: center; color: #777; padding: 10pt; }
    </style>
</head>
<body>
    <h1>{{ $laporan->title() }}</h1>
    <div class="meta">
        {{ $company?->name }} · {{ __('dicetak') }} {{ now()->timezone($company?->timezone ?? 'Asia/Jakarta')->format('d/m/Y H:i') }}
        · {{ $baris->count() }} {{ __('baris') }}
        @foreach ($filters as $k => $v) · {{ $laporan->filters()[$k]['label'] ?? $k }}: {{ $laporan->filters()[$k]['options'][$v] ?? $v }} @endforeach
    </div>
    <table>
        <thead>
            <tr>
                @foreach ($laporan->columns() as $judul)
                    <th>{{ $judul }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($baris as $b)
                <tr>
                    @foreach (array_keys($laporan->columns()) as $k)
                        @php($v = $b[$k] ?? null)
                        <td class="{{ is_int($v) || is_float($v) ? 'num' : '' }}">{{ is_float($v) ? rtrim(rtrim(number_format($v, 4, ',', '.'), '0'), ',') : $v }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td class="kosong" colspan="{{ count($laporan->columns()) }}">{{ __('Tidak ada data.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

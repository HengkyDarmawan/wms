{{-- Layout induk cetak dokumen (18 §5.2). Dirender dompdf: CSS sederhana, tanpa flex/grid. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $nomor }}</title>
    <style>
        @page { margin: 14mm 12mm 18mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; }
        h1 { font-size: 15px; margin: 0; color: {{ $kop['accent'] }}; }
        table { width: 100%; border-collapse: collapse; }
        .baris { margin-top: 10px; }
        .baris th, .baris td { border: 1px solid #999; padding: 4px 5px; vertical-align: top; }
        .baris th { background: #eef2f6; text-align: left; }
        .r { text-align: right; }
        .c { text-align: center; }
        .muted { color: #666; }
        .info td { padding: 2px 6px 2px 0; vertical-align: top; }
        .info .k { color: #666; width: 22%; }
        .garis { border-top: 2px solid {{ $kop['accent'] }}; margin: 6px 0 8px; }
        .catatan { margin-top: 8px; }
        .kaki { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 8px; color: #666; }
        .batal { position: fixed; top: 40%; left: 0; right: 0; text-align: center; font-size: 72px;
                 color: #c0392b; opacity: 0.18; transform: rotate(-30deg); }
    </style>
</head>
<body>
    @if ($batal)
        <div class="batal">{{ __('DIBATALKAN') }}</div>
    @endif

    @include('print.partials.kop')

    @yield('isi')

    @include('print.partials.signatures')

    <div class="kaki">
        <table>
            <tr>
                <td>{{ $kop['footer'] }}</td>
                <td class="r">{{ __('Dicetak') }} {{ now()->format('d/m/Y H:i') }} · {{ ($bernilai ?? false) ? __('nilai dalam Rupiah') : __('tanpa nilai uang') }}</td>
            </tr>
        </table>
    </div>
</body>
</html>

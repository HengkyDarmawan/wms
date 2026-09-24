{{--
    Kop layout induk (18 §5.2). Bisa dipakai cetakan modul lain, mis. laporan opname:
    cukup kirim `$judul`, `$nomor`, dan opsional `$status`, `$qr`. Tanpa `$kop`, kop
    diambil dari layout induk company.
--}}
@php
    $kop ??= app(\App\Domain\Template\Support\PrintAssets::class)->kop();
    $judul ??= $type->label();
@endphp
<table>
    <tr>
        <td style="width: 55%; vertical-align: top;">
            @if ($kop['logo'])
                <img src="{{ $kop['logo'] }}" style="max-height: 14mm; max-width: 45mm; margin-bottom: 3px;"><br>
            @endif
            <strong style="font-size: 11px;">{{ $kop['company'] }}</strong><br>
            @if ($kop['header'] !== '')
                <span class="muted">{!! nl2br(e($kop['header'])) !!}</span>
            @endif
        </td>
        <td style="vertical-align: top; text-align: right;">
            <h1>{{ $judul }}</h1>
            <strong style="font-size: 11px;">{{ $nomor }}</strong><br>
            @if (! empty($status))
                <span class="muted">{{ __('Status') }}: {{ $status }}</span>
            @endif
        </td>
        @if (! empty($qr))
            <td style="width: 20mm; vertical-align: top; text-align: right;">
                <img src="{{ $qr }}" style="width: 19mm; height: 19mm;">
            </td>
        @endif
    </tr>
</table>
<div style="border-top: 2px solid {{ $kop['accent'] }}; margin: 6px 0 8px;"></div>

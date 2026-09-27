{{-- Satu label: setiap elemen di posisi desainnya (A-262); isi dari LabelPayload (A-121). --}}
@foreach (['logo', 'title', 'subtitle', 'detail', 'qr', 'barcode'] as $kunci)
    @php
        $e = $elements[$kunci];
        $tampil = match ($kunci) {
            'barcode' => $mode->hasBarcode() && $label['barcode'],
            'qr' => $mode->hasQr() && $label['qr_image'],
            'logo' => $e['visible'] && $label['logo'],
            default => $e['visible'] && trim((string) $label[$kunci]) !== '',
        };
        $kotak = 'left: '.$mm($e['x']).'; top: '.$mm($e['y']).'; width: '.$mm($e['w']).'; height: '.$mm($e['h']).';';
    @endphp
    @continue(! $tampil)
    @switch($kunci)
        @case('barcode')
            @php
                $teksH = $e['show_text'] ? round($e['font'] * 0.3528 * 1.25, 2) : 0;
                $batangH = max(1, $e['h'] - $teksH);
            @endphp
            <div class="el" style="{{ $kotak }}">
                <img src="{{ $label['barcode'] }}" style="width: {{ $mm($e['w']) }}; height: {{ $mm($batangH) }};">
                @if ($e['show_text'])
                    <div style="font-size: {{ $e['font'] }}pt; text-align: center; white-space: nowrap;">{{ $label['code128'] }}</div>
                @endif
            </div>
            @break
        @case('qr')
            @php
                $sisi = min($e['w'], $e['h']);
                $geser = match ($e['align']) { 'left' => 0, 'right' => $e['w'] - $sisi, default => ($e['w'] - $sisi) / 2 };
            @endphp
            <div class="el" style="left: {{ $mm($e['x'] + $geser) }}; top: {{ $mm($e['y'] + ($e['h'] - $sisi) / 2) }}; width: {{ $mm($sisi) }}; height: {{ $mm($sisi) }};">
                <img src="{{ $label['qr_image'] }}" style="width: {{ $mm($sisi) }}; height: {{ $mm($sisi) }};">
            </div>
            @break
        @case('logo')
            <div class="el" style="{{ $kotak }} text-align: {{ $e['align'] }};">
                <img src="{{ $label['logo'] }}" style="max-width: {{ $mm($e['w']) }}; max-height: {{ $mm($e['h']) }};">
            </div>
            @break
        @default
            <div class="el" style="{{ $kotak }} font-size: {{ $e['font'] }}pt; font-weight: {{ $e['bold'] ? 'bold' : 'normal' }}; text-align: {{ $e['align'] }};">{{ $label[$kunci] }}</div>
    @endswitch
@endforeach

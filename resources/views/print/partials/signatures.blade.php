{{--
    Blok tanda tangan (A-125). Nama pelaku yang diketahui sistem ikut tercetak.
    A-264: kotak berpelaku diberi QR segel tanda tangan + waktu tindakan + kode;
    QR membuka halaman verifikasi publik /verifikasi/{token}.
--}}
@if (! empty($tandaTangan))
    <table style="margin-top: 18px; page-break-inside: avoid;">
        <tr>
            @foreach ($tandaTangan as $blok)
                <td style="width: {{ floor(100 / count($tandaTangan)) }}%; text-align: center; vertical-align: top; padding: 0 6px;">
                    {{ $blok['label'] }}
                    <table style="width: 100%; margin: 2px 0;">
                        <tr>
                            @if (! empty($blok['qr']))
                                <td style="width: 16mm; padding: 0; vertical-align: middle;">
                                    <img src="{{ $blok['qr'] }}" style="width: 15mm; height: 15mm;">
                                </td>
                            @endif
                            <td style="height: 18mm; padding: 0; text-align: center; vertical-align: middle;">
                                @if ($blok['signature'])
                                    <img src="{{ $blok['signature'] }}" style="max-height: 17mm; max-width: 36mm;">
                                @endif
                            </td>
                        </tr>
                    </table>
                    <div style="border-top: 1px solid #555; padding-top: 2px;">
                        {{ $blok['name'] ?? '' }}&nbsp;
                    </div>
                    @if (! empty($blok['code']))
                        <div style="font-size: 6.5px; color: #666; margin-top: 1px;">
                            {{ $blok['at']?->lokal()->format('d/m/Y H:i') }} · {{ __('segel') }} {{ $blok['code'] }} · {{ __('pindai QR untuk verifikasi') }}
                        </div>
                    @endif
                </td>
            @endforeach
        </tr>
    </table>
@endif

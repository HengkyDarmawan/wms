{{-- Blok tanda tangan (A-125). Nama pelaku yang diketahui sistem ikut tercetak. --}}
@if (! empty($tandaTangan))
    <table style="margin-top: 18px; page-break-inside: avoid;">
        <tr>
            @foreach ($tandaTangan as $blok)
                <td style="width: {{ floor(100 / count($tandaTangan)) }}%; text-align: center; vertical-align: top; padding: 0 6px;">
                    {{ $blok['label'] }}
                    <div style="height: 18mm; margin: 2px 0;">
                        @if ($blok['signature'])
                            <img src="{{ $blok['signature'] }}" style="max-height: 17mm; max-width: 40mm;">
                        @endif
                    </div>
                    <div style="border-top: 1px solid #555; padding-top: 2px;">
                        {{ $blok['name'] ?? '' }}&nbsp;
                    </div>
                </td>
            @endforeach
        </tr>
    </table>
@endif

{{--
    Label bin/item/lot/potongan (18 §5.3, A-120). Thermal 50×30 mm: satu label per
    halaman. A4 3×8: 24 label 70×37 mm per lembar. Tanpa flex/grid karena dompdf.
--}}
@php
    use App\Domain\Template\Enums\PaperSize;
    $thermal = $paper === PaperSize::Label50x30;
    $halaman = $thermal ? $labels->chunk(1) : $labels->chunk(24);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $type->label() }}</title>
    <style>
        /* Lembar 3×8 = 210×296 mm, tepat A4 tanpa margin. */
        @page { margin: 0; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #000; }
        .lanjut { page-break-before: always; }
        table.lembar { border-collapse: collapse; margin: 0 auto; }
        td.sel { width: 70mm; height: 37mm; padding: 0; vertical-align: top; overflow: hidden; }
        /* dompdf mengabaikan box-sizing: lebar = ukuran label − padding kiri-kanan. */
        .label { width: {{ $thermal ? '46mm' : '62mm' }}; height: {{ $thermal ? '26mm' : '30.5mm' }};
                 padding: {{ $thermal ? '1.5mm 2mm 2mm 2mm' : '3mm 4mm' }}; overflow: hidden; }
        .judul { font-size: {{ $thermal ? '8.5pt' : '10pt' }}; font-weight: bold; white-space: nowrap; overflow: hidden; }
        .sub { font-size: {{ $thermal ? '6pt' : '7pt' }}; line-height: 1.15; height: {{ $thermal ? '4.4mm' : '5.2mm' }}; overflow: hidden; }
        .detail { font-size: {{ $thermal ? '6pt' : '7pt' }}; color: #333; }
        .qr { width: {{ $thermal ? '13mm' : '17mm' }}; height: {{ $thermal ? '13mm' : '17mm' }}; }
        .batang { width: 100%; height: {{ $thermal ? '8mm' : '10mm' }}; }
        .kode { font-size: 6pt; text-align: center; letter-spacing: 0.3pt; }
    </style>
</head>
<body>
    @foreach ($halaman as $isi)
        <div class="halaman {{ $loop->first ? '' : 'lanjut' }}">
            @if ($thermal)
                @include('print.labels.item', ['label' => $isi->first()])
            @else
                <table class="lembar">
                    @foreach ($isi->values()->chunk(3) as $baris)
                        <tr>
                            @foreach ($baris as $label)
                                <td class="sel">@include('print.labels.item', ['label' => $label])</td>
                            @endforeach
                            @for ($kosong = $baris->count(); $kosong < 3; $kosong++)
                                <td class="sel"></td>
                            @endfor
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>
    @endforeach
</body>
</html>

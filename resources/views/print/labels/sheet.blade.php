{{--
    Label bin/item/lot/potongan (18 §5.3). Ukuran halaman dan posisi tiap label
    dari master ukuran label (A-261); posisi elemen dari desain label (A-262).
    Semua posisi absolut dalam mm, tanpa flex/grid karena dompdf.
--}}
@php
    $posisi = $format->positions();
    [$halW, $halH] = $format->pageSize();
    $halaman = $labels->chunk($format->perPage());
    $mm = fn (float $v) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.').'mm';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $type->label() }}</title>
    <style>
        @page { margin: 0; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #000; }
        /* Tinggi dikurangi sedikit supaya dompdf tidak membuat halaman kosong. */
        .halaman { position: relative; width: {{ $mm($halW) }}; height: {{ $mm($halH - 0.3) }}; overflow: hidden; }
        .lanjut { page-break-before: always; }
        .label { position: absolute; overflow: hidden; }
        .el { position: absolute; overflow: hidden; line-height: 1.2; }
        .el img { display: block; }
    </style>
</head>
<body>
    @foreach ($halaman as $isi)
        <div class="halaman {{ $loop->first ? '' : 'lanjut' }}">
            @foreach ($isi->values() as $i => $label)
                <div class="label" style="left: {{ $mm($posisi[$i][0]) }}; top: {{ $mm($posisi[$i][1]) }}; width: {{ $mm($format->width_mm) }}; height: {{ $mm($format->height_mm) }};">
                    @include('print.labels.item', ['label' => $label, 'elements' => $elements, 'mode' => $mode, 'mm' => $mm])
                </div>
            @endforeach
        </div>
    @endforeach
</body>
</html>

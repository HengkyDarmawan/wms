@extends('print.layout')

@section('isi')
    <p class="muted">{{ __('Contoh tampilan kop, tabel, dan blok tanda tangan. Isi dokumen yang sebenarnya diambil dari data dokumen.') }}</p>

    <table class="baris">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 18%;">{{ __('Kode') }}</th>
                <th>{{ __('Barang') }}</th>
                <th class="r" style="width: 12%;">{{ __('Jumlah') }}</th>
                <th style="width: 10%;">{{ __('Satuan') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>1</td><td>BAUT-M12</td><td>{{ __('Baut M12 (contoh)') }}</td><td class="r">50</td><td>PCS</td></tr>
            <tr><td>2</td><td>SEMEN-PCC</td><td>{{ __('Semen PCC 50 kg (contoh)') }}</td><td class="r">20</td><td>SAK</td></tr>
        </tbody>
    </table>
@endsection

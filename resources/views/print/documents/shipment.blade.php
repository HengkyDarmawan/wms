@extends('print.layout')
@php use App\Domain\Template\Support\PrintFormat; @endphp

@section('isi')
    <table class="info">
        <tr>
            <td class="k">{{ __('Dari gudang') }}</td>
            <td>{{ $sj->warehouse?->code }} — {{ $sj->warehouse?->name }}</td>
            <td class="k">{{ __('Tanggal berangkat') }}</td>
            <td>{{ $sj->shipped_at?->lokal()->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="k">{{ __('Tujuan') }}</td>
            <td>{{ $sj->destination_type->label() }}: {{ $sj->destinationLabel() }}</td>
            <td class="k">{{ __('Cara kirim') }}</td>
            <td>{{ $sj->shipment_method->label() }}: {{ $sj->carrierLabel() ?: '—' }}</td>
        </tr>
        <tr>
            <td class="k">{{ __('Rujukan') }}</td>
            <td colspan="3">
                {{ implode(', ', $rujukan['req']) ?: '—' }}
                @if ($rujukan['pck'] !== []) · {{ __('PCK') }} {{ implode(', ', $rujukan['pck']) }} @endif
            </td>
        </tr>
    </table>

    <table class="baris">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 16%;">{{ __('Kode') }}</th>
                <th>{{ __('Barang') }}</th>
                <th style="width: 22%;">{{ __('Lot / Serial / Potongan') }}</th>
                <th class="r" style="width: 10%;">{{ __('Jumlah') }}</th>
                <th style="width: 8%;">{{ __('Satuan') }}</th>
                <th class="c" style="width: 10%;">{{ __('Diterima') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $i => $l)
                @php $p = $l->pickTaskLine; @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $p?->item?->code }}</td>
                    <td>{{ $p?->item?->name }}</td>
                    <td>{{ PrintFormat::tracking($p?->lot, $p?->serial, $p?->piece) }}</td>
                    <td class="r">{{ PrintFormat::qty($l->qty_shipped) }}</td>
                    <td>{{ $p?->item?->baseUom?->code }}</td>
                    <td></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($sj->notes)
        <p class="catatan"><strong>{{ __('Catatan') }}:</strong> {{ $sj->notes }}</p>
    @endif
@endsection

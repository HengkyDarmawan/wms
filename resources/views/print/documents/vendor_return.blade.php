@extends('print.layout')
@php use App\Domain\Template\Support\PrintFormat; @endphp

@section('isi')
    <table class="info">
        <tr>
            <td class="k">{{ __('Vendor') }}</td>
            <td>{{ $rtv->vendor?->code }} — {{ $rtv->vendor?->name }}</td>
            <td class="k">{{ __('Penerimaan asal') }}</td>
            <td>{{ $rtv->receipt?->number ?? '—' }}</td>
        </tr>
        <tr>
            <td class="k">{{ __('Dari gudang') }}</td>
            <td>{{ $rtv->warehouse?->code }} — {{ $rtv->warehouse?->name }}</td>
            <td class="k">{{ __('Dikirim') }}</td>
            <td>{{ $rtv->shipped_at?->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
    </table>

    <table class="baris">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 15%;">{{ __('Kode') }}</th>
                <th>{{ __('Barang') }}</th>
                <th style="width: 20%;">{{ __('Lot / Serial / Potongan') }}</th>
                <th class="r" style="width: 10%;">{{ __('Jumlah') }}</th>
                <th style="width: 8%;">{{ __('Satuan') }}</th>
                <th style="width: 18%;">{{ __('Alasan') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $i => $l)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $l->item?->code }}</td>
                    <td>{{ $l->item?->name }}</td>
                    <td>{{ PrintFormat::tracking($l->lot, $l->serial, $l->piece) }}</td>
                    <td class="r">{{ PrintFormat::qty($l->qty_base) }}</td>
                    <td>{{ $l->item?->baseUom?->code }}</td>
                    <td>{{ $l->reason?->label }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($rtv->notes)
        <p class="catatan"><strong>{{ __('Catatan') }}:</strong> {{ $rtv->notes }}</p>
    @endif
@endsection

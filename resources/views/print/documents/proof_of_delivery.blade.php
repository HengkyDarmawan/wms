@extends('print.layout')
@php use App\Domain\Template\Support\PrintFormat; @endphp

@section('isi')
    <table class="info">
        <tr>
            <td class="k">{{ __('Surat jalan') }}</td>
            <td>{{ $sj->number }}</td>
            <td class="k">{{ __('Diterima') }}</td>
            <td>{{ $proof->confirmed_at?->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="k">{{ __('Tujuan') }}</td>
            <td>{{ $sj->destination_type->label() }}: {{ $sj->destinationLabel() }}</td>
            <td class="k">{{ __('Penerima') }}</td>
            <td>{{ $proof->received_by_name ?: $proof->receivedByUser?->name }}</td>
        </tr>
        <tr>
            <td class="k">{{ __('Dari gudang') }}</td>
            <td>{{ $sj->warehouse?->code }} — {{ $sj->warehouse?->name }}</td>
            <td class="k">{{ __('Diisi lewat') }}</td>
            <td>{{ $proof->channel?->label() }}</td>
        </tr>
    </table>

    <table class="baris">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 16%;">{{ __('Kode') }}</th>
                <th>{{ __('Barang') }}</th>
                <th style="width: 18%;">{{ __('Lot / Serial / Potongan') }}</th>
                <th class="r" style="width: 9%;">{{ __('Dikirim') }}</th>
                <th class="r" style="width: 8%;">{{ __('Baik') }}</th>
                <th class="r" style="width: 8%;">{{ __('Rusak') }}</th>
                <th class="r" style="width: 8%;">{{ __('Kurang') }}</th>
                <th style="width: 7%;">{{ __('Satuan') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $i => $l)
                @php $p = $l->shipmentLine?->pickTaskLine; @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $p?->item?->code }}</td>
                    <td>{{ $p?->item?->name }}@if ($l->notes)<br><span class="muted">{{ $l->notes }}</span>@endif</td>
                    <td>{{ PrintFormat::tracking($p?->lot, $p?->serial, $p?->piece) }}</td>
                    <td class="r">{{ PrintFormat::qty($l->shipmentLine?->qty_shipped) }}</td>
                    <td class="r">{{ PrintFormat::qty($l->qty_good) }}</td>
                    <td class="r">{{ PrintFormat::qty($l->qty_damaged) }}</td>
                    <td class="r">{{ PrintFormat::qty($l->qty_missing) }}</td>
                    <td>{{ $p?->item?->baseUom?->code }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($proof->notes)
        <p class="catatan"><strong>{{ __('Catatan') }}:</strong> {{ $proof->notes }}</p>
    @endif
@endsection

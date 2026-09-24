@extends('print.layout')
@php use App\Domain\Template\Support\PrintFormat; @endphp

@section('isi')
    <table class="info">
        <tr>
            <td class="k">{{ __('Surat jalan') }}</td>
            <td>{{ $sj->number }}</td>
            <td class="k">{{ __('Asal selisih') }}</td>
            <td>{{ $dsc->origin?->label() }}</td>
        </tr>
        <tr>
            <td class="k">{{ __('Tujuan') }}</td>
            <td>{{ $sj->destination_type->label() }}: {{ $sj->destinationLabel() }}</td>
            <td class="k">{{ __('Diselesaikan') }}</td>
            <td>{{ $dsc->resolver?->name ?? '—' }} {{ $dsc->resolved_at?->format('d/m/Y H:i') }}</td>
        </tr>
    </table>

    <table class="baris">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 14%;">{{ __('Kode') }}</th>
                <th>{{ __('Barang') }}</th>
                <th style="width: 10%;">{{ __('Jenis') }}</th>
                <th class="r" style="width: 9%;">{{ __('Jumlah') }}</th>
                <th style="width: 7%;">{{ __('Satuan') }}</th>
                <th style="width: 16%;">{{ __('Penyelesaian') }}</th>
                <th style="width: 14%;">{{ __('Alasan') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $i => $l)
                @php $p = $l->shipmentLine?->pickTaskLine; @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $p?->item?->code }}</td>
                    <td>
                        {{ $p?->item?->name }}
                        @php $lacak = PrintFormat::tracking($p?->lot, $p?->serial, $p?->piece); @endphp
                        @if ($lacak !== '')<br><span class="muted">{{ $lacak }}</span>@endif
                    </td>
                    <td>{{ $l->discrepancy_type?->label() }}</td>
                    <td class="r">{{ PrintFormat::qty($l->qty_base) }}</td>
                    <td>{{ $p?->item?->baseUom?->code }}</td>
                    <td>
                        {{ $l->disposition?->label() ?? '—' }}
                        @if ($l->client_decision)<br><span class="muted">{{ $l->client_decision->label() }}</span>@endif
                        @if ($l->claim_ref)<br><span class="muted">{{ __('Klaim') }} {{ $l->claim_ref }}</span>@endif
                    </td>
                    <td>{{ $l->reasonCode?->label }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($dsc->notes)
        <p class="catatan"><strong>{{ __('Catatan') }}:</strong> {{ $dsc->notes }}</p>
    @endif
@endsection

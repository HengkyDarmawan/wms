@extends('print.layout')
@php use App\Domain\Template\Support\PrintFormat; @endphp

@section('isi')
    <table class="info">
        <tr>
            <td class="k">{{ __('Gudang') }}</td>
            <td>{{ $adj->warehouse?->code }} — {{ $adj->warehouse?->name }}</td>
            <td class="k">{{ __('Asal') }}</td>
            <td>
                {{ $adj->origin?->label() }}
                @if ($adj->stockCount) · {{ $adj->stockCount->number }} @endif
                @if ($adj->reversalOf) · {{ __('pembalik') }} {{ $adj->reversalOf->number }} @endif
            </td>
        </tr>
        <tr>
            <td class="k">{{ __('Alasan') }}</td>
            <td>{{ $adj->reason?->label ?? '—' }}</td>
            <td class="k">{{ __('Disetujui') }}</td>
            <td>{{ $adj->approver?->name ?? '—' }} {{ $adj->approved_at?->lokal()->format('d/m/Y H:i') }}</td>
        </tr>
    </table>

    <table class="baris">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 18%;">{{ __('Bin') }}</th>
                <th style="width: 13%;">{{ __('Kode') }}</th>
                <th>{{ __('Barang') }}</th>
                <th style="width: 17%;">{{ __('Lot / Serial / Potongan') }}</th>
                <th style="width: 9%;">{{ __('Kondisi') }}</th>
                <th class="r" style="width: 9%;">{{ __('Jumlah') }}</th>
                <th style="width: 7%;">{{ __('Satuan') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $i => $l)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $l->bin?->code }}</td>
                    <td>{{ $l->item?->code }}</td>
                    <td>{{ $l->item?->name }}@if ($l->reason && $l->reason_code_id !== $adj->reason_code_id)<br><span class="muted">{{ $l->reason->label }}</span>@endif</td>
                    <td>{{ $l->trackingLabel() }}</td>
                    <td>{{ $l->stock_status?->label() }}</td>
                    <td class="r">{{ PrintFormat::qty($l->qty_delta, true) }}</td>
                    <td>{{ $l->item?->baseUom?->code }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($adj->notes)
        <p class="catatan"><strong>{{ __('Catatan') }}:</strong> {{ $adj->notes }}</p>
    @endif
@endsection
